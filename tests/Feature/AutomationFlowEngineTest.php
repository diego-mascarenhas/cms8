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
        $automation = Automation::factory()->create([
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
    }

    public function test_runner_uses_step_prompt_key_with_flow(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
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
