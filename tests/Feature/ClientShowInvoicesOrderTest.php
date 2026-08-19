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

    public function test_client_show_invoices_are_sorted_by_date_desc(): void
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

        $olderIdNewerDate = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'OLD-ID-NEW-DATE',
            'date' => '2026-06-20',
            'due_date' => '2026-06-30',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        $newerIdOlderDate = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'NEW-ID-OLD-DATE',
            'date' => '2026-01-05',
            'due_date' => '2026-01-15',
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 200,
            'status' => 1,
        ]);

        $this->assertGreaterThan($olderIdNewerDate->id, $newerIdOlderDate->id);

        $response = $this->actingAs($user)->get(route('client.show', $client->id));

        $response->assertOk();

        // The table sorts on the date column, which needs the ISO data-order attribute to beat
        // the d/m/Y text shown to the user.
        $response->assertSee('order: [[2, \'desc\']]', false);
        $response->assertSee('data-order="2026-06-20"', false);
        $response->assertSee('data-order="2026-01-05"', false);
    }
}
