<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitAssistantFlowToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_commit_assistant_flow_returns_machine_marker_and_canonical_key(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $moduleKey = 'invflow_'.substr(md5((string) $team->id), 0, 8);
        $module = Module::query()->create([
            'name' => 'Invoices',
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
            'prompt_instruction' => 'Help pay invoices.',
            'is_active' => true,
            'order' => 0,
        ]);

        $agentUser = User::factory()->create();
        $agentUser->teams()->attach($team->id, ['role' => 'admin']);
        $agentUser->forceFill(['current_team_id' => $team->id])->save();

        $tools = app(AssistantToolsService::class);
        $tools->setRequestContext($agentUser->id, (int) $team->id, null);

        $routingKey = $moduleKey.':collections';
        $out = $tools->execute('commit_assistant_flow', ['routing_key' => $routingKey]);

        $this->assertStringStartsWith('FLOW_COMMITTED:', $out);
        $this->assertStringContainsString('"routing_key":"'.$routingKey.'"', $out);
    }

    public function test_commit_assistant_flow_rejects_general_router(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $moduleKey = 'asst_'.substr(md5((string) $team->id), 0, 8);
        $module = Module::query()->create([
            'name' => 'Assistant',
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
            'section_key' => 'general',
            'section_label' => 'Router',
            'prompt_instruction' => 'Route',
            'is_active' => true,
            'order' => 0,
        ]);

        $agentUser = User::factory()->create();
        $agentUser->teams()->attach($team->id, ['role' => 'admin']);
        $agentUser->forceFill(['current_team_id' => $team->id])->save();

        $tools = app(AssistantToolsService::class);
        $tools->setRequestContext($agentUser->id, (int) $team->id, null);

        $out = $tools->execute('commit_assistant_flow', ['routing_key' => $moduleKey.':general']);

        $this->assertStringStartsWith('Error:', $out);
    }
}
