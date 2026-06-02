<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\FinancialProjectionHistoryGenerator;
use App\Services\Finance\InvoiceAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialProjectionHistoryGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
        ]);
    }

    public function test_seeds_one_year_of_categorized_invoices(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $generator = app(FinancialProjectionHistoryGenerator::class);
        $stats = $generator->seedForTeam($team, 2, true);

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

        $this->assertSame($stats['invoices'], $stats['payments']);
        $payments = Payment::withoutGlobalScopes()->where('team_id', $team->id);
        $this->assertGreaterThan(0, (clone $payments)->where('status', 2)->count());
        $this->assertGreaterThan(0, (clone $payments)->where('status', 3)->count());
        $this->assertGreaterThan(0, (clone $payments)->where('status', 4)->count());

        $totalPayments = (clone $payments)->count();
        $rejectedShare = (clone $payments)->where('status', 4)->count() / $totalPayments;
        $this->assertGreaterThan(0.005, $rejectedShare);
        $this->assertLessThan(0.04, $rejectedShare);
    }
}
