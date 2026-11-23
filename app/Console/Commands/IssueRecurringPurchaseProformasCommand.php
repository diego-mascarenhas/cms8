<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Finance\RecurringPurchaseProformaIssuer;
use Illuminate\Console\Command;

class IssueRecurringPurchaseProformasCommand extends Command
{
    protected $signature = 'purchases:issue-proformas
                            {--team= : Only this team id}
                            {--dry-run : List proformas that would be created}';

    protected $description = 'Create draft purchase invoices for due recurring services';

    public function handle(RecurringPurchaseProformaIssuer $issuer): int
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

        $issued = $issuer->issueDue($team, (bool) $this->option('dry-run'));

        if ($issued->isEmpty())
        {
            $this->info('No recurring purchases due for a proforma.');

            return self::SUCCESS;
        }

        $this->table(
            ['Invoice', 'Team', 'Number', 'Date', 'Status'],
            $issued->map(fn ($invoice): array => [
                $invoice->id ?? '',
                $invoice->team_id,
                $invoice->number,
                (string) $invoice->date,
                $invoice->status,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
