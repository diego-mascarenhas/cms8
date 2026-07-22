<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillStripeInvoiceFiscalDatesCommand extends Command
{
    protected $signature = 'invoices:backfill-stripe-fiscal-dates
                            {--team= : Limit to a team id}
                            {--dry-run : Show changes without saving}';

    protected $description = 'Set Stripe invoice fiscal dates from status_transitions.finalized_at when available';

    public function handle(): int
    {
        $teamId = $this->option('team') !== null ? (int) $this->option('team') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->whereNotNull('source_reference_id')
            ->orderBy('id');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        }

        $updated = 0;
        $checked = 0;

        $query->chunkById(200, function ($invoices) use ($dryRun, &$updated, &$checked)
        {
            $externalIds = $invoices->pluck('source_reference_id')->filter()->unique()->values();
            $syncs = InvoiceSync::query()
                ->where('provider', 'stripe')
                ->whereIn('external_id', $externalIds)
                ->get()
                ->keyBy('external_id');

            foreach ($invoices as $invoice)
            {
                $checked++;
                $sync = $syncs->get($invoice->source_reference_id);
                if (! $sync instanceof InvoiceSync)
                {
                    continue;
                }

                $finalizedAt = data_get($sync->raw_payload, 'status_transitions.finalized_at');
                if (! is_numeric($finalizedAt))
                {
                    continue;
                }

                $fiscalDate = Carbon::createFromTimestampUTC((int) $finalizedAt)
                    ->setTimezone(config('app.timezone'))
                    ->toDateString();

                $currentDate = $invoice->date
                    ? Carbon::parse($invoice->date)->toDateString()
                    : null;

                if ($currentDate === $fiscalDate)
                {
                    continue;
                }

                $this->line(sprintf(
                    '%s: %s → %s',
                    $invoice->number ?? $invoice->source_reference_id,
                    $currentDate ?? 'null',
                    $fiscalDate,
                ));

                if (! $dryRun)
                {
                    $invoice->forceFill(['date' => $fiscalDate])->save();
                }

                $updated++;
            }
        });

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} of {$checked} Stripe invoices.");

        return self::SUCCESS;
    }
}
