<?php

namespace App\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Services\Finance\InvoiceCurrencyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StripeInvoiceCoreImportService
{
    public function __construct(
        private readonly StripeInvoiceCoreMapper $mapper,
        private readonly InvoiceCurrencyService $currencyService,
        private readonly StripeInvoiceItemImporter $itemImporter,
    ) {}

    /**
     * Materialize open Stripe invoice_syncs into core invoices for a client.
     * Used when assigning Mercado Pago payments so unpaid Stripe invoices appear.
     *
     * @return int Number of sync rows processed
     */
    public function importOpenSyncsForEnterprise(int $teamId, int $enterpriseId, int $limit = 50): int
    {
        return $this->importSyncsForEnterprise(
            $teamId,
            $enterpriseId,
            fn ($query) => $query->where(function ($inner): void
            {
                $inner->whereIn('status', ['open', 'uncollectible'])
                    ->orWhere(function ($unpaid): void
                    {
                        $unpaid->where('paid', false)
                            ->where('amount_remaining', '>', 0);
                    });
            }),
            $limit,
        );
    }

    /**
     * Materialize paid Stripe invoices that still lack Mercado Pago metadata (backfill).
     *
     * @return int Number of sync rows processed
     */
    public function importPaidUnlinkedSyncsForEnterprise(int $teamId, int $enterpriseId, int $limit = 50): int
    {
        return $this->importSyncsForEnterprise(
            $teamId,
            $enterpriseId,
            fn ($query) => $query->paidWithoutMercadoPagoMetadata(),
            $limit,
        );
    }

    /**
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $constrain
     */
    private function importSyncsForEnterprise(int $teamId, int $enterpriseId, callable $constrain, int $limit): int
    {
        $enterprise = Enterprise::query()
            ->where('team_id', $teamId)
            ->whereKey($enterpriseId)
            ->first();

        if (! $enterprise || blank($enterprise->code))
        {
            return 0;
        }

        $query = InvoiceSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->where('customer_id', $enterprise->code)
            ->whereNotExists(function ($sub): void
            {
                $sub->from('invoices')
                    ->whereColumn('invoices.source_reference_id', 'invoice_syncs.external_id')
                    ->whereColumn('invoices.team_id', 'invoice_syncs.team_id')
                    ->where('invoices.source_provider', 'stripe');
            })
            ->orderByRaw('invoice_created_at IS NULL')
            ->orderBy('invoice_created_at')
            ->orderBy('id')
            ->limit(max(1, $limit));

        $constrain($query);

        $processed = 0;

        foreach ($query->get() as $sync)
        {
            if (! $sync instanceof InvoiceSync)
            {
                continue;
            }

            $invoice = $this->importFromSyncRow(
                $sync,
                fallbackEmail: false,
                linkCodeOnEmailMatch: false,
                dryRun: false,
                forceEnterpriseId: $enterpriseId,
            );
            if ($invoice !== null)
            {
                $processed++;
            }
        }

        return $processed;
    }

    public function importFromSyncRow(
        InvoiceSync $row,
        bool $fallbackEmail = true,
        bool $linkCodeOnEmailMatch = true,
        bool $dryRun = false,
        ?int $forceEnterpriseId = null,
    ): ?Invoice {
        if ($forceEnterpriseId !== null)
        {
            $enterpriseId = $forceEnterpriseId;
        } else
        {
            [$enterpriseId] = $this->resolveEnterpriseId(
                $row,
                $fallbackEmail,
                $linkCodeOnEmailMatch,
                $dryRun,
            );
        }

        if (! $enterpriseId)
        {
            return null;
        }

        $date = $this->resolveFiscalDate($row);
        $dueDate = $row->invoice_due_date
            ? Carbon::parse($row->invoice_due_date)->toDateString()
            : null;

        $gross = $this->normalizeAmount($row->subtotal ?? $row->total ?? $row->amount_due ?? 0);
        $discount = $this->normalizeNullableAmount($row->total_discount_amount);
        $total = $this->normalizeAmount($row->total ?? $row->amount_due ?? $gross);
        $coreFields = $this->mapper->mapFromInvoiceSync($row);
        $isCreditNote = str_starts_with((string) $row->external_id, 'cn_');

        $payload = [
            'team_id' => $row->team_id,
            'enterprise_id' => $enterpriseId,
            'billing_id' => null,
            'type_id' => $isCreditNote ? 2 : 1,
            'operation' => 'sell',
            'number' => $this->resolveInvoiceNumber($row->number, $row->external_id),
            'date' => $date,
            'due_date' => $isCreditNote ? null : $dueDate,
            'gross_amount' => $gross,
            'discount' => $discount,
            'total_amount' => $total,
            'balance' => $isCreditNote ? 0.0 : $coreFields['balance'],
            'status' => $isCreditNote
                ? ($coreFields['status'] === 3 ? 3 : 4)
                : $coreFields['status'],
            'source_provider' => 'stripe',
            'source_reference_id' => $row->external_id,
            'source_synced_at' => $row->last_synced_at ?? now(),
        ];

        if (Schema::hasColumn('invoices', 'currency_id'))
        {
            $payload['currency_id'] = $this->currencyService->resolveCurrencyIdFromStripeSync($row)
                ?? $this->currencyService->defaultCurrencyId();
        }

        if ($dryRun)
        {
            return null;
        }

        $existing = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $row->external_id)
            ->first();

        if ($existing)
        {
            if ($isCreditNote && filled($existing->number))
            {
                // Keep Humano CN numbering (e.g. 0005-0252-CN-01); Stripe's lives on invoice_syncs.
                unset($payload['number']);
            }

            $existing->fill($payload);
            $existing->save();
            $this->itemImporter->syncForInvoice($existing, $row);

            return $existing->fresh(['items']);
        }

        $invoice = Invoice::withoutGlobalScopes()->create($payload);
        $this->itemImporter->syncForInvoice($invoice, $row);

        return $invoice->fresh(['items']);
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    public function resolveEnterpriseId(
        InvoiceSync $row,
        bool $fallbackEmail,
        bool $linkCodeOnEmailMatch,
        bool $dryRun,
    ): array {
        $enterprise = Enterprise::query()
            ->where('team_id', $row->team_id)
            ->where('type_id', 1)
            ->where('code', $row->customer_id)
            ->first();

        if ($enterprise)
        {
            return [$enterprise->id, 'code'];
        }

        if (! $fallbackEmail)
        {
            return [null, 'none'];
        }

        $email = strtolower(trim((string) $row->customer_email));
        if ($email === '')
        {
            return [null, 'none'];
        }

        $emailMatches = Enterprise::query()
            ->where('team_id', $row->team_id)
            ->where('type_id', 1)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        if ($emailMatches->count() !== 1)
        {
            return [null, 'none'];
        }

        /** @var Enterprise $matched */
        $matched = $emailMatches->first();

        if ($linkCodeOnEmailMatch && filled($row->customer_id) && blank($matched->code) && ! $dryRun)
        {
            $matched->code = (string) $row->customer_id;
            $matched->save();
        }

        return [$matched->id, 'email'];
    }

    private function normalizeAmount(mixed $amount): float
    {
        $value = (float) $amount;
        if ($value < 0)
        {
            return 0.0;
        }

        return round($value, 2);
    }

    private function normalizeNullableAmount(mixed $amount): ?float
    {
        if ($amount === null)
        {
            return null;
        }

        return $this->normalizeAmount($amount);
    }

    private function resolveInvoiceNumber(?string $number, string $externalId): string
    {
        $number = trim((string) $number);
        if ($number !== '')
        {
            return $number;
        }

        return 'STR-'.Str::upper(Str::substr($externalId, -8));
    }

    /**
     * Fiscal date follows Stripe finalization (number assignment), not draft creation.
     */
    private function resolveFiscalDate(InvoiceSync $row): string
    {
        $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
        $finalizedAt = data_get($payload, 'status_transitions.finalized_at');

        if (is_numeric($finalizedAt))
        {
            return Carbon::createFromTimestampUTC((int) $finalizedAt)
                ->setTimezone(config('app.timezone'))
                ->toDateString();
        }

        if ($row->invoice_created_at)
        {
            return Carbon::parse($row->invoice_created_at)->toDateString();
        }

        return now()->toDateString();
    }
}
