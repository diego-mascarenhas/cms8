<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Module;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseMovementsApiTest extends TestCase
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

    public function test_movements_returns_invoice_items_ordered_by_date_with_categories(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplier = $this->createEnterpriseForTeam($user, 'Proveedor Alpha', 2);
        $client = $this->createEnterpriseForTeam($user, 'Cliente Beta', 1);
        $expenseCategory = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Hosting',
        ]);
        $incomeCategory = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Servicios',
        ]);

        $expenseInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'GC-100',
            'date' => '2026-04-10',
            'due_date' => '2026-04-10',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $expenseInvoice->id,
            'category_id' => $expenseCategory->id,
            'description' => 'Servidor cloud',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $incomeInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'FV-200',
            'date' => '2026-06-15',
            'due_date' => '2026-06-15',
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 242,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_movements_sell',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $teamId,
            'provider' => 'stripe',
            'external_id' => 'in_movements_sell',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_test/movements',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $incomeInvoice->id,
            'category_id' => $incomeCategory->id,
            'description' => 'Desarrollo web',
            'quantity' => 1,
            'unit_price' => 200,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $token = $user->createToken('movements-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=q:2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vat_year', 2026)
            ->assertJsonPath('vat_period', 'q:2')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.operation', 'sell')
            ->assertJsonPath('data.data.0.description', 'Desarrollo web')
            ->assertJsonPath('data.data.0.category_name', 'Servicios')
            ->assertJsonPath('data.data.0.enterprise_name', 'Cliente Beta')
            ->assertJsonPath('data.data.0.invoice_url', 'https://invoice.stripe.com/i/acct_test/movements')
            ->assertJsonPath('data.data.1.operation', 'buy')
            ->assertJsonPath('data.data.1.category_name', 'Hosting')
            ->assertJsonPath('data.data.1.signed_amount', -121)
            ->assertJsonPath('data.data.1.invoice_url', null)
            ->assertJsonPath('data.data.1.invoice_id', $expenseInvoice->id);
    }

    public function test_movements_can_filter_by_operation_and_search(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplier = $this->createEnterpriseForTeam($user, 'Proveedor Filtrado', 2);
        $category = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Software',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'GC-SEARCH',
            'date' => '2026-01-20',
            'due_date' => '2026-01-20',
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
            'category_id' => $category->id,
            'description' => 'Licencia anual Administración',
            'quantity' => 1,
            'unit_price' => 50,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $token = $user->createToken('movements-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:1&operation=buy&search=Licencia')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vat_period', 'm:1')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.description', 'Licencia anual Administración');

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:1&operation=buy&search=licencia')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:1&operation=buy&search=administracion')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:1&operation=buy&search=proveedor')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.enterprise_name', 'Proveedor Filtrado');

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:1&operation=sell')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:1&category_id='.$category->id)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.category_id', $category->id);

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:1&uncategorized=1')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:2')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_movements_include_reporting_amount_for_foreign_currency(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplier = $this->createEnterpriseForTeam($user, 'Proveedor ARS', 2);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate' => 1000,
            'date' => '2026-04-01',
            'fetched_at' => now(),
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => '2026-04-01',
            'fetched_at' => now(),
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'FV-ARS',
            'date' => '2026-04-10',
            'due_date' => '2026-04-10',
            'gross_amount' => 10000,
            'discount' => 0,
            'total_amount' => 10000,
            'balance' => 0,
            'currency_id' => 32,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => null,
            'description' => '1 × Hosting DEMO.COM (at $10,000.00 / month)',
            'quantity' => 1,
            'unit_price' => 10000,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $token = $user->createToken('movements-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:4')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reporting_currency', 'EUR')
            ->assertJsonPath('data.data.0.currency_code', 'ARS')
            ->assertJsonPath('data.data.0.amount', 10000)
            ->assertJsonPath('data.data.0.reporting_currency', 'EUR')
            ->assertJsonPath('data.data.0.reporting_amount', 9);
    }

    public function test_movements_prefer_payment_settlement_rate_over_frankfurter(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplier = $this->createEnterpriseForTeam($user, 'Proveedor USD', 2);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.89509,
            'date' => '2026-10-31',
            'fetched_at' => now(),
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => '1F3AD340-0024',
            'date' => '2026-10-10',
            'due_date' => '2026-10-10',
            'gross_amount' => 39,
            'discount' => 0,
            'total_amount' => 39,
            'balance' => 0,
            'currency_id' => 840,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => null,
            'description' => 'Suscripción USD',
            'quantity' => 1,
            'unit_price' => 39,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $eurAccount = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'bank-eur',
            'name' => 'Cuenta EUR',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $paymentType = PaymentType::query()->firstOrCreate(['name' => 'Transfer']);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'transaction_type' => 'expense',
            'date' => '2026-10-12',
            'invoice_id' => $invoice->id,
            'account_id' => $eurAccount->id,
            'type_id' => $paymentType->id,
            'amount' => 39.87,
            'document_amount' => 39,
            'exchange_rate' => round(39.87 / 39, 6),
            'status' => 2,
        ]);

        $token = $user->createToken('movements-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/movements?vat_year=2026&vat_period=m:10&operation=buy')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.currency_code', 'USD')
            ->assertJsonPath('data.data.0.amount', 39)
            ->assertJsonPath('data.data.0.reporting_amount', 39.87);
    }

    public function test_category_charts_return_income_and_expense_breakdown(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplier = $this->createEnterpriseForTeam($user, 'Proveedor Charts', 2);
        $client = $this->createEnterpriseForTeam($user, 'Cliente Charts', 1);
        $expenseCategory = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Hosting Charts',
        ]);
        $incomeCategory = Category::factory()->create([
            'team_id' => $teamId,
            'name' => 'Servicios Charts',
        ]);

        $expenseInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'GC-CHART',
            'date' => '2026-03-10',
            'due_date' => '2026-03-10',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $expenseInvoice->id,
            'category_id' => $expenseCategory->id,
            'description' => 'Servidor',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $incomeInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'FV-CHART',
            'date' => '2026-03-12',
            'due_date' => '2026-03-12',
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $incomeInvoice->id,
            'category_id' => $incomeCategory->id,
            'description' => 'Proyecto',
            'quantity' => 1,
            'unit_price' => 200,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $token = $user->createToken('movements-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/category-charts?vat_year=2026&vat_period=m:3')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vat_year', 2026)
            ->assertJsonPath('vat_period', 'm:3')
            ->assertJsonPath('income_categories.0.name', 'Servicios Charts')
            ->assertJsonPath('income_categories.0.total', 200)
            ->assertJsonPath('expense_categories.0.name', 'Hosting Charts')
            ->assertJsonPath('expense_categories.0.total', 100);
    }

    public function test_api_can_update_invoice_item_category(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $supplier = $this->createEnterpriseForTeam($user, 'Proveedor Cat', 2);
        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-server',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);
        $category = Category::factory()->create([
            'team_id' => $teamId,
            'module_id' => $module->id,
            'name' => 'Infra',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'GC-CAT',
            'date' => '2026-02-01',
            'due_date' => '2026-02-01',
            'gross_amount' => 10,
            'discount' => 0,
            'total_amount' => 10,
            'balance' => 0,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => null,
            'description' => 'VPS',
            'quantity' => 1,
            'unit_price' => 10,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $token = $user->createToken('movements-api')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/invoice-items/'.$item->id.'/category', [
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('category_id', $category->id)
            ->assertJsonPath('category_name', 'Infra');
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createEnterpriseForTeam(User $user, string $name, int $typeId): Enterprise
    {
        return Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => $typeId,
            'status_id' => 1,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@test.test',
        ]);
    }
}
