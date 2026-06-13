<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use Illuminate\Support\Facades\Artisan;

class InvoiceInboundSyncService
{
    public function __construct(
        private readonly CuenticaInvoiceCoreImportService $cuenticaImportService,
    ) {}

    /**
     * @return list<string> Provider keys: stripe, cuentica
     */
    public function availableProviders(Team $team): array
    {
        $providers = [];

        if ($this->teamHasStripeInbound($team))
        {
            $providers[] = 'stripe';
        }

        if ($this->teamHasCuenticaInbound($team))
        {
            $providers[] = 'cuentica';
        }

        return $providers;
    }

    public function canSync(Team $team): bool
    {
        return $this->availableProviders($team) !== [];
    }

    /**
     * @return array{providers: list<string>, imported: int, updated: int, skipped: int}
     */
    public function syncForTeam(Team $team): array
    {
        $providers = $this->availableProviders($team);

        if ($providers === [])
        {
            return [
                'providers' => [],
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        if (in_array('stripe', $providers, true))
        {
            Artisan::call('stripe:sync-invoices', [
                '--team_id' => $team->id,
                '--mode' => 'mutable',
                '--limit' => 120,
                '--recent-days' => 90,
            ]);

            Artisan::call('invoice-syncs:import-stripe', [
                '--team_id' => $team->id,
                '--reconcile' => true,
                '--limit' => 200,
                '--fallback-email' => true,
                '--link-code-on-email-match' => true,
            ]);
        }

        if (in_array('cuentica', $providers, true))
        {
            Artisan::call('cuentica:sync-invoices', [
                '--team_id' => $team->id,
                '--mode' => 'mutable',
                '--limit' => 120,
                '--recent-days' => 365,
            ]);

            $importStats = $this->importCuenticaRowsForTeam($team);
            $imported += $importStats['imported'];
            $updated += $importStats['updated'];
            $skipped += $importStats['skipped'];
        }

        return [
            'providers' => $providers,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function importCuenticaRowsForTeam(Team $team, int $limitPerKind = 100): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach (['cuentica_sale', 'cuentica_purchase'] as $billingReason)
        {
            $stats = $this->importCuenticaBillingReasonRows($team, $billingReason, $limitPerKind);
            $imported += $stats['imported'];
            $updated += $stats['updated'];
            $skipped += $stats['skipped'];
        }

        return compact('imported', 'updated', 'skipped');
    }

    /**
     * @return array{imported: int, updated: int, skipped: int}
     */
    private function importCuenticaBillingReasonRows(Team $team, string $billingReason, int $limit): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $rows = InvoiceSync::query()
            ->where('team_id', $team->id)
            ->where('provider', 'cuentica')
            ->where('billing_reason', $billingReason)
            ->orderByRaw('invoice_created_at IS NULL')
            ->orderBy('invoice_created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row)
        {
            if (! $row instanceof InvoiceSync)
            {
                continue;
            }

            $existing = Invoice::withoutGlobalScopes()
                ->where('source_provider', 'cuentica')
                ->where('source_reference_id', $row->external_id)
                ->exists();

            $invoice = $this->cuenticaImportService->importFromSyncRow(
                $row,
                fallbackTaxId: true,
                fallbackEmail: true,
                linkCodeOnMatch: true,
                autoCreateCounterparty: true,
            );

            if (! $invoice)
            {
                $skipped++;

                continue;
            }

            if ($existing)
            {
                $updated++;
            } else
            {
                $imported++;
            }
        }

        return compact('imported', 'updated', 'skipped');
    }

    /**
     * @param  list<string>  $providerKeys
     */
    public function providerLabels(array $providerKeys): string
    {
        return collect($providerKeys)
            ->map(fn (string $key): string => (string) __('invoice_sync.providers.'.$key))
            ->implode(', ');
    }

    private function teamHasStripeInbound(Team $team): bool
    {
        return trim((string) $team->getSetting('stripe_secret', '')) !== '';
    }

    private function teamHasCuenticaInbound(Team $team): bool
    {
        if (! config('fiscal.platforms.cuentica.inbound_sync.enabled', true))
        {
            return false;
        }

        if (! (bool) $team->getSetting('cuentica_inbound_sync_enabled', true))
        {
            return false;
        }

        return trim((string) $team->getSetting('cuentica_api_token', '')) !== '';
    }
}
