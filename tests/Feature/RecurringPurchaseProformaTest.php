<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Finance\InvoiceAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecurringPurchaseProformaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_recurring_purchase_is_a_proforma_until_the_supplier_invoice_arrives(): void
    {
        Carbon::setTestNow('2026-11-23 09:00:00');
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor Hosting',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $category = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Hosting',
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
            'operation' => 'buy',
            'description' => 'Servidor mensual',
            'currency_id' => 978,
            'price' => 50,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => '2026-12-15',
            'status' => 4,
        ]);

        $projection = $this->projection($team);

        $this->assertEqualsWithDelta(50.0, $projection['points'][4]['expense'], 0.01);
        $this->assertEqualsWithDelta(550.0, $projection['contracted_expense'], 0.01);

        $breakdown = (new FinanceCfoBriefService($this->analytics()))->projectionMonthBreakdown($team, '2026-12');

        $this->assertSame('Compra recurrente (proforma)', $breakdown['items'][0]['source_label']);
        $this->assertSame('Proveedor Hosting — Hosting', $breakdown['items'][0]['label']);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'F-12',
            'date' => '2026-12-10',
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $category->id,
            'description' => 'Hosting diciembre',
            'quantity' => 1,
            'unit_price' => 50,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $afterInvoice = $this->projection($team);

        $this->assertEqualsWithDelta(0.0, $afterInvoice['points'][4]['expense'], 0.01);
        $this->assertEqualsWithDelta(500.0, $afterInvoice['contracted_expense'], 0.01);
    }

    public function test_a_recurring_purchase_requires_a_category(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor Hosting',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('service.create'))
            ->assertOk()
            ->assertSee('A recurring purchase is a proforma until the supplier invoice for that month is recorded.', false);

        $this->actingAs($user)
            ->post(route('service.store'), [
                'enterprise_id' => $enterprise->id,
                'operation' => 'buy',
                'price' => 50,
                'frequency' => 1,
                'next_billing' => '2026-12-15',
                'status' => 4,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('services', 0);
    }

    private function projection(Team $team): array
    {
        return (new FinanceCfoBriefService($this->analytics()))->projection($team, 2026);
    }

    private function analytics(): InvoiceAnalyticsService
    {
        $trend = [];

        foreach (range(1, 12) as $month)
        {
            $trend[] = [
                'label' => 'M',
                'month' => $month,
                'income' => 0.0,
                'expense' => 0.0,
                'profit' => 0.0,
            ];
        }

        $analytics = $this->createMock(InvoiceAnalyticsService::class);
        $analytics->method('buildYearReport')->willReturn([
            'year' => 2026,
            'reporting_currency' => 'EUR',
            'monthly_trend' => $trend,
            'scenario' => [
                'avg_monthly_income' => 0,
                'avg_monthly_expense' => 0,
                'avg_monthly_profit' => 0,
            ],
        ]);

        return $analytics;
    }
}
