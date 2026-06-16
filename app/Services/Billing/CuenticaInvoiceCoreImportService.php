<?php

namespace App\Services\Billing;

use App\Enums\CuenticaInboundDocumentKind;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Services\Finance\InvoiceCurrencyService;
use App\Services\Fiscal\Cuentica\CuenticaCounterpartyResolver;
use App\Services\Fiscal\Cuentica\CuenticaInvoiceCoreMapper;
use App\Services\Fiscal\Cuentica\CuenticaInvoiceItemImporter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CuenticaInvoiceCoreImportService
{
    public function __construct(
        private readonly CuenticaInvoiceCoreMapper $mapper,
        private readonly InvoiceCurrencyService $currencyService,
        private readonly CuenticaCounterpartyResolver $counterpartyResolver,
        private readonly CuenticaInvoiceItemImporter $itemImporter,
        private readonly CuenticaPaymentImportService $paymentImportService,
    ) {}

    public function importFromSyncRow(
        InvoiceSync $row,
        bool $fallbackTaxId = true,
        bool $fallbackEmail = true,
        bool $linkCodeOnMatch = true,
        bool $autoCreateCounterparty = false,
        bool $dryRun = false,
    ): ?Invoice {
        $kind = CuenticaInboundDocumentKind::fromBillingReason($row->billing_reason)
            ?? CuenticaInboundDocumentKind::fromExternalId($row->external_id);

        if ($kind === null)
        {
            return null;
        }

        [$enterpriseId] = $this->resolveEnterpriseId(
            $row,
            $kind,
            $fallbackTaxId,
            $fallbackEmail,
            $linkCodeOnMatch,
            $autoCreateCounterparty,
            $dryRun,
        );

        if (! $enterpriseId)
        {
            return null;
        }

        $date = $row->invoice_created_at
            ? Carbon::parse($row->invoice_created_at)->toDateString()
            : now()->toDateString();

        $gross = $this->normalizeAmount($row->subtotal ?? $row->total ?? 0);
        $total = $this->normalizeAmount($row->total ?? $gross);
        $coreFields = $this->mapper->mapFromInvoiceSync($row);

        $payload = [
            'team_id' => $row->team_id,
            'enterprise_id' => $enterpriseId,
            'billing_id' => null,
            'type_id' => 1,
            'operation' => $kind->operation(),
            'number' => $this->resolveInvoiceNumber($row->number, $row->external_id, $kind),
            'date' => $date,
            'due_date' => null,
            'gross_amount' => $gross,
            'discount' => null,
            'total_amount' => $total,
            'balance' => $coreFields['balance'],
            'status' => $coreFields['status'],
            'source_provider' => 'cuentica',
            'source_reference_id' => $row->external_id,
            'source_synced_at' => $row->last_synced_at ?? now(),
        ];

        if (Schema::hasColumn('invoices', 'currency_id'))
        {
            $payload['currency_id'] = $this->currencyService->resolveCurrencyIdFromCuenticaSync($row)
                ?? $this->currencyService->defaultCurrencyId();
        }

        if ($dryRun)
        {
            return null;
        }

        $existing = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'cuentica')
            ->where('source_reference_id', $row->external_id)
            ->first();

        if ($existing)
        {
            $existing->fill($payload);
            $existing->save();
            $this->itemImporter->syncForInvoice($existing, $row, $kind);
            $this->paymentImportService->syncPaymentForInvoice($existing, $row);

            return $existing->fresh(['items']);
        }

        $invoice = Invoice::withoutGlobalScopes()->create($payload);
        $this->itemImporter->syncForInvoice($invoice, $row, $kind);
        $this->paymentImportService->syncPaymentForInvoice($invoice, $row);

        return $invoice->fresh(['items']);
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    public function resolveEnterpriseId(
        InvoiceSync $row,
        CuenticaInboundDocumentKind $kind,
        bool $fallbackTaxId,
        bool $fallbackEmail,
        bool $linkCodeOnMatch,
        bool $autoCreateCounterparty,
        bool $dryRun,
    ): array {
        $codePrefix = $kind === CuenticaInboundDocumentKind::Sale ? 'cuentica_c_' : 'cuentica_p_';
        $expectedCode = filled($row->customer_id) ? $codePrefix.$row->customer_id : null;

        if ($expectedCode)
        {
            $byCode = Enterprise::query()
                ->where('team_id', $row->team_id)
                ->where('type_id', $kind->enterpriseTypeId())
                ->where('code', $expectedCode)
                ->first();

            if ($byCode)
            {
                return [$byCode->id, 'code'];
            }
        }

        if ($fallbackTaxId)
        {
            $taxId = strtoupper(trim((string) $row->customer_tax_id));
            if ($taxId !== '')
            {
                $billingMatch = EnterpriseBillingAddress::query()
                    ->whereHas('enterprise', function ($query) use ($row, $kind)
                    {
                        $query->where('team_id', $row->team_id)
                            ->where('type_id', $kind->enterpriseTypeId());
                    })
                    ->whereRaw('UPPER(identification_number) = ?', [$taxId])
                    ->first();

                if ($billingMatch?->enterprise)
                {
                    /** @var Enterprise $enterprise */
                    $enterprise = $billingMatch->enterprise;

                    if ($linkCodeOnMatch && $expectedCode && blank($enterprise->code) && ! $dryRun)
                    {
                        $enterprise->code = $expectedCode;
                        $enterprise->save();
                    }

                    return [$enterprise->id, 'tax_id'];
                }
            }
        }

        if ($fallbackEmail)
        {
            $email = strtolower(trim((string) $row->customer_email));
            if ($email !== '')
            {
                $emailMatches = Enterprise::query()
                    ->where('team_id', $row->team_id)
                    ->where('type_id', $kind->enterpriseTypeId())
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->get();

                if ($emailMatches->count() === 1)
                {
                    /** @var Enterprise $matched */
                    $matched = $emailMatches->first();

                    if ($linkCodeOnMatch && $expectedCode && blank($matched->code) && ! $dryRun)
                    {
                        $matched->code = $expectedCode;
                        $matched->save();
                    }

                    return [$matched->id, 'email'];
                }
            }
        }

        if ($autoCreateCounterparty)
        {
            $created = $this->counterpartyResolver->findOrCreateFromSyncRow($row, $kind, $dryRun);
            if ($created)
            {
                return [$created->id, 'created'];
            }
        }

        return [null, 'none'];
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

    private function resolveInvoiceNumber(?string $number, string $externalId, CuenticaInboundDocumentKind $kind): string
    {
        $number = trim((string) $number);
        if ($number !== '')
        {
            return $number;
        }

        $suffix = Str::upper(Str::substr(str_replace(':', '-', $externalId), -12));

        return strtoupper($kind->value).'-'.$suffix;
    }
}
