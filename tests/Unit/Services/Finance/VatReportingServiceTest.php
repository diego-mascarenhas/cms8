<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Team;
use App\Models\User;
use App\Services\Finance\VatReportingService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VatReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    private VatReportingService $service;

    private Team $team;

    private Enterprise $enterprise;

    private ?int $eurCurrencyId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $this->team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $this->team->id])->save();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $this->eurCurrencyId = Currency::query()->where('code', 'EUR')->value('id');
        $this->service = app(VatReportingService::class);
    }

    public function test_sums_multi_rate_income_vat_for_month(): void
    {
        $invoice = $this->createInvoice('sell', now()->toDateString(), 130, 151.5);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Service 21%',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Service 10%',
            'quantity' => 1,
            'unit_price' => 30,
            'discount' => 0,
            'tax_percentage' => 10,
        ]);

        $outside = $this->createInvoice('sell', now()->subMonths(2)->toDateString(), 100, 121);
        InvoiceItem::query()->create([
            'invoice_id' => $outside->id,
            'description' => 'Old',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $range = $this->service->currentMonthRange(now());
        $total = $this->service->sumIncomeVat($range['from'], $range['to'], 'EUR', $this->team->id);

        $this->assertSame(24.0, $total);
    }

    public function test_expense_fallback_uses_total_minus_gross_when_no_tax_percentage(): void
    {
        $this->createInvoice('buy', now()->toDateString(), 100, 121);

        $range = $this->service->currentMonthRange(now());
        $total = $this->service->sumExpenseVat($range['from'], $range['to'], 'EUR', $this->team->id);

        $this->assertSame(21.0, $total);
    }

    public function test_current_quarter_range_covers_three_months(): void
    {
        $range = $this->service->currentQuarterRange(Carbon::create(2026, 7, 15));

        $this->assertSame('Q3 2026', $range['label']);
        $this->assertSame('2026-07-01', $range['from']->toDateString());
        $this->assertSame('2026-09-30', $range['to']->toDateString());
    }

    public function test_previous_quarter_range_wraps_to_prior_year(): void
    {
        $range = $this->service->previousQuarterRange(Carbon::create(2026, 7, 15));

        $this->assertSame('Q2 2026', $range['label']);
        $this->assertSame('2026-04-01', $range['from']->toDateString());
        $this->assertSame('2026-06-30', $range['to']->toDateString());

        $wrap = $this->service->previousQuarterRange(Carbon::create(2026, 2, 1));
        $this->assertSame('Q4 2025', $wrap['label']);
    }

    public function test_resolve_selected_period_defaults_past_year_to_q4(): void
    {
        $period = $this->service->resolveSelectedPeriod(
            year: 2025,
            teamId: $this->team->id,
            now: Carbon::create(2026, 7, 15),
        );

        $this->assertSame(2025, $period['year']);
        $this->assertSame('quarter', $period['mode']);
        $this->assertSame('q:4', $period['period']);
        $this->assertSame('Q4 2025', $period['label']);
    }

    public function test_resolve_selected_period_accepts_month_token(): void
    {
        $period = $this->service->resolveSelectedPeriod(
            year: 2025,
            period: 'm:3',
            teamId: $this->team->id,
            now: Carbon::create(2026, 7, 15),
        );

        $this->assertSame('month', $period['mode']);
        $this->assertSame('m:3', $period['period']);
        $this->assertSame('2025-03-01', $period['range']['from']->toDateString());
        $this->assertSame('2025-03-31', $period['range']['to']->toDateString());
    }

    public function test_previous_comparable_range_for_month_and_quarter(): void
    {
        $month = $this->service->resolveSelectedPeriod(
            year: 2025,
            period: 'm:3',
            teamId: $this->team->id,
            now: Carbon::create(2026, 7, 15),
        );
        $previousMonth = $this->service->previousComparableRange($month);
        $this->assertSame('2025-02-01', $previousMonth['from']->toDateString());
        $this->assertSame('2025-02-28', $previousMonth['to']->toDateString());

        $quarter = $this->service->resolveSelectedPeriod(
            year: 2026,
            period: 'q:2',
            teamId: $this->team->id,
            now: Carbon::create(2026, 7, 15),
        );
        $previousQuarter = $this->service->previousComparableRange($quarter);
        $this->assertSame('2026-01-01', $previousQuarter['from']->toDateString());
        $this->assertSame('2026-03-31', $previousQuarter['to']->toDateString());
    }

    public function test_converts_vat_to_reporting_currency(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => now()->toDateString(),
            'fetched_at' => now(),
        ]);

        $usdId = Currency::query()->where('code', 'USD')->value('id');
        $invoice = $this->createInvoice('sell', now()->toDateString(), 100, 121, $usdId);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'USD service',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $range = $this->service->currentMonthRange(now());
        $total = $this->service->sumIncomeVat($range['from'], $range['to'], 'EUR', $this->team->id);

        $this->assertSame(18.9, $total);
    }

    private function createInvoice(
        string $operation,
        string $date,
        float $gross,
        float $total,
        ?int $currencyId = null,
    ): Invoice {
        return Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => $operation,
            'number' => 'INV-'.uniqid(),
            'date' => $date,
            'gross_amount' => $gross,
            'total_amount' => $total,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $currencyId ?? $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);
    }
}
