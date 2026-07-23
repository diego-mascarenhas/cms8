<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Team;
use App\Services\AssistantAutomationRunner;
use App\Services\AssistantChatService;
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
                'entry_aliases' => ['estrategia', 'embudo de operaciones'],
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
        $this->assertNull(
            $runner->resolveSlugFromMessage('embudo-estrategico-humano', $team->id, Automation::CHANNEL_EMAIL),
        );
        $this->assertNull(
            $runner->resolveSlugFromMessage('Embudo', $team->id, Automation::CHANNEL_WHATSAPP),
            'La palabra "Embudo" sola no es slug ni alias; no debe disparar el embudo.',
        );
    }
}
