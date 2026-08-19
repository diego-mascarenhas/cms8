<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSettingsHostingCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_settings_shows_hosting_card_with_server_links_when_modules_enabled(): void
    {
        $this->seed(ModuleSeeder::class);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        // The links sit behind access-infrastructure-modules, which reads the Spatie role.
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $user->refresh();

        $team->enableModule('servers');
        $team->enableModule('hosting');

        Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Huginn',
            'server_url' => 'huginn.example.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $response = $this->actingAs($user)->get(route('team-settings.index', $team));

        $response->assertOk();
        $response->assertSee(__('team_settings.groups.hosting.title'), false);
        $response->assertSee(route('server.index'), false);
        $response->assertSee(route('hosting.index'), false);
    }

    public function test_team_settings_shows_enable_modules_hint_when_hosting_modules_disabled(): void
    {
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('team-settings.index', $team));

        $response->assertOk();
        $response->assertSee(__('team_settings.groups.hosting.modules_disabled'), false);
        $response->assertSee(route('account.edit', $team->id), false);
    }
}
