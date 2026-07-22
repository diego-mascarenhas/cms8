<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VatHaciendaCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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
        Permission::firstOrCreate(['name' => 'payment.list', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->user->forceFill(['current_team_id' => $this->user->ownedTeams()->first()->id])->save();
        $this->user->assignRole('admin');
        $this->user->givePermissionTo('payment.list');
        $this->actingAs($this->user);

        $this->enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Cliente Hacienda SL',
            'type_id' => 1,
            'status_id' => 1,
            'country' => 'ES',
        ]);

        $this->eurCurrencyId = Currency::query()->where('code', 'EUR')->value('id');
    }

    public function test_income_export_downloads_csv_for_selected_period(): void
    {
        $this->createInvoice('sell', '2024-05-10', 100, 121, 'INV-IN-001');
        $this->createInvoice('sell', '2024-06-10', 200, 242, 'INV-IN-002');

        $response = $this->get(route('income.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Comprobante', $csv);
        $this->assertStringContainsString('Razón Social', $csv);
        $this->assertStringContainsString('INV-IN-001', $csv);
        $this->assertStringContainsString('Cliente Hacienda SL', $csv);
        $this->assertStringContainsString('121,00', $csv);
        $this->assertStringContainsString('21,00', $csv);
        $this->assertStringNotContainsString('INV-IN-002', $csv);
        $this->assertStringContainsString('TOTALES', $csv);
        $this->assertStringContainsString('1 registros', $csv);
    }

    public function test_expense_export_downloads_csv_for_selected_period(): void
    {
        $this->createInvoice('buy', '2024-05-15', 50, 60.5, 'INV-EX-001');

        $response = $this->get(route('expense.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('INV-EX-001', $csv);
        $this->assertStringContainsString('60,50', $csv);
        $this->assertStringContainsString('TOTALES', $csv);
    }

    public function test_export_shows_exchange_rate_for_foreign_currency_using_invoice_date(): void
    {
        $usdId = Currency::query()->where('code', 'USD')->value('id');

        \App\Models\ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => '2024-05-01',
            'fetched_at' => now(),
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'INV-USD-001',
            'date' => '2024-05-12',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $usdId,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'USD line',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $csv = $this->get(route('income.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        // Cambio shows EUR→USD (1 / 0.90)
        $this->assertStringContainsString('1,1111', $csv);
        $this->assertStringContainsString('USD', $csv);
        $this->assertStringNotContainsString('N/A', $csv);
        // 121 USD * 0.90 = 108.90 EUR
        $this->assertStringContainsString('108,90', $csv);
    }

    private function createInvoice(
        string $operation,
        string $date,
        float $gross,
        float $total,
        string $number,
    ): Invoice {
        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => $operation,
            'number' => $number,
            'date' => $date,
            'gross_amount' => $gross,
            'total_amount' => $total,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Line',
            'quantity' => 1,
            'unit_price' => $gross,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        return $invoice;
    }
}
