<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceDashboardInvoicedLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoiced_lines_page_lists_all_lines_for_operation_and_period(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->setSetting('finance_reporting_currency', 'EUR', ['group' => 'finance']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Finance',
            'key' => 'finance-'.uniqid(),
            'icon' => 'ti-coin',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'S-001',
            'date' => Carbon::create(2026, 4, 10)->toDateString(),
            'due_date' => Carbon::create(2026, 4, 30),
            'gross_amount' => 500,
            'discount' => 0,
            'total_amount' => 500,
            'balance' => 0,
            'status' => 2,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $category->id,
            'description' => 'Annual plan',
            'quantity' => 1,
            'unit_price' => 500,
            'discount' => 0,
        ]);

        $backUrl = route('finance-dashboard.projection', ['year' => 2026]);

        $response = $this->actingAs($user)
            ->get(route('finance-dashboard.invoiced-lines', [
                'operation' => 'sell',
                'year' => 2026,
                'month' => 4,
                'return' => $backUrl,
            ]));

        $response->assertOk();
        $response->assertSee('Todas las líneas de ingreso facturadas');
        $response->assertSee('Annual plan');
        $response->assertSee('Hosting');
        $response->assertSee('Acme SL');
        $response->assertSee('S-001');
        $response->assertSee(route('client.show', $enterprise->id), false);
        $response->assertSee(route('invoice.show', $invoice->id), false);
        $response->assertSee('500 EUR', false);
        $response->assertSee('EUR');
        $response->assertDontSee('10/04/2026');
        $response->assertSee($backUrl, false);
    }

    public function test_invoiced_lines_page_filters_by_month(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Finance',
            'key' => 'finance-'.uniqid(),
            'icon' => 'ti-coin',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Consulting',
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Beta Corp',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        foreach ([2, 5] as $month)
        {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => 'buy',
                'number' => "B-00{$month}",
                'date' => Carbon::create(2026, $month, 5)->toDateString(),
                'due_date' => Carbon::create(2026, $month, 28),
                'gross_amount' => 200 * $month,
                'discount' => 0,
                'total_amount' => 200 * $month,
                'balance' => 0,
                'status' => 2,
            ]);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => $category->id,
                'description' => "Expense {$month}",
                'quantity' => 1,
                'unit_price' => 200 * $month,
                'discount' => 0,
            ]);
        }

        $this->actingAs($user)
            ->get(route('finance-dashboard.invoiced-lines', [
                'operation' => 'buy',
                'year' => 2026,
                'month' => 2,
            ]))
            ->assertOk()
            ->assertSee('Expense 2')
            ->assertDontSee('Expense 5');
    }

    public function test_invoiced_lines_page_filters_uncategorized_only(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Finance',
            'key' => 'finance-'.uniqid(),
            'icon' => 'ti-coin',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Gamma SA',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $categorizedInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'B-100',
            'date' => Carbon::create(2026, 3, 10)->toDateString(),
            'due_date' => Carbon::create(2026, 3, 30),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $categorizedInvoice->id,
            'category_id' => $category->id,
            'description' => 'Categorized expense',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
        ]);

        $uncategorizedInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'B-101',
            'date' => Carbon::create(2026, 3, 12)->toDateString(),
            'due_date' => Carbon::create(2026, 3, 30),
            'gross_amount' => 250,
            'discount' => 0,
            'total_amount' => 250,
            'balance' => 0,
            'status' => 2,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $uncategorizedInvoice->id,
            'category_id' => null,
            'description' => 'Uncategorized expense',
            'quantity' => 1,
            'unit_price' => 250,
            'discount' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('finance-dashboard.invoiced-lines', [
                'operation' => 'buy',
                'year' => 2026,
                'month' => 0,
                'uncategorized' => 1,
            ]))
            ->assertOk()
            ->assertSee('Líneas de gasto sin categoría')
            ->assertSee('Uncategorized expense')
            ->assertDontSee('Categorized expense')
            ->assertSee('name="uncategorized"', false)
            ->assertSee('value="1"', false)
            ->assertSee('line-category-badge', false);
    }
}
