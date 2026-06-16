<?php

namespace App\Services\Finance;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceCurrencyService
{
    /** @var array<int, int> CMS7 facturas.id_moneda => currencies.id */
    private const LEGACY_MONEDA_MAP = [
        1 => 32,
        2 => 840,
        3 => 978,
        4 => 840,
        5 => 840,
    ];

    public function legacyMonedaIdToCurrencyId(int $legacyMonedaId): int
    {
        return self::LEGACY_MONEDA_MAP[$legacyMonedaId] ?? $this->defaultCurrencyId();
    }

    public function currencyIdFromIsoCode(?string $code): ?int
    {
        if (! filled($code))
        {
            return null;
        }

        $id = Currency::query()
            ->where('code', strtoupper(trim($code)))
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function defaultCurrencyId(): int
    {
        $code = strtoupper((string) config('verifactu.default_currency', 'EUR'));

        return (int) (Currency::query()->where('code', $code)->value('id') ?? 978);
    }

    public function resolveCurrencyIdFromStripeSync(InvoiceSync $sync): ?int
    {
        return $this->currencyIdFromIsoCode($sync->currency);
    }

    public function resolveCurrencyIdFromCuenticaSync(InvoiceSync $sync): ?int
    {
        return $this->currencyIdFromIsoCode($sync->currency)
            ?? $this->currencyIdFromIsoCode((string) config('fiscal.platforms.cuentica.inbound_sync.default_currency', 'EUR'));
    }

    /**
     * @return array{updated: int, stripe: int, legacy: int, manual_default: int}
     */
    public function resync(
        ?int $teamId = null,
        bool $fromLegacy = true,
        bool $onlyNull = false,
        bool $dryRun = false,
        bool $manualDefault = true,
    ): array {
        if (! Schema::hasColumn('invoices', 'currency_id'))
        {
            return [
                'updated' => 0,
                'stripe' => 0,
                'legacy' => 0,
                'manual_default' => 0,
            ];
        }

        $stats = [
            'updated' => 0,
            'stripe' => 0,
            'legacy' => 0,
            'manual_default' => 0,
        ];

        $stats['stripe'] = $this->resyncFromStripeSyncs($teamId, $onlyNull, $dryRun);
        $stats['updated'] += $stats['stripe'];

        if ($fromLegacy)
        {
            $stats['legacy'] = $this->resyncFromLegacyFacturas($teamId, $onlyNull, $dryRun);
            $stats['updated'] += $stats['legacy'];
        }

        if ($manualDefault)
        {
            $stats['manual_default'] = $this->resyncManualDefaultCurrency($teamId, $dryRun);
            $stats['updated'] += $stats['manual_default'];
        }

        return $stats;
    }

    private function resyncFromStripeSyncs(?int $teamId, bool $onlyNull, bool $dryRun): int
    {
        if (! Schema::hasTable('invoice_syncs'))
        {
            return 0;
        }

        $query = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->whereNotNull('source_reference_id');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        if ($onlyNull)
        {
            $query->whereNull('currency_id');
        }

        $updated = 0;

        $query->select(['id', 'team_id', 'source_reference_id', 'currency_id'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $invoices) use (&$updated, $dryRun): void
            {
                $referenceIds = $invoices->pluck('source_reference_id')->filter()->unique()->values();
                if ($referenceIds->isEmpty())
                {
                    return;
                }

                $teamIds = $invoices->pluck('team_id')->unique()->values();

                $syncRows = InvoiceSync::query()
                    ->where('provider', 'stripe')
                    ->whereIn('team_id', $teamIds)
                    ->whereIn('external_id', $referenceIds)
                    ->get(['team_id', 'external_id', 'currency']);

                $syncByKey = $syncRows->keyBy(fn (InvoiceSync $sync): string => $sync->team_id.'|'.$sync->external_id);

                foreach ($invoices as $invoice)
                {
                    $sync = $syncByKey->get($invoice->team_id.'|'.$invoice->source_reference_id);
                    if (! $sync)
                    {
                        continue;
                    }

                    $currencyId = $this->resolveCurrencyIdFromStripeSync($sync);
                    if ($currencyId === null || (int) $invoice->currency_id === $currencyId)
                    {
                        continue;
                    }

                    if (! $dryRun)
                    {
                        Invoice::withoutGlobalScopes()
                            ->whereKey($invoice->id)
                            ->update(['currency_id' => $currencyId]);
                    }

                    $updated++;
                }
            });

        return $updated;
    }

    private function resyncFromLegacyFacturas(?int $teamId, bool $onlyNull, bool $dryRun): int
    {
        if (! $this->legacyFacturasAvailable())
        {
            return 0;
        }

        $invoiceQuery = Invoice::withoutGlobalScopes()
            ->where(function ($query): void
            {
                $query->where('source_provider', 'manual')
                    ->orWhereNull('source_provider');
            });

        if ($teamId !== null)
        {
            $invoiceQuery->where('team_id', $teamId);
        }

        if ($onlyNull)
        {
            $invoiceQuery->whereNull('currency_id');
        }

        $updated = 0;

        $invoiceQuery->select(['id', 'currency_id'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $invoices) use (&$updated, $dryRun): void
            {
                $legacyRows = DB::connection('mysql_legacy')
                    ->table('facturas')
                    ->whereIn('id', $invoices->pluck('id'))
                    ->pluck('id_moneda', 'id');

                foreach ($invoices as $invoice)
                {
                    if (! $legacyRows->has($invoice->id))
                    {
                        continue;
                    }

                    $currencyId = $this->legacyMonedaIdToCurrencyId((int) $legacyRows->get($invoice->id));
                    if ((int) $invoice->currency_id === $currencyId)
                    {
                        continue;
                    }

                    if (! $dryRun)
                    {
                        Invoice::withoutGlobalScopes()
                            ->whereKey($invoice->id)
                            ->update(['currency_id' => $currencyId]);
                    }

                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * CMS manual invoices without id_moneda in legacy default to pesos (id_moneda = 1 → ARS).
     */
    private function resyncManualDefaultCurrency(?int $teamId, bool $dryRun): int
    {
        $currencyId = $this->legacyMonedaIdToCurrencyId(1);

        $query = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'manual')
            ->whereNull('currency_id');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        if ($dryRun)
        {
            return (int) $query->count();
        }

        return (int) $query->update(['currency_id' => $currencyId]);
    }

    private function legacyConnectionAvailable(): bool
    {
        try
        {
            DB::connection('mysql_legacy')->getPdo();
        } catch (\Throwable)
        {
            return false;
        }

        return true;
    }

    private function legacyFacturasAvailable(): bool
    {
        if (! $this->legacyConnectionAvailable())
        {
            return false;
        }

        try
        {
            return Schema::connection('mysql_legacy')->hasTable('facturas');
        } catch (\Throwable)
        {
            return false;
        }
    }
}
