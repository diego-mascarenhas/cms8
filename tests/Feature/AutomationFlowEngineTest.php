<?php

namespace Tests\Feature;

use App\Enums\AutomationReplyType;
use App\Models\Automation;
use App\Models\Team;
use App\Services\AssistantAutomationRunner;
use App\Services\AssistantChatService;
use App\Services\AutomationFlowEngine;
use App\Services\AutomationFlowGraphSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationFlowEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_advances_on_matching_reply_after_bot_turn(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($automation, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Ask',
                    'instruction' => 'Do you want a meeting?',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => AutomationReplyType::YesNo->value, 'match_value' => 'yes', 'label' => 'Sí'],
                        ['id' => 'output_2', 'reply_type' => AutomationReplyType::Fallback->value, 'match_value' => null, 'label' => 'Otra'],
                    ],
                ],
                [
                    'client_id' => '2',
                    'label' => 'Book',
                    'instruction' => 'Lets book it',
                    'prompt_key' => 'calendar:assistant_citas',
                    'is_entry' => false,
                    'position_x' => 200,
                    'position_y' => 0,
                    'outputs' => [],
                ],
            ],
            'edges' => [
                ['from_client_id' => '1', 'from_output' => 'output_1', 'to_client_id' => '2'],
            ],
        ]);

        $engine = app(AutomationFlowEngine::class);
        $session = $engine->sessionFor($automation, Automation::CHANNEL_API, 'test-session');

        $first = $engine->resolveStepForMessage($session, 'hola');
        $this->assertSame('Ask', $first['step']?->label);
        $engine->markAwaitingReply($session->fresh());

        $second = $engine->resolveStepForMessage($session->fresh(), 'sí');
        $this->assertSame('Book', $second['step']?->label);
        $this->assertNotNull($second['matched_transition']);
        $this->assertNull($second['exit_automation_id']);
    }

    public function test_flow_exits_to_action_automation(): void
    {
        $team = Team::factory()->create();
        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);
        $action = Automation::factory()->action()->create([
            'team_id' => $team->id,
            'entry_prompt_key' => 'calendar:assistant_citas',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Ask',
                    'instruction' => 'Need a meeting?',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Choice->value,
                            'match_value' => 'cita',
                            'label' => 'Cita',
                            'to_automation_id' => $action->id,
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'from_client_id' => '1',
                    'from_output' => 'output_1',
                    'to_client_id' => null,
                    'to_automation_id' => $action->id,
                ],
            ],
        ]);

        $engine = app(AutomationFlowEngine::class);
        $session = $engine->sessionFor($funnel, Automation::CHANNEL_API, 'exit-session');
        $engine->resolveStepForMessage($session, 'hola');
        $engine->markAwaitingReply($session->fresh());

        $resolved = $engine->resolveStepForMessage($session->fresh(), 'quiero una cita');
        $this->assertSame($action->id, $resolved['exit_automation_id']);
        $this->assertFalse($resolved['completed']);
        $this->assertNull($resolved['step']);

        $session2 = $engine->sessionFor($funnel, Automation::CHANNEL_API, 'exit-runner');
        $engine->resolveStepForMessage($session2, 'hola');
        $engine->markAwaitingReply($session2->fresh());

        $this->mock(AssistantChatService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(function (string $message, $teamId, $image, $audio, $voice, $promptKey) use ($team)
                {
                    return $teamId === $team->id && $promptKey === 'calendar:assistant_citas';
                })
                ->andReturn([
                    'response' => 'Vamos a agendar',
                    'routed_to' => 'calendar:assistant_citas',
                ]);
        });

        $handed = app(AssistantAutomationRunner::class)->run(
            $funnel->fresh(),
            Automation::CHANNEL_API,
            'quiero una cita',
            null,
            null,
            false,
            'exit-runner',
        );

        $this->assertSame('Vamos a agendar', $handed['response']);
        $this->assertTrue($handed['flow_exited'] ?? false);
        $this->assertSame($action->id, $handed['automation_id']);
        $this->assertSame($funnel->id, $handed['from_automation_id']);
    }

    public function test_step_appendix_prioritizes_funnel_over_general_menu(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($automation, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Propuesta de valor',
                    'instruction' => 'Definir el problema dominante',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => AutomationReplyType::FreeText->value, 'match_value' => null, 'label' => 'Texto'],
                    ],
                ],
            ],
            'edges' => [],
        ]);

        $step = $automation->fresh()->entryStep();
        $this->assertNotNull($step);
        $appendix = app(AutomationFlowEngine::class)->stepSystemAppendix($step);

        $this->assertStringContainsString('Embudo de automatización activo', $appendix);
        $this->assertStringContainsString('NO uses el menú general de módulos', $appendix);
        $this->assertStringContainsString('NO ofrezcas', $appendix);
        $this->assertStringContainsString('Propuesta de valor', $appendix);
        $this->assertStringContainsString('Definir el problema dominante', $appendix);
    }

    public function test_runner_uses_step_prompt_key_with_flow(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($automation, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Ask',
                    'instruction' => 'Question',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'prompt_key' => 'contacts:assistant_contactos',
                    'outputs' => [],
                ],
            ],
            'edges' => [],
        ]);

        $this->mock(AssistantChatService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(function (string $message, $teamId, $image, $audio, $voice, $promptKey) use ($team)
                {
                    return $teamId === $team->id
                        && $promptKey === 'contacts:assistant_contactos'
                        && str_contains($message, 'Paso del embudo');
                })
                ->andReturn([
                    'response' => 'Ok',
                    'routed_to' => 'contacts:assistant_contactos',
                ]);
        });

        $result = app(AssistantAutomationRunner::class)->run(
            $automation,
            Automation::CHANNEL_API,
            'Hola',
            null,
            null,
            false,
            'embed-1',
        );

        $this->assertSame('Ok', $result['response']);
        $this->assertNotNull($result['step_key']);
    }
}
