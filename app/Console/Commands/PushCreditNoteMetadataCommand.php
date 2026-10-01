<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Team;
use App\Services\Billing\StripeCreditNoteMetadataWriter;
use App\Services\Finance\CreditNoteNumberAllocator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class PushCreditNoteMetadataCommand extends Command
{
    protected $signature = 'invoices:push-credit-note-metadata
                            {--team_id= : Limit to one team}
                            {--from= : Only notes on or after this date (Y-m-d)}
                            {--to= : Only notes on or before this date (Y-m-d)}
                            {--dry-run : Preview without calling Stripe}';

    protected $description = 'Write Humano credit-note numbers into Stripe credit note metadata';

    public function __construct(
        private readonly StripeCreditNoteMetadataWriter $writer,
        private readonly CreditNoteNumberAllocator $allocator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $from = $this->option('from') !== null ? Carbon::parse((string) $this->option('from'))->startOfDay() : null;
        $to = $this->option('to') !== null ? Carbon::parse((string) $this->option('to'))->endOfDay() : null;

        if ($from instanceof Carbon && $to instanceof Carbon && $from->greaterThan($to))
        {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        $teams = Team::query()
            ->when($teamId, fn ($query) => $query->whereKey($teamId))
            ->orderBy('id')
            ->get();

        $pushed = 0;
        $failed = 0;

        foreach ($teams as $team)
        {
            $notes = $this->creditNotes((int) $team->id, $from, $to);
            if ($notes->isEmpty())
            {
                continue;
            }

            $client = null;
            if (! $dryRun)
            {
                $secret = trim((string) $team->getSetting('stripe_secret'));
                if ($secret === '' || ! str_starts_with($secret, 'sk_'))
                {
                    $this->warn("Team {$team->id}: missing stripe_secret, skipped ".count($notes).' notes.');

                    continue;
                }

                $client = new StripeClient($secret);
            }

            foreach ($notes as $creditNote)
            {
                $stripeId = (string) $creditNote->source_reference_id;
                if ($dryRun)
                {
                    $this->line("[dry-run] {$creditNote->number} → {$stripeId}");
                    $pushed++;

                    continue;
                }

                if ($this->writer->push($client, $creditNote))
                {
                    $this->line("{$creditNote->number} → {$stripeId}");
                    $pushed++;

                    continue;
                }

                $this->error("Failed {$creditNote->number} ({$stripeId})");
                $failed++;
            }
        }

        $this->info(
            "Pushed: {$pushed} | failed: {$failed}".
            ($dryRun ? ' | dry-run' : ''),
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    private function creditNotes(int $teamId, ?Carbon $from, ?Carbon $to)
    {
        $query = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('type_id', 2)
            ->where('source_reference_id', 'like', 'cn_%')
            ->orderBy('date')
            ->orderBy('id');

        if ($from instanceof Carbon)
        {
            $query->whereDate('date', '>=', $from->toDateString());
        }

        if ($to instanceof Carbon)
        {
            $query->whereDate('date', '<=', $to->toDateString());
        }

        return $query->get()->filter(function (Invoice $creditNote): bool
        {
            return $this->allocator->isHumanoCreditNoteNumber((string) $creditNote->number);
        })->values();
    }
}
