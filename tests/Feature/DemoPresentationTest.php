<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Support\DemoTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_prod_read_toggle_is_hidden_on_demo_team(): void
    {
        config([
            'app.allow_prod_read_toggle' => true,
            'app.prod_read_credentials_configured' => true,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => DemoTeam::TEAM_NAME])->save();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('app.prod_read.toggle_label'), false);
    }

    public function test_demo_team_helper_matches_demo_team_name(): void
    {
        $team = Team::factory()->create(['name' => DemoTeam::TEAM_NAME]);

        $this->assertTrue(DemoTeam::isDemoTeam($team));
        $this->assertFalse(DemoTeam::isDemoTeam(Team::factory()->make(['name' => 'Other'])));
    }

    public function test_dashboard_shows_performance_insight_on_demo_team_without_module_flag(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => DemoTeam::TEAM_NAME])->save();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        UserDailyPerformanceInsight::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'insight_date' => now()->toDateString(),
            'performance_ratio' => 78.5,
            'headline' => 'Prioriza📌',
            'focus' => 'Cerrar seguimientos comerciales pendientes hoy',
            'message' => 'Buen ritmo en el equipo Demo: revisa mensajes sin leer y confirma reuniones.',
            'context_snapshot' => [
                'highlights' => [
                    'WhatsApp sin leer en bandeja demo.',
                    'Facturas vencidas requieren seguimiento.',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Prioriza', false)
            ->assertDontSee(__('app.dashboard_assistant_greeting', ['name' => explode(' ', $user->name, 2)[0]]), false);
    }

    public function test_demo_team_trim_administrators_keeps_only_core_admins(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

        $team = Team::factory()->create(['name' => DemoTeam::TEAM_NAME]);

        $coreAdmin = User::factory()->create(['email' => 'admin@humano.app']);
        $coreAdmin->assignRole('admin');
        $team->users()->attach($coreAdmin->id, ['role' => 'admin']);

        $extraAdmin = User::factory()->create(['email' => 'demo-admin1@humano.test']);
        $extraAdmin->assignRole('admin');
        $team->users()->attach($extraAdmin->id, ['role' => 'admin']);

        DemoTeam::trimAdministrators($team);

        $this->assertTrue($coreAdmin->fresh()->hasRole('admin'));
        $this->assertFalse($extraAdmin->fresh()->hasRole('admin'));
        $this->assertTrue($extraAdmin->fresh()->hasRole('employee'));
        $this->assertSame(1, $team->allUsers()->filter(fn (User $user) => $user->hasRole('admin'))->count());
    }
}
