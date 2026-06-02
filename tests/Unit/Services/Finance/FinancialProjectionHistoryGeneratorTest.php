<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\FinancialProjectionHistoryGenerator;
use App\Services\Finance\InvoiceAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialProjectionHistoryGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
    }

    public function test_seeds_one_year_of_categorized_invoices(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $generator = app(FinancialProjectionHistoryGenerator::class);
        $stats = $generator->seedForTeam($team, 1, true);

        $this->assertGreaterThan(0, $stats['invoices']);
        $this->assertGreaterThan(20, $stats['invoices']);
        $this->assertGreaterThan($stats['invoices'], $stats['items']);

        $bounds = app(InvoiceAnalyticsService::class)->resolveYearBounds($team->id);
        $this->assertSame((int) Carbon::now()->year, $bounds['max']);

        $report = app(InvoiceAnalyticsService::class)->buildYearReport($team->id, (int) Carbon::now()->year);
        $this->assertGreaterThan(0, $report['summary']['income']);
        $this->assertGreaterThan(0, $report['summary']['expense']);
        $this->assertNotEmpty($report['income_categories']);
        $this->assertNotEmpty($report['expense_categories']);

        $histCount = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('number', 'like', 'HIST-%')
            ->count();
        $this->assertSame($stats['invoices'], $histCount);
    }
}
