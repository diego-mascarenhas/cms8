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
}
