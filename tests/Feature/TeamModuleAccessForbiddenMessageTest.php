<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamModuleAccessForbiddenMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_automations_module_shows_specific_message(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        Module::query()->firstOrCreate(
            ['key' => 'automations'],
            [
                'name' => 'Automations',
                'icon' => 'layout',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Equipo Sin Módulo',
        ]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $team->disableModule('automations');
        $this->assertFalse($team->fresh()->hasModule('automations'));

        $response = $this->actingAs($user->fresh())->get(route('funnel-list'));

        $response->assertRedirect('/misc-not-authorized');
        $response->assertSessionHas(
            'unauthorized_message',
            'Tu equipo actual (Equipo Sin Módulo) no tiene el módulo «Automations» activo. Pedile al administrador que lo habilite o cambiá de equipo.',
        );

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('funnel-list'))
            ->assertOk()
            ->assertSee('Módulo no disponible')
            ->assertSee('Equipo Sin Módulo')
            ->assertSee('Automations');
    }
}
