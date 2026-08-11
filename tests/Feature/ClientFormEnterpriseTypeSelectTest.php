<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientFormEnterpriseTypeSelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_client_create_and_edit_forms_render_enterprise_type_select(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Tipo Form Client',
            'type_id' => 1,
            'status_id' => 2,
            'email' => 'tipo-form@example.test',
        ]);

        $create = $this->actingAs($user)->get(route('client.create'));
        $create->assertOk();
        $create->assertSee('id="type_id"', false);
        $create->assertSee('name="type_id"', false);
        $create->assertSee('Cliente', false);
        $create->assertSee('Proveedor', false);
        $create->assertSee('Alianza', false);
        $this->assertMatchesRegularExpression(
            '/col-md-6[\s\S]*?id="email"[\s\S]*?col-md-6[\s\S]*?id="website"/',
            $create->getContent(),
        );

        $edit = $this->actingAs($user)->get(route('client.edit', $enterprise->id));
        $edit->assertOk();
        $edit->assertSee('id="type_id"', false);
        $edit->assertSee('value="1"', false);
        $edit->assertSee('Cliente', false);
        $edit->assertSee('Alianza', false);
    }

    public function test_client_store_can_change_enterprise_type(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Change Type Co',
            'type_id' => 1,
            'status_id' => 2,
            'email' => 'change-type@example.test',
        ]);

        $response = $this->actingAs($user)->post(route('client.store'), [
            'id' => $enterprise->id,
            'name' => 'Change Type Co',
            'email' => 'change-type@example.test',
            'type_id' => 3,
            'status_id' => 2,
        ]);

        $response->assertRedirect(route('client.show', $enterprise->id));

        $enterprise->refresh();
        $this->assertSame(3, (int) $enterprise->type_id);
    }

    public function test_client_store_creates_enterprise_with_selected_type(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->post(route('client.store'), [
            'name' => 'New Alliance Partner',
            'email' => 'alliance-partner@example.test',
            'type_id' => 3,
            'status_id' => 2,
        ]);

        $response->assertRedirect(route('client-list'));

        $this->assertDatabaseHas('enterprises', [
            'team_id' => $team->id,
            'name' => 'New Alliance Partner',
            'type_id' => 3,
        ]);
    }
}
