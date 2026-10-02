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

class ClientShowInvoicesOrderTest extends TestCase
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

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_client_show_invoices_follow_list_status_priority_then_number(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Orden Facturas',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0100',
            'date' => '2026-06-20',
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0200',
            'date' => '2026-01-05',
            'due_date' => now()->subDays(5)->toDateString(),
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 200,
            'status' => 2,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0300',
            'date' => '2026-03-01',
            'due_date' => null,
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 0,
            'status' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('client.show', $client->id));

        $response->assertOk();
        $response->assertSee('id="clientInvoicesTableSearch"', false);
        $response->assertSee('dom: \'rtip\'', false);
        $response->assertSee(route('invoice.create', ['enterprise_id' => $client->id]), false);
        $response->assertSee('Ingresar factura', false);
        $response->assertSee('order: [[6, \'asc\'], [1, \'desc\']]', false);
        $response->assertSeeInOrder([
            '0005-0200',
            '0005-0100',
            '0005-0300',
        ]);
        $this->assertMatchesRegularExpression(
            '/data-order="2026-03-01">01\/03\/2026<\/td>\s*<td\s*>\s*<\/td>/',
            $response->getContent(),
        );
    }
}
