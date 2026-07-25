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

class CategoryItemsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_items_page_shows_enterprise_link_and_reporting_currency_without_line_description(): void
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
            'name' => 'Payroll & Benefits',
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Pine Labs SA',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'B-001',
            'date' => Carbon::create(2026, 3, 15)->toDateString(),
            'due_date' => Carbon::create(2026, 3, 30),
            'gross_amount' => 1825.46,
            'discount' => 0,
            'total_amount' => 1825.46,
            'balance' => 0,
            'status' => 2,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $category->id,
            'description' => 'Monthly payroll allocation',
            'quantity' => 1,
            'unit_price' => 1825.46,
            'discount' => 50,
        ]);

        $backUrl = route('finance-dashboard.index', ['year' => 2026]);

        $response = $this->actingAs($user)
            ->get(route('categories.items', [
                'id' => $category->id,
                'year' => 2026,
                'month' => 3,
                'return' => $backUrl,
            ]));

        $response->assertOk();
        $response->assertSee('Categoría/');
        $response->assertSee('Payroll &amp; Benefits', false);
        $response->assertSee('Líneas facturadas en esta categoría');
        $response->assertDontSee('Monthly payroll allocation');
        $response->assertSee('Pine Labs SA');
        $response->assertSee(route('client.show', $enterprise->id), false);
        $response->assertSee('<th>'.e(__('Enterprise')).'</th>', false);
        $response->assertSee('<th class="text-end">'.e(__('Total')).'</th>', false);
        $response->assertSee('Con descuento');
        $response->assertSee('1.775', false);
        $response->assertSee('EUR');
        $response->assertDontSee('15/03/2026');
        $response->assertSee(__('Back'));
        $response->assertSee($backUrl, false);
        $response->assertSee('name="month"', false);
        $response->assertSee('name="year"', false);
    }

    public function test_category_items_page_filters_by_month(): void
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
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        foreach ([3, 4] as $month)
        {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => 'sell',
                'number' => "S-00{$month}",
                'date' => Carbon::create(2026, $month, 10)->toDateString(),
                'due_date' => Carbon::create(2026, $month, 30),
                'gross_amount' => 100 * $month,
                'discount' => 0,
                'total_amount' => 100 * $month,
                'balance' => 0,
                'status' => 2,
            ]);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => $category->id,
                'description' => "Line month {$month}",
                'quantity' => 1,
                'unit_price' => 100 * $month,
                'discount' => 0,
            ]);
        }

        $this->actingAs($user)
            ->get(route('categories.items', ['id' => $category->id, 'year' => 2026, 'month' => 3]))
            ->assertOk()
            ->assertSee('300 EUR', false)
            ->assertDontSee('400 EUR', false);

        $this->actingAs($user)
            ->get(route('categories.items', ['id' => $category->id, 'year' => 2026, 'month' => 4]))
            ->assertOk()
            ->assertSee('400 EUR', false)
            ->assertDontSee('300 EUR', false);
    }

    public function test_category_items_page_filters_by_operation(): void
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
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        foreach ([['sell', 'S-001', 300], ['buy', 'B-001', 150]] as [$operation, $number, $amount])
        {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => $operation,
                'number' => $number,
                'date' => Carbon::create(2026, 3, 10)->toDateString(),
                'due_date' => Carbon::create(2026, 3, 30),
                'gross_amount' => $amount,
                'discount' => 0,
                'total_amount' => $amount,
                'balance' => 0,
                'status' => 2,
            ]);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => $category->id,
                'description' => "Line {$operation}",
                'quantity' => 1,
                'unit_price' => $amount,
                'discount' => 0,
            ]);
        }

        $this->actingAs($user)
            ->get(route('categories.items', [
                'id' => $category->id,
                'year' => 2026,
                'month' => 3,
                'operation' => 'sell',
            ]))
            ->assertOk()
            ->assertSee('300 EUR', false)
            ->assertDontSee('150 EUR', false)
            ->assertSee('name="operation"', false)
            ->assertSee('value="sell"', false);

        $this->actingAs($user)
            ->get(route('categories.items', [
                'id' => $category->id,
                'year' => 2026,
                'month' => 3,
                'operation' => 'buy',
            ]))
            ->assertOk()
            ->assertSee('150 EUR', false)
            ->assertDontSee('300 EUR', false)
            ->assertSee('value="buy"', false);
    }
}
