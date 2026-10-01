<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class VatHaciendaCreditNoteExportTest extends TestCase
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
            EnterpriseTaxStatusTypeSeeder::class,
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
            'name' => 'Cliente España SL',
            'type_id' => 1,
            'status_id' => 1,
            'country' => 'ES',
        ]);

        $this->eurCurrencyId = Currency::query()->where('code', 'EUR')->value('id');
    }

    public function test_income_page_shows_export_dropdown_with_credit_notes(): void
    {
        $this->get(route('income.index', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))
            ->assertOk()
            ->assertSee('incomeExportDropdown', false)
            ->assertSee('/income/export-hacienda', false)
            ->assertSee('/income/export-credit-notes', false)
            ->assertSee('/income/export-hacienda-previous-quarter', false)
            ->assertSee('Generar ZIP Trimestre Anterior', false)
            ->assertSee('js-filter-select', false)
            ->assertSee(__('Credit notes'), false);
    }

    public function test_spain_credit_note_exports_negative_base_and_vat_on_credit_notes_route(): void
    {
        $original = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0989',
            'date' => '2024-05-05',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_orig',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $original->id,
            'description' => 'Servicio',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $abono = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => '0005-0990',
            'date' => '2024-05-12',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 4,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_abono',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $abono->id,
            'description' => 'Abono',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $haciendaCsv = $this->get(route('income.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('0005-0989', $haciendaCsv);
        $this->assertStringNotContainsString('0005-0990', $haciendaCsv);

        $creditNotesCsv = $this->get(route('income.export-credit-notes', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('0005-0990', $creditNotesCsv);
        $this->assertStringNotContainsString('0005-0989', $creditNotesCsv);
        $this->assertStringContainsString('-100,00', $creditNotesCsv);
        $this->assertStringContainsString('-21,00', $creditNotesCsv);
        $this->assertStringContainsString('-121,00', $creditNotesCsv);
        $this->assertStringContainsString('Nota de Crédito', $creditNotesCsv);
    }

    public function test_spain_credit_note_with_mismatched_line_exports_header_base_and_vat(): void
    {
        $original = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0720',
            'date' => '2024-05-08',
            'gross_amount' => 600,
            'total_amount' => 726,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_0720',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $original->id,
            'description' => 'Line stored below the header base',
            'quantity' => 1,
            'unit_price' => 60,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $abono = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => '0005-0720-CN-01',
            'date' => '2024-05-18',
            'gross_amount' => 600,
            'total_amount' => 726,
            'balance' => 0,
            'status' => 4,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_0720',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $abono->id,
            'description' => 'Line stored below the header base',
            'quantity' => 1,
            'unit_price' => 60,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $salesCsv = $this->get(route('income.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('0005-0720', $salesCsv);
        $this->assertStringNotContainsString('0005-0720-CN-01', $salesCsv);
        $this->assertStringContainsString('600,00', $salesCsv);
        $this->assertStringContainsString('126,00', $salesCsv);
        $this->assertStringContainsString('726,00', $salesCsv);
        $this->assertStringNotContainsString('12,60', $salesCsv);
        $this->assertStringNotContainsString('713,40', $salesCsv);

        $creditNotesCsv = $this->get(route('income.export-credit-notes', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('0005-0720-CN-01', $creditNotesCsv);
        $this->assertStringNotContainsString('0005-0720,', $creditNotesCsv);
        $this->assertStringContainsString('-600,00', $creditNotesCsv);
        $this->assertStringContainsString('-126,00', $creditNotesCsv);
        $this->assertStringContainsString('-726,00', $creditNotesCsv);
        $this->assertStringNotContainsString('-12,60', $creditNotesCsv);
        $this->assertStringNotContainsString('-713,40', $creditNotesCsv);
    }

    public function test_spain_sell_without_line_tax_uses_total_minus_gross_as_vat(): void
    {
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'ES-NO-LINES-TAX',
            'date' => '2024-05-20',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);

        $csv = $this->get(route('income.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('ES-NO-LINES-TAX', $csv);
        $this->assertStringContainsString('100,00', $csv);
        $this->assertStringContainsString('21,00', $csv);
        $this->assertStringContainsString('121,00', $csv);
    }

    public function test_expense_page_shows_export_dropdown_with_credit_notes(): void
    {
        $this->get(route('expense.index', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))
            ->assertOk()
            ->assertSee('expenseExportDropdown', false)
            ->assertSee('/expense/export-hacienda', false)
            ->assertSee('/expense/export-credit-notes', false)
            ->assertSee('/income/export-hacienda-previous-quarter', false)
            ->assertSee('Generar ZIP Trimestre Anterior', false)
            ->assertSee('js-filter-select', false)
            ->assertSee(__('Credit notes'), false);
    }

    public function test_expense_credit_notes_export_only_includes_buy_credit_notes(): void
    {
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'EXP-001',
            'date' => '2024-05-05',
            'gross_amount' => 50,
            'total_amount' => 60.5,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);

        $abono = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 2,
            'operation' => 'buy',
            'number' => 'EXP-CN-001',
            'date' => '2024-05-12',
            'gross_amount' => 50,
            'total_amount' => 60.5,
            'balance' => 0,
            'status' => 4,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $abono->id,
            'description' => 'Abono gasto',
            'quantity' => 1,
            'unit_price' => 50,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $haciendaCsv = $this->get(route('expense.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('EXP-001', $haciendaCsv);
        $this->assertStringNotContainsString('EXP-CN-001', $haciendaCsv);

        $creditNotesCsv = $this->get(route('expense.export-credit-notes', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('EXP-CN-001', $creditNotesCsv);
        $this->assertStringNotContainsString('EXP-001', $creditNotesCsv);
        $this->assertStringContainsString('-50,00', $creditNotesCsv);
        $this->assertStringContainsString('-10,50', $creditNotesCsv);
        $this->assertStringContainsString('-60,50', $creditNotesCsv);
    }

    public function test_previous_quarter_zip_contains_buy_sell_and_credit_note_csvs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

        try
        {
            $this->createBookInvoice('sell', 1, 2, '2026-08-10', 'FAC-VENTA', 100);
            $this->createBookInvoice('buy', 1, 2, '2026-08-12', 'FAC-COMPRA', 50);
            $this->createBookInvoice('sell', 2, 4, '2026-08-20', 'NC-VENTA', 100, 'cn_venta');
            $this->createBookInvoice('buy', 2, 4, '2026-08-22', 'NC-COMPRA', 50);
            $this->createBookInvoice('sell', 1, 2, '2026-10-01', 'FAC-FUERA', 10);

            $response = $this->get(route('income.export-hacienda-previous-quarter'));

            $response->assertOk();
            $response->assertDownload('hacienda-compra-venta-Q3-2026.zip');

            $path = $response->baseResponse->getFile()->getPathname();
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);

            $venta = $zip->getFromName('hacienda-venta-Q3-2026.csv');
            $compra = $zip->getFromName('hacienda-compra-Q3-2026.csv');
            $creditNotesSell = $zip->getFromName('hacienda-notas-credito-venta-Q3-2026.csv');
            $creditNotesBuy = $zip->getFromName('hacienda-notas-credito-compra-Q3-2026.csv');
            $zip->close();

            $this->assertIsString($venta);
            $this->assertIsString($compra);
            $this->assertIsString($creditNotesSell);
            $this->assertIsString($creditNotesBuy);

            $this->assertStringContainsString('FAC-VENTA', $venta);
            $this->assertStringNotContainsString('FAC-COMPRA', $venta);
            $this->assertStringNotContainsString('NC-VENTA', $venta);
            $this->assertStringNotContainsString('FAC-FUERA', $venta);

            $this->assertStringContainsString('FAC-COMPRA', $compra);
            $this->assertStringNotContainsString('FAC-VENTA', $compra);
            $this->assertStringNotContainsString('NC-COMPRA', $compra);

            $this->assertStringContainsString('NC-VENTA', $creditNotesSell);
            $this->assertStringContainsString('-100,00', $creditNotesSell);
            $this->assertStringNotContainsString('FAC-VENTA', $creditNotesSell);
            $this->assertStringNotContainsString('NC-COMPRA', $creditNotesSell);

            $this->assertStringContainsString('NC-COMPRA', $creditNotesBuy);
            $this->assertStringContainsString('-50,00', $creditNotesBuy);
            $this->assertStringNotContainsString('FAC-COMPRA', $creditNotesBuy);
        } finally
        {
            Carbon::setTestNow();
        }
    }

    public function test_credit_note_export_uses_the_original_invoice_tax_id(): void
    {
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0396',
            'date' => '2024-05-01',
            'gross_amount' => 100,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_tax_orig',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $this->user->currentTeam->id,
            'provider' => 'stripe',
            'external_id' => 'in_tax_orig',
            'number' => '0005-0396',
            'status' => 'paid',
            'currency' => 'eur',
            'customer_tax_id' => '30-7160149-98',
            'total' => 100,
            'paid' => true,
            'last_synced_at' => now(),
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0001',
            'date' => '2024-05-12',
            'gross_amount' => 100,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 4,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_tax_orig',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $this->user->currentTeam->id,
            'provider' => 'stripe',
            'external_id' => 'cn_tax_orig',
            'number' => '0005-0396-CN-01',
            'status' => 'issued',
            'currency' => 'eur',
            'total' => 100,
            'paid' => true,
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 'cn_tax_orig',
                'invoice' => 'in_tax_orig',
            ],
        ]);

        $csv = $this->get(route('income.export-credit-notes', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('CN-0005-0001', $csv);
        $this->assertStringContainsString('30-7160149-98', $csv);
    }

    public function test_purchase_export_uses_the_supplier_tax_id(): void
    {
        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $this->enterprise->id,
            'name' => 'Proveedor SL',
            'tax_status_type_id' => 1,
            'identification_number' => 'B83834747',
            'status' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'ES4353227',
            'date' => '2024-05-08',
            'gross_amount' => 50,
            'total_amount' => 60.5,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
        ]);

        $csv = $this->get(route('expense.export-hacienda', [
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->streamedContent();

        $this->assertStringContainsString('ES4353227', $csv);
        $this->assertStringContainsString('B83834747', $csv);
    }

    private function createBookInvoice(
        string $operation,
        int $typeId,
        int $status,
        string $date,
        string $number,
        float $gross,
        ?string $sourceReferenceId = null,
    ): void {
        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => $typeId,
            'operation' => $operation,
            'number' => $number,
            'date' => $date,
            'gross_amount' => $gross,
            'total_amount' => round($gross * 1.21, 2),
            'balance' => 0,
            'status' => $status,
            'currency_id' => $this->eurCurrencyId,
            'source_provider' => 'manual',
            'source_reference_id' => $sourceReferenceId,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Line',
            'quantity' => 1,
            'unit_price' => $gross,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);
    }
}
