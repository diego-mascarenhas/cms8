<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceDataTableSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
    }

    public function test_invoice_datatable_search_matches_invoice_number(): void
    {
        $team = $this->user->currentTeam;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $matchingInvoice = Invoice::withoutGlobalScopes()->create([
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
            'operation' => 'sell',
            'number' => '0001-00000102',
            'date' => '2005-03-01',
            'due_date' => '2005-03-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 10,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->invoiceDataTableUrl('00000541'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matchingInvoice->id, (string) $response->json('data.0.DT_RowId'));
    }

    public function test_invoice_datatable_search_matches_enterprise_name_with_accent_normalization(): void
    {
        $team = $this->user->currentTeam;

        $matchingEnterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Gestión Fernández SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $otherEnterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Other Active Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $matchingInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $matchingEnterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00001001',
            'date' => '2006-11-14',
            'due_date' => '2006-11-14',
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 10,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $otherEnterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00001002',
            'date' => '2006-11-14',
            'due_date' => '2006-11-14',
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 10,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->invoiceDataTableUrl('fernandez'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matchingInvoice->id, (string) $response->json('data.0.DT_RowId'));
    }

    public function test_invoice_datatable_orders_same_date_by_number_desc(): void
    {
        $team = $this->user->currentTeam;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Same Day Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $lower = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0021',
            'date' => '2026-06-06',
            'due_date' => null,
            'gross_amount' => 10,
            'discount' => 0,
            'total_amount' => 10,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_order_low',
        ]);

        $higher = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0028',
            'date' => '2026-06-06',
            'due_date' => null,
            'gross_amount' => 20,
            'discount' => 0,
            'total_amount' => 20,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_order_high',
        ]);

        $query = $this->invoiceDataTableBaseQuery();
        $query['summary_filter'] = 'credit_notes';
        $query['order'] = [['column' => 2, 'dir' => 'desc']];

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('invoice.index').'?'.http_build_query($query));

        $response->assertOk();
        $this->assertSame(2, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $higher->id, (string) $response->json('data.0.DT_RowId'));
        $this->assertSame((string) $lower->id, (string) $response->json('data.1.DT_RowId'));
    }

    public function test_invoice_datatable_orders_by_status_business_priority(): void
    {
        $team = $this->user->currentTeam;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Status Sort Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $anulada = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'ST-ANULADA',
            'date' => '2026-07-01',
            'due_date' => '2026-07-10',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 3,
        ]);

        $cobrada = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'ST-COBRADA',
            'date' => '2026-07-02',
            'due_date' => '2026-07-20',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        $pendiente = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'ST-PENDIENTE',
            'date' => '2026-07-03',
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 50,
            'status' => 2,
        ]);

        $vencida = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'ST-VENCIDA',
            'date' => '2026-06-01',
            'due_date' => now()->subDays(5)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 50,
            'status' => 2,
        ]);

        $query = $this->invoiceDataTableBaseQuery();
        $query['summary_filter'] = 'all';
        $query['order'] = [['column' => 6, 'dir' => 'asc']];
        $query['search'] = ['value' => 'ST-', 'regex' => 'false'];

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('invoice.index').'?'.http_build_query($query));

        $response->assertOk();
        $this->assertSame(4, (int) $response->json('recordsFiltered'));
        // ASC priority: Vencida → Pendiente → Cobrada → Anulada
        $this->assertSame((string) $vencida->id, (string) $response->json('data.0.DT_RowId'));
        $this->assertSame((string) $pendiente->id, (string) $response->json('data.1.DT_RowId'));
        $this->assertSame((string) $cobrada->id, (string) $response->json('data.2.DT_RowId'));
        $this->assertSame((string) $anulada->id, (string) $response->json('data.3.DT_RowId'));
    }

    private function invoiceDataTableUrl(string $searchValue): string
    {
        $query = $this->invoiceDataTableBaseQuery();
        $query['search'] = ['value' => $searchValue, 'regex' => 'false'];

        return route('invoice.index').'?'.http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ($this->invoiceDataTableColumnDefinitions() as $definition)
        {
            $columns[] = array_merge($definition, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'summary_filter' => 'excluding_collected',
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => $columns,
        ];
    }

    /**
     * @return array<int, array{data: string, name: string, searchable: string, orderable: string}>
     */
    private function invoiceDataTableColumnDefinitions(): array
    {
        return [
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'number_with_indicator', 'name' => 'number_with_indicator', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'date', 'name' => 'date', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'enterprise_id', 'name' => 'enterprise_id', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'total_amount', 'name' => 'total_amount', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'balance', 'name' => 'balance', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'status', 'name' => 'status', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ];
    }
}
