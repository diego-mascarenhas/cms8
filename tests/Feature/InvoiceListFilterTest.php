<?php

namespace Tests\Feature;

use App\DataTables\InvoiceDataTable;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceListFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_invoice_datatable_default_filter_includes_purchase_and_collected_invoices(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor SL',
            'type_id' => 2,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'VENTA-PENDIENTE',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
            'source_provider' => 'cuentica',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'COMPRA-PAGADA',
            'date' => '2026-06-02',
            'due_date' => null,
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 60.5,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'cuentica',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'COMPRA-PENDIENTE',
            'date' => '2026-06-03',
            'due_date' => null,
            'gross_amount' => 80,
            'discount' => 0,
            'total_amount' => 80,
            'balance' => 80,
            'status' => 1,
            'source_provider' => 'cuentica',
        ]);

        $this->actingAs($user);
        request()->merge(['summary_filter' => 'all']);

        $numbers = app(InvoiceDataTable::class)
            ->query(app(Invoice::class))
            ->orderBy('number')
            ->pluck('number')
            ->all();

        $this->assertSame(['COMPRA-PAGADA', 'COMPRA-PENDIENTE', 'VENTA-PENDIENTE'], $numbers);
    }

    public function test_invoice_datatable_unpaid_filter_excludes_purchases_and_bonificadas(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00000102',
            'date' => '2005-03-01',
            'due_date' => '2005-03-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 5,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00000541',
            'date' => '2006-11-14',
            'due_date' => '2006-11-14',
            'gross_amount' => 94.5,
            'discount' => 0,
            'total_amount' => 94.5,
            'balance' => 0.5,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'COMPRA-PENDIENTE',
            'date' => '2006-11-15',
            'due_date' => null,
            'gross_amount' => 80,
            'discount' => 0,
            'total_amount' => 80,
            'balance' => 80,
            'status' => 1,
            'source_provider' => 'manual',
        ]);

        $this->actingAs($user);
        request()->merge(['summary_filter' => 'unpaid']);

        $numbers = app(InvoiceDataTable::class)
            ->query(app(Invoice::class))
            ->pluck('number')
            ->all();

        $this->assertSame(['0001-00000541'], $numbers);
    }

    public function test_invoice_datatable_operation_filter_buy_excludes_sales(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor SL',
            'type_id' => 2,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'VENTA-001',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
            'source_provider' => 'cuentica',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'COMPRA-001',
            'date' => '2026-06-02',
            'due_date' => null,
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 1,
            'source_provider' => 'cuentica',
        ]);

        $this->actingAs($user);
        request()->merge([
            'summary_filter' => 'all',
            'operation_filter' => 'buy',
        ]);

        $numbers = app(InvoiceDataTable::class)
            ->query(app(Invoice::class))
            ->pluck('number')
            ->all();

        $this->assertSame(['COMPRA-001'], $numbers);
    }

    public function test_invoice_datatable_operation_filter_sell_excludes_purchases(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor SL',
            'type_id' => 2,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'VENTA-001',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
            'source_provider' => 'cuentica',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'COMPRA-001',
            'date' => '2026-06-02',
            'due_date' => null,
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 1,
            'source_provider' => 'cuentica',
        ]);

        $this->actingAs($user);
        request()->merge([
            'summary_filter' => 'all',
            'operation_filter' => 'sell',
        ]);

        $numbers = app(InvoiceDataTable::class)
            ->query(app(Invoice::class))
            ->pluck('number')
            ->all();

        $this->assertSame(['VENTA-001'], $numbers);
    }
}
