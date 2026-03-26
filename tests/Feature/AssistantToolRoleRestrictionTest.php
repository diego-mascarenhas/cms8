<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AssistantToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantToolRoleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_jetstream_client_team_role_cannot_use_internal_tools(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $client = User::factory()->create();
        $client->teams()->attach($team->id, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $team->id])->save();

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($client->id, $team->id, null);

        $out = $service->execute('list_team_users', []);
        $this->assertStringContainsString('No disponible para tu rol', $out);
    }

    public function test_jetstream_admin_team_role_can_list_team_users(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, null);

        $out = $service->execute('list_team_users', []);
        $this->assertStringContainsString('Team members', $out);
    }

    public function test_jetstream_client_can_use_commit_assistant_flow(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $moduleKey = 'modrole_'.substr(md5((string) $team->id), 0, 8);
        $module = Module::query()->create([
            'name' => 'Flow mod',
            'key' => $moduleKey,
            'level' => 1,
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);

        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'collections',
            'section_label' => 'Cobranza',
            'prompt_instruction' => 'Test',
            'is_active' => true,
            'order' => 0,
        ]);

        $client = User::factory()->create();
        $client->teams()->attach($team->id, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $team->id])->save();

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($client->id, $team->id, null);

        $out = $service->execute('commit_assistant_flow', ['routing_key' => $moduleKey.':collections']);
        $this->assertStringStartsWith('FLOW_COMMITTED:', $out);
    }

    public function test_ticket_policy_allows_spatie_client_to_create_ticket(): void
    {
        Role::firstOrCreate(['name' => 'client']);

        $client = User::factory()->create();
        $team = Team::factory()->create();
        $client->teams()->attach($team->id, ['role' => 'client']);
        $client->assignRole('client');
        $client->forceFill(['current_team_id' => $team->id])->save();

        $this->assertTrue($client->can('create', Ticket::class));
    }
}
