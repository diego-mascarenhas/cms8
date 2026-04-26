<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolIntentPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantToolIntentPromptServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setTeamKeywordIntentRouting(Team $team, bool $enabled): void
    {
        $team->setSetting('assistant_keyword_intent_routing', $enabled, [
            'group' => 'chat',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);
    }

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
        $this->setTeamKeywordIntentRouting($team, true);

        $service = app(AssistantToolIntentPromptService::class);
        $found = $service->findPromptForMessage((int) $team->id, 'Quiero probar el asistente');

        $this->assertNotNull($found);
        $this->assertSame('chat_capabilities', $found->section_key);
    }

    public function test_find_prompt_returns_null_when_no_match(): void
    {
        [$team] = $this->createTeamWithCapabilitiesPrompt();
        $this->setTeamKeywordIntentRouting($team, true);

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

        $this->setTeamKeywordIntentRouting($team, true);

        $found = app(AssistantToolIntentPromptService::class)->findPromptForMessage((int) $team->id, 'Mostrame el catálogo de productos');

        $this->assertNotNull($found);
        $this->assertSame('chat_commerce', $found->section_key);
    }

    public function test_resolve_flow_skips_keyword_attach_when_keyword_routing_disabled(): void
    {
        [$team] = $this->createTeamWithCapabilitiesPrompt();
        $this->setTeamKeywordIntentRouting($team, false);

        $service = app(AssistantToolIntentPromptService::class);
        $resolution = $service->resolveFlowForToolAssistant((int) $team->id, 'Quiero probar el asistente', null);

        $this->assertNull($resolution['prompt']);
        $this->assertSame('omit', $resolution['persist_assistant_flow_key']);
    }

    public function test_resolve_flow_keyword_attach_when_keyword_routing_enabled(): void
    {
        [$team] = $this->createTeamWithCapabilitiesPrompt();
        $this->setTeamKeywordIntentRouting($team, true);

        $service = app(AssistantToolIntentPromptService::class);
        $resolution = $service->resolveFlowForToolAssistant((int) $team->id, 'Quiero probar el asistente', null);

        $this->assertNotNull($resolution['prompt']);
        $this->assertSame('set', $resolution['persist_assistant_flow_key']);
    }

    public function test_find_prompt_matches_section_key_when_no_config_intent_matches(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $this->setTeamKeywordIntentRouting($team, true);

        $module = Module::query()->create([
            'name' => 'Custom flow module',
            'key' => 'custom_routing_'.substr(md5((string) $team->id), 0, 8),
            'is_core' => false,
            'status' => 1,
        ]);

        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'plan_sucesorio_unique',
            'section_label' => 'Plan sucesorio',
            'prompt_instruction' => 'Help with inheritance in Spanish.',
            'is_active' => true,
            'order' => 0,
        ]);

        $service = app(AssistantToolIntentPromptService::class);
        $pair = $service->findPromptAndRoutingKeyForMessage(
            (int) $team->id,
            'Necesito el plan sucesorio unique para un cliente, gracias',
        );

        $this->assertNotNull($pair);
        $this->assertSame('plan_sucesorio_unique', $pair['prompt']->section_key);
        $this->assertStringContainsString('plan_sucesorio_unique', $pair['routing_key']);
    }

    public function test_section_key_wins_when_score_higher_than_config_intent(): void
    {
        [$team] = $this->createTeamWithCapabilitiesPrompt();
        $this->setTeamKeywordIntentRouting($team, true);

        $chatModule = Module::query()->firstOrCreate(
            ['key' => 'chat'],
            ['name' => 'Chat', 'is_core' => false, 'status' => 1],
        );

        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $chatModule->id,
            'section_key' => 'humano-disparador-flow',
            'section_label' => 'Debug flow',
            'prompt_instruction' => 'Reply with [HUMANO_FLOW_OK] only.',
            'is_active' => true,
            'order' => 5,
        ]);

        $service = app(AssistantToolIntentPromptService::class);
        $pair = $service->findPromptAndRoutingKeyForMessage(
            (int) $team->id,
            'quiero probar humano disparador flow',
        );

        $this->assertNotNull($pair);
        $this->assertSame('humano-disparador-flow', $pair['prompt']->section_key);
        $this->assertSame('chat:humano-disparador-flow', $pair['routing_key']);
    }

    public function test_section_label_phrase_can_win_when_section_key_does_not_appear_in_message(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $this->setTeamKeywordIntentRouting($team, true);

        $module = Module::query()->create([
            'name' => 'Label intent module',
            'key' => 'label_intent_'.substr(md5((string) $team->id), 0, 8),
            'is_core' => false,
            'status' => 1,
        ]);

        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'internal_routing_only_xyz',
            'section_label' => 'disparador especial uniquetermflowqa',
            'prompt_instruction' => 'Label-only trigger test.',
            'is_active' => true,
            'order' => 0,
        ]);

        $service = app(AssistantToolIntentPromptService::class);
        $pair = $service->findPromptAndRoutingKeyForMessage(
            (int) $team->id,
            'hola disparador especial uniquetermflowqa por favor',
        );

        $this->assertNotNull($pair);
        $this->assertSame('internal_routing_only_xyz', $pair['prompt']->section_key);
    }
}
