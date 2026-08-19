<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationFlowSession;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Services\AssistantAutomationRunner;
use App\Services\AssistantChatService;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class AssistantAutomationRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_uses_entry_prompt_key_and_rejects_disabled_channel(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'entry_prompt_key' => 'contacts:landing',
            'channels' => Automation::normalizeChannels([
                'api' => true,
                'whatsapp' => false,
            ]),
        ]);

        $this->mock(AssistantChatService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->with('Hello', $team->id, null, null, false, 'contacts:landing')
                ->andReturn([
                    'response' => 'Landing reply',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $runner = app(AssistantAutomationRunner::class);
        $result = $runner->run($automation, Automation::CHANNEL_API, 'Hello');

        $this->assertSame('Landing reply', $result['response']);
        $this->assertSame($automation->id, $result['automation_id']);

        $this->expectException(AccessDeniedHttpException::class);
        $runner->run($automation, Automation::CHANNEL_WHATSAPP, 'Hello');
    }

    public function test_run_rejects_inactive_automation(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->inactive()->create([
            'team_id' => $team->id,
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        $runner = app(AssistantAutomationRunner::class);

        $this->expectException(NotFoundHttpException::class);
        $runner->run($automation, Automation::CHANNEL_API, 'Hello');
    }

    public function test_find_by_public_token_and_default_for_channel(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'entry_prompt_key' => 'invoices:collections',
            'channels' => Automation::normalizeChannels([
                'whatsapp' => true,
                'api' => false,
            ]),
        ]);

        $runner = app(AssistantAutomationRunner::class);

        $this->assertTrue($runner->findByPublicToken($automation->public_token)->is($automation));
        $this->assertSame(
            'invoices:collections',
            $runner->resolveChannelPromptKey($team->id, Automation::CHANNEL_WHATSAPP),
        );
        $this->assertNull($runner->resolveChannelPromptKey($team->id, Automation::CHANNEL_API));
    }

    public function test_run_for_team_throws_when_slug_missing(): void
    {
        $team = Team::factory()->create();
        $runner = app(AssistantAutomationRunner::class);

        $this->expectException(NotFoundHttpException::class);
        $runner->runForTeam($team->id, Automation::CHANNEL_API, 'Hi', 'does-not-exist');
    }

    public function test_resolve_slug_from_message_supports_slug_command_and_aliases(): void
    {
        $team = Team::factory()->create();
        Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'slug' => 'embudo-estrategico-humano',
            'channels' => Automation::normalizeChannels(['whatsapp' => true, 'humano' => true]),
            'settings' => [
                'entry_aliases' => ['embudo', 'estrategia', 'embudo de operaciones'],
            ],
        ]);

        $runner = app(AssistantAutomationRunner::class);

        $this->assertSame(
            'embudo-estrategico-humano',
            $runner->resolveSlugFromMessage('embudo-estrategico-humano', $team->id, Automation::CHANNEL_WHATSAPP),
        );
        $this->assertSame(
            'embudo-estrategico-humano',
            $runner->resolveSlugFromMessage('/embudo embudo-estrategico-humano', $team->id, Automation::CHANNEL_WHATSAPP),
        );
        $this->assertSame(
            'embudo-estrategico-humano',
            $runner->resolveSlugFromMessage('Estrategia', $team->id, Automation::CHANNEL_WHATSAPP),
        );
        $this->assertSame(
            'embudo-estrategico-humano',
            $runner->resolveSlugFromMessage('embudo de operaciones', $team->id, Automation::CHANNEL_WHATSAPP),
        );
        $this->assertSame(
            'embudo-estrategico-humano',
            $runner->resolveSlugFromMessage('Embudo', $team->id, Automation::CHANNEL_WHATSAPP),
        );
        $this->assertNull(
            $runner->resolveSlugFromMessage('embudo-estrategico-humano', $team->id, Automation::CHANNEL_EMAIL),
        );
    }

    public function test_whatsapp_skips_default_funnel_when_site_prompt_is_selected(): void
    {
        $this->seed(ModuleSeeder::class);
        $team = Team::factory()->create();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Reservá citas.',
            'is_active' => true,
            'order' => 0,
        ]);

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'slug' => 'embudo-estrategico-humano',
            'entry_prompt_key' => null,
            'channels' => Automation::normalizeChannels(['whatsapp' => true, 'chat' => true]),
        ]);

        $runner = app(AssistantAutomationRunner::class);

        $withoutPrompt = $runner->resolveFlowContext(
            (int) $team->id,
            Automation::CHANNEL_WHATSAPP,
            'Hola',
            'wa:549111',
        );
        $this->assertSame($funnel->id, $withoutPrompt['automation']?->id);

        app(TeamSiteAssistantPromptService::class)->select($team, 'chat:citas_y_ventas');

        $withPrompt = $runner->resolveFlowContext(
            (int) $team->id,
            Automation::CHANNEL_WHATSAPP,
            'Hola',
            'wa:549111',
        );
        $this->assertNull($withPrompt['automation']);
        $this->assertNull($withPrompt['appendix']);
        $this->assertNull($withPrompt['prompt_key']);
    }

    public function test_whatsapp_ignores_default_funnel_session_when_site_prompt_is_selected(): void
    {
        $this->seed(ModuleSeeder::class);
        $team = Team::factory()->create();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Reservá citas.',
            'is_active' => true,
            'order' => 0,
        ]);

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'slug' => 'embudo-estrategico-humano',
            'channels' => Automation::normalizeChannels(['whatsapp' => true]),
        ]);

        AutomationFlowSession::query()->create([
            'team_id' => $team->id,
            'automation_id' => $funnel->id,
            'channel' => Automation::CHANNEL_WHATSAPP,
            'external_key' => 'wa:549111',
            'meta' => ['awaiting_reply' => true],
            'last_message_at' => now(),
        ]);

        app(TeamSiteAssistantPromptService::class)->select($team, 'chat:citas_y_ventas');

        $context = app(AssistantAutomationRunner::class)->resolveFlowContext(
            (int) $team->id,
            Automation::CHANNEL_WHATSAPP,
            'Hola',
            'wa:549111',
        );

        $this->assertNull($context['automation']);
        $this->assertFalse((bool) data_get(
            AutomationFlowSession::query()->where('automation_id', $funnel->id)->first()?->meta,
            'awaiting_reply',
        ));
    }

    public function test_whatsapp_still_enters_funnel_via_alias_when_site_prompt_is_selected(): void
    {
        $this->seed(ModuleSeeder::class);
        $team = Team::factory()->create();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Reservá citas.',
            'is_active' => true,
            'order' => 0,
        ]);

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'slug' => 'embudo-estrategico-humano',
            'channels' => Automation::normalizeChannels(['whatsapp' => true]),
            'settings' => [
                'entry_aliases' => ['embudo'],
            ],
        ]);

        app(TeamSiteAssistantPromptService::class)->select($team, 'chat:citas_y_ventas');

        $runner = app(AssistantAutomationRunner::class);
        $slug = $runner->resolveSlugFromMessage('embudo', (int) $team->id, Automation::CHANNEL_WHATSAPP);
        $this->assertSame('embudo-estrategico-humano', $slug);

        $context = $runner->resolveFlowContext(
            (int) $team->id,
            Automation::CHANNEL_WHATSAPP,
            'embudo',
            'wa:549111',
            $slug,
        );

        $this->assertSame($funnel->id, $context['automation']?->id);
    }

    public function test_reset_phrase_abandons_open_funnel_session(): void
    {
        $team = Team::factory()->create();
        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'slug' => 'embudo-estrategico-humano',
            'channels' => Automation::normalizeChannels(['whatsapp' => true]),
        ]);

        AutomationFlowSession::query()->create([
            'team_id' => $team->id,
            'automation_id' => $funnel->id,
            'channel' => Automation::CHANNEL_WHATSAPP,
            'external_key' => 'wa:549111',
            'meta' => ['awaiting_reply' => true],
            'last_message_at' => now(),
        ]);

        $context = app(AssistantAutomationRunner::class)->resolveFlowContext(
            (int) $team->id,
            Automation::CHANNEL_WHATSAPP,
            'Salir del embudo',
            'wa:549111',
        );

        $this->assertNull($context['automation']);
        $this->assertNull($context['appendix']);
        $this->assertFalse((bool) data_get(
            AutomationFlowSession::query()->where('automation_id', $funnel->id)->first()?->meta,
            'awaiting_reply',
        ));
    }
}
