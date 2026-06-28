<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Finance\InvoiceAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        $this->service = app(InvoiceAnalyticsService::class);
    }

    public function test_build_year_report_aggregates_by_operation_and_category(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $incomeCategory = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Hosting sales',
        ]);
        $expenseCategory = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Infrastructure',
        ]);

        $year = 2024;

        $sellInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'S-001',
            'date' => Carbon::create($year, 3, 15)->toDateString(),
            'due_date' => Carbon::create($year, 3, 30),
            'gross_amount' => 1000,
            'discount' => 0,
            'total_amount' => 1000,
            'balance' => 0,
            'status' => 2,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $sellInvoice->id,
            'category_id' => $incomeCategory->id,
            'description' => 'Plan A',
            'quantity' => 1,
            'unit_price' => 1000,
            'discount' => 0,
        ]);

        $buyInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'B-001',
            'date' => Carbon::create($year, 3, 20)->toDateString(),
            'due_date' => Carbon::create($year, 3, 30),
            'gross_amount' => 400,
            'discount' => 50,
            'total_amount' => 350,
            'balance' => 0,
            'status' => 2,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $buyInvoice->id,
            'category_id' => $expenseCategory->id,
            'description' => 'Server',
            'quantity' => 1,
            'unit_price' => 400,
            'discount' => 50,
        ]);

        $voidInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'S-VOID',
            'date' => Carbon::create($year, 4, 1)->toDateString(),
            'due_date' => Carbon::create($year, 4, 15),
            'gross_amount' => 9999,
            'discount' => 0,
            'total_amount' => 9999,
            'balance' => 0,
            'status' => 3,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $voidInvoice->id,
            'category_id' => $incomeCategory->id,
            'description' => 'Should be excluded',
            'quantity' => 1,
            'unit_price' => 9999,
            'discount' => 0,
        ]);

        $this->assertDatabaseCount('invoices', 3);
        $this->assertDatabaseCount('invoice_items', 3);

        $report = $this->service->buildYearReport($team->id, $year);

        $this->assertSame($year, $report['year']);
        $this->assertEqualsWithDelta(1000.0, $report['summary']['income'], 0.01);
        $this->assertEqualsWithDelta(350.0, $report['summary']['expense'], 0.01);
        $this->assertEqualsWithDelta(650.0, $report['summary']['profit'], 0.01);
        $this->assertSame('Hosting sales', $report['income_categories'][0]['name']);
        $this->assertSame('Infrastructure', $report['expense_categories'][0]['name']);
        $this->assertEqualsWithDelta(100.0, $report['income_categories'][0]['share_percent'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $report['monthly_trend'][2]['income'], 0.01);
        $this->assertEqualsWithDelta(350.0, $report['monthly_trend'][2]['expense'], 0.01);
    }

    public function test_build_year_report_converts_invoice_lines_to_team_reporting_currency(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->setSetting('finance_reporting_currency', 'EUR', ['group' => 'finance']);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => '2024-06-30',
            'fetched_at' => now(),
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $incomeCategory = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Services',
        ]);

        $year = 2024;

        $eurInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'currency_id' => 978,
            'number' => 'S-EUR',
            'date' => Carbon::create($year, 6, 10)->toDateString(),
            'due_date' => Carbon::create($year, 6, 30),
            'gross_amount' => 1000,
            'discount' => 0,
            'total_amount' => 1000,
            'balance' => 0,
            'status' => 2,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $eurInvoice->id,
            'category_id' => $incomeCategory->id,
            'description' => 'EUR sale',
            'quantity' => 1,
            'unit_price' => 1000,
            'discount' => 0,
        ]);

        $usdInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'currency_id' => 840,
            'number' => 'S-USD',
            'date' => Carbon::create($year, 6, 15)->toDateString(),
            'due_date' => Carbon::create($year, 6, 30),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $usdInvoice->id,
            'category_id' => $incomeCategory->id,
            'description' => 'USD sale',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
        ]);

        $report = $this->service->buildYearReport($team->id, $year);

        $this->assertSame('EUR', $report['reporting_currency']);
        $this->assertEqualsWithDelta(1090.0, $report['summary']['income'], 0.01);
    }

    public function test_build_year_report_keeps_native_totals_when_exchange_rate_is_missing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->setSetting('finance_reporting_currency', 'USD', ['group' => 'finance']);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $incomeCategory = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Services',
        ]);

        $year = 2024;

        $eurInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'currency_id' => 978,
            'number' => 'S-EUR',
            'date' => Carbon::create($year, 6, 10)->toDateString(),
            'due_date' => Carbon::create($year, 6, 30),
            'gross_amount' => 1000,
            'discount' => 0,
            'total_amount' => 1000,
            'balance' => 0,
            'status' => 2,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $eurInvoice->id,
            'category_id' => $incomeCategory->id,
            'description' => 'EUR sale',
            'quantity' => 1,
            'unit_price' => 1000,
            'discount' => 0,
        ]);

        $report = $this->service->buildYearReport($team->id, $year);

        $this->assertFalse($report['conversion']['complete']);
        $this->assertContains('EUR->USD', $report['conversion']['missing_pairs']);
        $this->assertEqualsWithDelta(0.0, $report['summary']['income'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $report['conversion']['native_totals']['income']['EUR'], 0.01);
    }

    public function test_resolve_year_bounds_uses_invoice_dates(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'S-OLD',
            'date' => '2018-06-01',
            'due_date' => Carbon::create(2018, 6, 15),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        $this->assertDatabaseHas('invoices', [
            'team_id' => $team->id,
            'number' => 'S-OLD',
        ]);

        $storedDate = Invoice::withoutGlobalScopes()->where('number', 'S-OLD')->value('date');
        $this->assertSame('2018-06-01', Carbon::parse($storedDate)->toDateString());

        $bounds = $this->service->resolveYearBounds($team->id);

        $this->assertSame(2018, $bounds['min']);
        $this->assertSame(2018, $bounds['max']);
    }
}
