<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Billing\MercadoPagoSettlementPayerEnricher;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnrichMercadoPagoSettlementPayersCommand extends Command
{
    protected $signature = 'mercadopago:enrich-settlement-payers
                            {--team_id= : Enrich only one team}
                            {--recent-days=90 : Report window ending now, starting this many days ago}
                            {--from= : Report begin date (Y-m-d)}
                            {--to= : Report end date (Y-m-d)}
                            {--poll=90 : Seconds to wait for report generation}
                            {--dry-run : Parse report without writing}';

    protected $description = 'Enrich Mercado Pago payment_syncs with payer name/id from Account money settlement report';

    public function __construct(
        private readonly MercadoPagoSettlementPayerEnricher $enricher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $poll = max(15, (int) $this->option('poll'));
        [$begin, $end] = $this->resolveDateRange(
            $this->option('from'),
            $this->option('to'),
            max(1, (int) $this->option('recent-days')),
        );

        $teams = Team::query()->with('settings');
        if ($teamId)
        {
            $teams->whereKey($teamId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
        $teams = $teams->get();
        if ($teams->isEmpty())
        {
            $this->warn('No teams found.');

            return self::SUCCESS;
        }

        foreach ($teams as $team)
        {
            $token = trim((string) $team->getSetting('mercadopago_access_token'));
            if ($token === '')
            {
                $this->line("Skipping team {$team->id}: missing mercadopago_access_token.");

                continue;
            }

            $this->info("Team {$team->id}: requesting settlement report {$begin->toDateString()} → {$end->toDateString()}");

            try
            {
                $result = $this->enricher->enrichTeam($team, $begin, $end, $dryRun, $poll);
            } catch (\Throwable $e)
            {
                $this->error("Team {$team->id}: {$e->getMessage()}");

                continue;
            }

            $this->line(sprintf(
                'Team %d: enriched=%d report_rows=%d unmatched=%d skipped=%d chunks=%d%s',
                $team->id,
                $result['enriched'],
                $result['report_rows'],
                $result['unmatched'],
                $result['skipped'],
                $result['chunks'] ?? 1,
                $dryRun ? ' (dry-run)' : '',
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(?string $from, ?string $to, int $recentDays): array
    {
        $end = filled($to) ? Carbon::parse($to)->endOfDay() : now();
        $begin = filled($from)
            ? Carbon::parse($from)->startOfDay()
            : $end->clone()->subDays($recentDays)->startOfDay();

        return [$begin, $end];
    }
}
