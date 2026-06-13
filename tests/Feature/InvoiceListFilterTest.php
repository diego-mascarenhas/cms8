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

    public function test_invoice_datatable_default_filter_includes_paid_purchase_invoices(): void
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
            'number' => 'VENTA-COBRADA',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
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

        $this->actingAs($user);
        request()->merge(['summary_filter' => 'excluding_collected']);

        $numbers = app(InvoiceDataTable::class)
            ->query(app(Invoice::class))
            ->pluck('number')
            ->all();

        $this->assertSame(['COMPRA-PAGADA'], $numbers);
    }

    public function test_invoice_datatable_legacy_all_filter_excludes_bonificadas(): void
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

        $this->actingAs($user);
        request()->merge(['summary_filter' => 'all']);

        $numbers = app(InvoiceDataTable::class)
            ->query(app(Invoice::class))
            ->pluck('number')
            ->all();

        $this->assertSame(['0001-00000541'], $numbers);
    }
}
