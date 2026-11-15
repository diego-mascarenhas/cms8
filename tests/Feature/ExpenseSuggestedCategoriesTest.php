<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseSuggestedCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
    }

    public function test_suggested_categories_returns_previous_invoice_item_categories(): void
    {
        $user = $this->makeAdminUser();
        $supplier = $this->createSupplierForTeam($user);
        $software = Category::factory()->create([
            'team_id' => (int) $user->current_team_id,
            'name' => 'Software',
            'status' => 1,
        ]);
        $hosting = Category::factory()->create([
            'team_id' => (int) $user->current_team_id,
            'name' => 'Hosting',
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'FAC-PREV-001',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $software->id,
            'description' => 'Licencia SaaS',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $hosting->id,
            'description' => 'Servidor cloud',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $response = $this->actingAs($user)->getJson(route('expense.suggested-categories', [
            'enterprise_id' => $supplier->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice.number', 'FAC-PREV-001')
            ->assertJsonPath('items.0.category_id', $software->id)
            ->assertJsonPath('items.0.category_name', 'Software')
            ->assertJsonPath('items.1.category_id', $hosting->id)
            ->assertJsonPath('items.1.category_name', 'Hosting');
    }

    public function test_suggested_categories_returns_empty_when_no_previous_invoice(): void
    {
        $user = $this->makeAdminUser();
        $supplier = $this->createSupplierForTeam($user);

        $this->actingAs($user)
            ->getJson(route('expense.suggested-categories', [
                'enterprise_id' => $supplier->id,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice', null)
            ->assertJsonPath('items', []);
    }

    public function test_suggested_categories_falls_back_to_last_team_expense_item(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplierWithHistory = $this->createSupplierForTeam($user);
        $otherSupplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Otro proveedor',
            'email' => 'otro@test.test',
        ]);
        $software = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Software',
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplierWithHistory->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'FAC-FALLBACK-001',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $software->id,
            'description' => 'Licencia',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $this->actingAs($user)
            ->getJson(route('expense.suggested-categories', [
                'enterprise_id' => $otherSupplier->id,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice', null)
            ->assertJsonPath('items.0.category_id', $software->id)
            ->assertJsonPath('items.0.source', 'last_item');
    }

    public function test_expense_form_api_defaults_category_from_last_item(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplier = $this->createSupplierForTeam($user);
        $software = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Software',
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'FAC-DEFAULT-CAT',
            'date' => '2026-07-01',
            'due_date' => '2026-07-01',
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $software->id,
            'description' => 'SaaS',
            'quantity' => 1,
            'unit_price' => 50,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $token = $user->createToken('expense-category-default')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/form')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('default_category_id', $software->id)
            ->assertJsonPath('default_category_source', 'last_item');
    }

    public function test_expense_form_api_defaults_category_from_request_context(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $hosting = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Hosting',
            'status' => 1,
        ]);

        $token = $user->createToken('expense-category-context')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/form?category_id='.$hosting->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('default_category_id', $hosting->id)
            ->assertJsonPath('default_category_source', 'context');
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createSupplierForTeam(User $user): Enterprise
    {
        return Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Proveedor de prueba',
            'email' => 'proveedor@test.test',
        ]);
    }
}
