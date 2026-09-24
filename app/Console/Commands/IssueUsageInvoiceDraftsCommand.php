<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Billing\TeamUsageInvoiceDraftIssuer;
use Illuminate\Console\Command;

class IssueUsageInvoiceDraftsCommand extends Command
{
    protected $signature = 'billing:issue-usage-invoice-drafts
                            {--team= : Only this team id}
                            {--dry-run : List drafts that would be created}';

    protected $description = 'Create Stripe draft invoices for closed team usage periods';

    public function handle(TeamUsageInvoiceDraftIssuer $issuer): int
    {
        $teamOption = $this->option('team');
        $team = null;
        if (is_string($teamOption) && trim($teamOption) !== '')
        {
            $team = Team::query()->find((int) $teamOption);
            if (! $team)
            {
                $this->error('Team '.(int) $teamOption.' was not found.');

                return self::FAILURE;
            }
        }

        $results = $issuer->issueDueDrafts($team, (bool) $this->option('dry-run'));

        if ($results->isEmpty())
        {
            $this->info('No closed usage periods to draft.');

            return self::SUCCESS;
        }

        $this->table(
            ['Team', 'Kind', 'From', 'To', 'Cents', 'Lines', 'Status', 'Stripe'],
            $results->map(fn (array $row): array => [
                $row['team_id'],
                $row['kind'],
                $row['period_from'],
                $row['period_to'],
                $row['billed_cents'],
                $row['lines'],
                $row['status'],
                $row['stripe_invoice_id'] ?? '',
            ])->all(),
        );

        foreach ($results as $row)
        {
            if (($row['status'] ?? '') === 'error' && filled($row['error'] ?? null))
            {
                $this->error('Team '.$row['team_id'].': '.$row['error']);
            }
        }

        return $results->contains(fn (array $row): bool => $row['status'] === 'error')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
