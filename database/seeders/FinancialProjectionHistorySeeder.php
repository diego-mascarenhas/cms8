<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Services\DefaultAssistantFlowPromptsService;
use App\Services\Finance\FinancialProjectionHistoryGenerator;
use Illuminate\Database\Seeder;

/**
 * Seeds ~10 years of categorized invoice lines (income + expenses) for the financial projection report.
 *
 * Usage:
 *   php artisan db:seed --class=FinancialProjectionHistorySeeder
 *   php artisan db:seed --class=FinancialProjectionHistorySeeder --force  # with TEAM_ID env
 *
 * Options via environment:
 *   FINANCIAL_HISTORY_TEAM_ID=1
 *   FINANCIAL_HISTORY_YEARS=10
 *   FINANCIAL_HISTORY_FRESH=1   # delete prior HIST-* invoices for the team first
 */
class FinancialProjectionHistorySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $teamId = (int) (env('FINANCIAL_HISTORY_TEAM_ID') ?: Team::query()->orderBy('id')->value('id') ?: 1);
        $years = (int) (env('FINANCIAL_HISTORY_YEARS') ?: 10);
        $fresh = filter_var(env('FINANCIAL_HISTORY_FRESH', true), FILTER_VALIDATE_BOOL);

        $team = Team::query()->find($teamId);
        if ($team === null)
        {
            $this->command?->error("Team id {$teamId} not found.");

            return;
        }

        $this->command?->info("Seeding {$years} years of financial projection data for team #{$teamId} ({$team->name})...");

        DefaultAssistantFlowPromptsService::syncForTeam($teamId);

        $stats = app(FinancialProjectionHistoryGenerator::class)->seedForTeam($team, $years, $fresh);

        $this->command?->info(sprintf(
            'Done. Years %d–%d: %d invoices, %d line items, %d payments (prefix %s).',
            $stats['start_year'],
            $stats['end_year'],
            $stats['invoices'],
            $stats['items'],
            $stats['payments'],
            'HIST-',
        ));

        $this->command?->info('Open /finance-dashboard/projection to review the report.');
    }
}
