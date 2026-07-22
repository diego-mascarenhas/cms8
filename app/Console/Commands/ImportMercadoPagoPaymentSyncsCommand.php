<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentSync;
use App\Services\Billing\MercadoPagoPaymentImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportMercadoPagoPaymentSyncsCommand extends Command
{
    protected $signature = 'payment-syncs:import-mercadopago
                            {--team_id= : Import only one team}
                            {--limit=500 : Max payment_syncs rows to process}
                            {--reconcile : Also update payments already linked to these sync rows}
                            {--fallback-email : Resolve enterprise by email when payer id/code does not match}
                            {--link-code-on-email-match : When fallback by email succeeds uniquely, write MP payer id into enterprises.code}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map Mercado Pago payment_syncs rows into core payments (idempotent by source reference)';

    public function __construct(
        private readonly MercadoPagoPaymentImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('payment_syncs'))
        {
            $this->error('Table payment_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $reconcile = (bool) $this->option('reconcile');
        $fallbackEmail = (bool) $this->option('fallback-email');
        $linkCodeOnEmailMatch = (bool) $this->option('link-code-on-email-match');

        $query = PaymentSync::query()->where('provider', 'mercadopago');

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
                $q->from('payments')
                    ->whereColumn('payments.team_id', 'payment_syncs.team_id')
                    ->where('payments.source_provider', 'mercadopago')
                    ->where(function ($inner)
                    {
                        $inner->whereColumn('payments.source_reference_id', 'payment_syncs.external_id')
                            ->orWhereRaw("payments.source_reference_id LIKE payment_syncs.external_id || ':%'");
                    });
            });
        }

        $query
            ->orderByRaw('charge_created_at IS NULL')
            ->orderBy('charge_created_at')
            ->orderBy('id');

        $rows = $query->limit($limit)->get();
        if ($rows->isEmpty())
        {
            $this->info('No Mercado Pago payment_syncs rows to import.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row)
        {
            $existing = Payment::withoutGlobalScopes()
                ->where('team_id', $row->team_id)
                ->where('source_provider', 'mercadopago')
                ->where('source_reference_id', $row->external_id)
                ->first();

            $payment = $this->importService->importFromPaymentSync(
                $row,
                $fallbackEmail,
                $linkCodeOnEmailMatch,
                $dryRun,
            );

            if ($payment === null && ! $dryRun)
            {
                $skipped++;

                continue;
            }

            if ($dryRun)
            {
                $this->line("[dry-run] would import payment_sync #{$row->id} ({$row->external_id})");
                $created++;

                continue;
            }

            if ($existing)
            {
                $updated++;
            } else
            {
                $created++;
            }
        }

        $this->info("Done. Created: {$created} | Updated: {$updated} | Skipped: {$skipped}".($dryRun ? ' | dry-run' : ''));

        return self::SUCCESS;
    }
}
