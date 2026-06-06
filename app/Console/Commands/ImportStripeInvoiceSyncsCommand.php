<?php

namespace App\Console\Commands;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Services\Billing\StripeInvoiceCoreMapper;
use App\Services\Finance\InvoiceCurrencyService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportStripeInvoiceSyncsCommand extends Command
{
    protected $signature = 'invoice-syncs:import-stripe
                            {--team_id= : Import only one team}
                            {--limit=500 : Max sync rows to process}
                            {--reconcile : Also refresh rows that already have a core invoice (same team + Stripe source id)}
                            {--fallback-email : Resolve enterprise by email when customer_id/code does not match}
                            {--link-code-on-email-match : When fallback by email succeeds uniquely, write Stripe customer_id into enterprises.code}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map Stripe invoice_syncs rows into core invoices table (idempotent by source reference)';

    public function handle(StripeInvoiceCoreMapper $mapper, InvoiceCurrencyService $currencyService): int
    {
        if (! Schema::hasTable('invoice_syncs'))
        {
            $this->error('Table invoice_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $reconcile = (bool) $this->option('reconcile');
        $fallbackEmail = (bool) $this->option('fallback-email');
        $linkCodeOnEmailMatch = (bool) $this->option('link-code-on-email-match');

        // Process in stable chronological order (invoice_created_at), then row id. When
        // not reconciling, only rows with no core invoice yet (avoids re-processing the
        // same “first N” sync rows on every run).
        $query = InvoiceSync::query()->where('provider', 'stripe');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        } else
        {
            $query->orderBy('team_id');
        }

        if (! $reconcile)
        {
            $query->whereNotExists(function ($q)
            {
                $q->from('invoices')
                    ->whereColumn('invoices.source_reference_id', 'invoice_syncs.external_id')
                    ->whereColumn('invoices.team_id', 'invoice_syncs.team_id')
                    ->where('invoices.source_provider', 'stripe');
            });
        }

        $query
            ->orderByRaw('invoice_created_at IS NULL')
            ->orderBy('invoice_created_at')
            ->orderBy('id');

        $rows = $query->limit($limit)->get();

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row)
        {
            if (! $row instanceof InvoiceSync)
            {
                continue;
            }

            $processed++;

            [$enterpriseId, $resolutionMode] = $this->resolveEnterpriseId(
                $row,
                $fallbackEmail,
                $linkCodeOnEmailMatch,
                $dryRun,
            );

            if (! $enterpriseId)
            {
                $skipped++;
                $reason = $fallbackEmail ? 'customer_id/code or unique email' : 'customer_id/code';
                $this->warn("Skip {$row->external_id}: enterprise not found by {$reason} for team {$row->team_id}");

                continue;
            }

            $date = $row->invoice_created_at
                ? Carbon::parse($row->invoice_created_at)->toDateString()
                : now()->toDateString();
            $dueDate = $row->invoice_due_date
                ? Carbon::parse($row->invoice_due_date)->toDateString()
                : null;

            $gross = $this->normalizeAmount($row->subtotal ?? $row->total ?? $row->amount_due ?? 0);
            $discount = $this->normalizeNullableAmount($row->total_discount_amount);
            $total = $this->normalizeAmount($row->total ?? $row->amount_due ?? $gross);
            $coreFields = $mapper->mapFromInvoiceSync($row);

            $payload = [
                'team_id' => $row->team_id,
                'enterprise_id' => $enterpriseId,
                'billing_id' => null,
                'type_id' => 1,
                'operation' => 'sell',
                'number' => $this->resolveInvoiceNumber($row->number, $row->external_id),
                'date' => $date,
                'due_date' => $dueDate,
                'gross_amount' => $gross,
                'discount' => $discount,
                'total_amount' => $total,
                'balance' => $coreFields['balance'],
                'status' => $coreFields['status'],
                'source_provider' => 'stripe',
                'source_reference_id' => $row->external_id,
                'source_synced_at' => $row->last_synced_at ?? now(),
            ];

            if (Schema::hasColumn('invoices', 'currency_id'))
            {
                $payload['currency_id'] = $currencyService->resolveCurrencyIdFromStripeSync($row)
                    ?? $currencyService->defaultCurrencyId();
            }

            if ($dryRun)
            {
                $this->line("[dry-run] upsert invoice for stripe external_id={$row->external_id}, team={$row->team_id}, matched_by={$resolutionMode}");

                continue;
            }

            $existing = Invoice::withoutGlobalScopes()
                ->where('source_provider', 'stripe')
                ->where('source_reference_id', $row->external_id)
                ->first();

            if ($existing)
            {
                $existing->fill($payload);
                $existing->save();
                $updated++;
            } else
            {
                Invoice::withoutGlobalScopes()->create($payload);
                $created++;
            }
        }

        $this->info(
            "Processed: {$processed} | created: {$created} | updated: {$updated} | skipped: {$skipped}".
            ($reconcile ? ' | reconcile' : ' | pending-only').
            ($dryRun ? ' | dry-run' : ''),
        );

        return self::SUCCESS;
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

    /**
     * @return array{0: int|null, 1: string}
     */
    private function resolveEnterpriseId(
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

        if ($linkCodeOnEmailMatch && filled($row->customer_id) && blank($matched->code))
        {
            if ($dryRun)
            {
                $this->line("[dry-run] would set enterprises.code={$row->customer_id} on enterprise_id={$matched->id}");
            } else
            {
                $matched->code = (string) $row->customer_id;
                $matched->save();
            }
        }

        return [$matched->id, 'email'];
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
}
