<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
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
        // First Importe column = net (without tax); Tax and Total columns keep IVA breakdown.
        $this->assertStringContainsString('100,00', $csv);
        $this->assertStringContainsString('21,00', $csv);
        $this->assertStringContainsString('121,00', $csv);
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

    public function test_paid_stripe_invoice_exports_its_total_not_the_zero_balance(): void
    {
        $zeroed = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0948',
            'date' => '2026-07-13',
            'gross_amount' => 0,
            'total_amount' => 0,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_zero_balance',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $this->user->currentTeam->id,
            'provider' => 'stripe',
            'external_id' => 'in_zero_balance',
            'customer_name' => 'Diego Hernán Martinez',
            'number' => '0005-0948',
            'status' => 'paid',
            'currency' => 'eur',
            'subtotal' => 95.88,
            'tax' => 0,
            'total' => 95.88,
            'amount_due' => 95.88,
            'amount_paid' => 95.88,
            'amount_remaining' => 0,
            'paid' => true,
            'invoice_created_at' => '2026-07-13 14:25:16',
            'last_synced_at' => now(),
        ]);

        InvoiceSync::query()->create([
            'team_id' => $this->user->currentTeam->id,
            'provider' => 'stripe',
            'external_id' => 'in_not_in_book',
            'customer_name' => 'Sogi S.A.',
            'customer_tax_id' => '30-7171198-58',
            'number' => '0005-0994',
            'status' => 'open',
            'currency' => 'eur',
            'subtotal' => 234082.56,
            'tax' => 0,
            'total' => 234082.56,
            'amount_due' => 234082.56,
            'amount_paid' => 0,
            'amount_remaining' => 234082.56,
            'paid' => false,
            'invoice_created_at' => '2026-07-31 22:00:40',
            'last_synced_at' => now(),
            'raw_payload' => [
                'status_transitions' => [
                    'finalized_at' => strtotime('2026-07-31 22:00:40 UTC'),
                ],
            ],
        ]);

        $csv = $this->get(route('income.export-hacienda', [
            'vat_year' => 2026,
            'vat_period' => 'm:7',
        ]))->streamedContent();

        $rows = $this->csvRowsByNumber($csv);

        $this->assertSame(0.0, (float) $zeroed->total_amount);
        $this->assertSame('95,88', $rows['0005-0948'][4]);
        $this->assertSame('95,88', $rows['0005-0948'][9]);
        $this->assertSame('234.082,56', $rows['0005-0994'][4]);
        $this->assertSame('234.082,56', $rows['0005-0994'][9]);
        $this->assertSame('Pendiente', $rows['0005-0994'][11]);
    }

    public function test_export_includes_every_team_invoice_regardless_of_enterprise_or_status(): void
    {
        $inactiveAlliance = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Alianza inactiva',
            'type_id' => 3,
            'status_id' => 1,
            'country' => 'AR',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $inactiveAlliance->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-DRAFT',
            'date' => '2026-07-20',
            'gross_amount' => 40,
            'total_amount' => 40,
            'balance' => 40,
            'status' => 9,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $inactiveAlliance->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-VOID',
            'date' => '2026-07-21',
            'gross_amount' => 15,
            'total_amount' => 15,
            'balance' => 0,
            'status' => 3,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $this->user->currentTeam->id,
            'provider' => 'stripe',
            'external_id' => 'in_draft_without_enterprise',
            'customer_name' => 'Sin empresa',
            'number' => '0005-NOENT',
            'status' => 'void',
            'currency' => 'eur',
            'subtotal' => 12,
            'tax' => 0,
            'total' => 12,
            'amount_due' => 12,
            'amount_remaining' => 12,
            'paid' => false,
            'invoice_created_at' => '2026-07-22 10:00:00',
            'last_synced_at' => now(),
        ]);

        $csv = $this->get(route('income.export-hacienda', [
            'vat_year' => 2026,
            'vat_period' => 'm:7',
        ]))->streamedContent();

        $rows = $this->csvRowsByNumber($csv);

        $this->assertArrayNotHasKey('0005-DRAFT', $rows);
        $this->assertSame('15,00', $rows['0005-VOID'][4]);
        $this->assertSame('Anulada', $rows['0005-VOID'][11]);
        $this->assertSame('Sin empresa', $rows['0005-NOENT'][2]);
        $this->assertSame('12,00', $rows['0005-NOENT'][4]);
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

    /**
     * @return array<string, list<string>>
     */
    private function csvRowsByNumber(string $csv): array
    {
        $rows = [];

        foreach (preg_split("/\r\n|\n|\r/", trim($csv)) as $line)
        {
            if ($line === '')
            {
                continue;
            }

            $columns = str_getcsv($line);
            $rows[(string) ($columns[0] ?? '')] = $columns;
        }

        return $rows;
    }
}
