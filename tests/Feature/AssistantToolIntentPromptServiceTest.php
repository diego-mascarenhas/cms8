<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolIntentPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AssistantToolIntentPromptServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Team, 1: Prompt, 2: User}
     */
    protected function createTeamWithCapabilitiesPrompt(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $module = Module::query()->create([
            'name' => 'Intent test module',
            'key' => 'intent_test_'.substr(md5((string) $team->id), 0, 8),
            'is_core' => false,
            'status' => 1,
        ]);

        $prompt = Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'chat_capabilities',
            'section_label' => 'Capabilities flow',
            'prompt_instruction' => 'Explain CRM capabilities briefly in Spanish.',
            'is_active' => true,
            'order' => 0,
        ]);

        return [$team, $prompt, $owner];
    }

    public function test_find_prompt_when_intent_matches(): void
    {
        [$team] = $this->createTeamWithCapabilitiesPrompt();

        $service = app(AssistantToolIntentPromptService::class);
        $found = $service->findPromptForMessage((int) $team->id, 'Quiero probar el asistente');

        $this->assertNotNull($found);
        $this->assertSame('chat_capabilities', $found->section_key);
    }

    public function test_find_prompt_returns_null_when_no_match(): void
    {
        [$team] = $this->createTeamWithCapabilitiesPrompt();

        $service = app(AssistantToolIntentPromptService::class);
        $this->assertNull($service->findPromptForMessage((int) $team->id, 'Solo un saludo genérico sin keywords'));
    }

    public function test_commerce_intent_resolves_when_prompt_exists(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $module = Module::query()->create([
            'name' => 'Commerce intent',
            'key' => 'commerce_intent_'.substr(md5((string) $team->id), 0, 8),
            'is_core' => false,
            'status' => 1,
        ]);

        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'chat_commerce',
            'section_label' => 'Shop flow',
            'prompt_instruction' => 'Help with catalog and cart in Spanish.',
            'is_active' => true,
            'order' => 0,
        ]);

        $found = app(AssistantToolIntentPromptService::class)->findPromptForMessage((int) $team->id, 'Mostrame el catálogo de productos');

        $this->assertNotNull($found);
        $this->assertSame('chat_commerce', $found->section_key);
    }

    public function test_resolve_flow_skips_keyword_attach_when_keyword_routing_disabled(): void
    {
        Config::set('assistant_tool_intent_prompts.keyword_intent_routing', false);
        [$team] = $this->createTeamWithCapabilitiesPrompt();

        $service = app(AssistantToolIntentPromptService::class);
        $resolution = $service->resolveFlowForToolAssistant((int) $team->id, 'Quiero probar el asistente', null);

        $this->assertNull($resolution['prompt']);
        $this->assertSame('omit', $resolution['persist_assistant_flow_key']);
    }

    public function test_resolve_flow_keyword_attach_when_keyword_routing_enabled(): void
    {
        Config::set('assistant_tool_intent_prompts.keyword_intent_routing', true);
        [$team] = $this->createTeamWithCapabilitiesPrompt();

        $service = app(AssistantToolIntentPromptService::class);
        $resolution = $service->resolveFlowForToolAssistant((int) $team->id, 'Quiero probar el asistente', null);

        $this->assertNotNull($resolution['prompt']);
        $this->assertSame('set', $resolution['persist_assistant_flow_key']);
    }
}
