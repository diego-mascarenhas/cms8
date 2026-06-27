<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseCreateSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
        ]);
    }

    public function test_create_supplier_endpoint_returns_enterprise_json(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->postJson(route('expense.create-supplier'), [
            'name' => 'Nuevo Proveedor SL',
            'identification_number' => 'B99887766',
            'email' => 'nuevo@proveedor.test',
            'country' => 'ES',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('enterprise.name', 'Nuevo Proveedor SL');

        $this->assertDatabaseHas('enterprises', [
            'team_id' => $team->id,
            'name' => 'Nuevo Proveedor SL',
            'type_id' => 2,
        ]);
    }

    public function test_create_supplier_rejects_own_business_configuration(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $team->setSetting('business_config', [
            'business_name' => 'Mi Empresa SL',
            'business_email' => 'facturacion@miempresa.test',
        ], ['type' => 'json', 'group' => 'business-config']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user)->postJson(route('expense.create-supplier'), [
            'name' => 'Mi Empresa SL',
            'identification_number' => 'B11111111',
            'email' => 'facturacion@miempresa.test',
            'country' => 'ES',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_supplier_rejects_invalid_phone(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user)->postJson(route('expense.create-supplier'), [
            'name' => 'Proveedor Teléfono SL',
            'phone' => 'no-es-un-telefono',
            'country' => 'ES',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        $this->actingAs($user)->postJson(route('expense.create-supplier'), [
            'name' => 'Proveedor Teléfono SL',
            'phone' => '12345',
            'country' => 'ES',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_supplier_accepts_valid_phone(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user)->postJson(route('expense.create-supplier'), [
            'name' => 'Proveedor Con Teléfono SL',
            'phone' => '+34 600 123 456',
            'country' => 'ES',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('enterprises', [
            'team_id' => $team->id,
            'name' => 'Proveedor Con Teléfono SL',
            'phone' => '+34 600 123 456',
        ]);
    }
}
