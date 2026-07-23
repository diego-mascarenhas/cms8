<?php

namespace Tests\Feature;

use App\Enums\AutomationReplyType;
use App\Livewire\AssistantChat;
use App\Models\Automation;
use App\Models\User;
use App\Services\AutomationFlowGraphSyncer;
use App\Services\ChatAssistantReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantChatAutomationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_typing_automation_slug_binds_funnel_and_passes_step_appendix(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $teamId = (int) $user->current_team_id;

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $teamId,
            'name' => 'Embudo estratégico',
            'slug' => 'embudo-estrategico-humano',
            'channels' => Automation::normalizeChannels(['humano' => true, 'chat' => true]),
            'settings' => [
                'entry_aliases' => ['embudo', 'estrategia'],
            ],
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Inicio',
                    'instruction' => 'Presentá la propuesta de valor de Humano',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Choice->value,
                            'match_value' => 'empezar',
                            'label' => 'Empezar',
                        ],
                    ],
                ],
            ],
            'edges' => [],
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->withArgs(function (
                    string $message,
                    array $history,
                    $teamIdArg,
                    bool $withTools,
                    $contextUserId,
                    $phone,
                    $forcedKey,
                    $contactId,
                    bool $previewOnly,
                    $channel,
                    bool $singleSend,
                    $appendix,
                ) {
                    return $message === 'embudo-estrategico-humano'
                        && is_string($appendix)
                        && str_contains($appendix, 'Paso del embudo: Inicio')
                        && str_contains($appendix, 'Presentá la propuesta de valor de Humano');
                })
                ->andReturn([
                    'success' => true,
                    'text' => 'Vamos con la propuesta de valor.',
                    'routed_to' => null,
                    'usage' => [],
                    'meta' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                    'assistant_flow_routing_key_specified' => false,
                    'assistant_flow_routing_key' => null,
                ]);
        });

        Livewire::actingAs($user->fresh())
            ->test(AssistantChat::class)
            ->set('input', 'embudo-estrategico-humano')
            ->call('sendMessage')
            ->assertSet('automationId', $funnel->id)
            ->assertSee('Vamos con la propuesta de valor.', false);
    }

    public function test_typing_entry_alias_embudo_binds_funnel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $teamId = (int) $user->current_team_id;

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $teamId,
            'slug' => 'embudo-estrategico-humano',
            'channels' => Automation::normalizeChannels(['humano' => true]),
            'settings' => [
                'entry_aliases' => ['embudo', 'estrategia'],
            ],
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Inicio',
                    'instruction' => 'Presentá la propuesta de valor de Humano',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Choice->value,
                            'match_value' => 'empezar',
                            'label' => 'Empezar',
                        ],
                    ],
                ],
            ],
            'edges' => [],
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->withArgs(function (
                    string $message,
                    array $history,
                    $teamIdArg,
                    bool $withTools,
                    $contextUserId,
                    $phone,
                    $forcedKey,
                    $contactId,
                    bool $previewOnly,
                    $channel,
                    bool $singleSend,
                    $appendix,
                ) {
                    return $message === 'embudo'
                        && is_string($appendix)
                        && str_contains($appendix, 'Paso del embudo: Inicio');
                })
                ->andReturn([
                    'success' => true,
                    'text' => 'Arrancamos el embudo estratégico.',
                    'routed_to' => null,
                    'usage' => [],
                    'meta' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                    'assistant_flow_routing_key_specified' => false,
                    'assistant_flow_routing_key' => null,
                ]);
        });

        Livewire::actingAs($user->fresh())
            ->test(AssistantChat::class)
            ->set('input', 'embudo')
            ->call('sendMessage')
            ->assertSet('automationId', $funnel->id)
            ->assertSee('Arrancamos el embudo estratégico.', false);
    }

    public function test_start_automation_event_opens_funnel_with_empezar(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $teamId = (int) $user->current_team_id;

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $teamId,
            'slug' => 'embudo-demo',
            'channels' => Automation::normalizeChannels(['humano' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Inicio',
                    'instruction' => 'Preguntá el problema dominante',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Fallback->value,
                            'match_value' => null,
                            'label' => 'Otra',
                        ],
                    ],
                ],
            ],
            'edges' => [],
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->withArgs(function (string $message, ...$rest)
                {
                    $appendix = $rest[10] ?? null;

                    return $message === 'empezar'
                        && is_string($appendix)
                        && str_contains($appendix, 'Preguntá el problema dominante');
                })
                ->andReturn([
                    'success' => true,
                    'text' => 'Empezamos el embudo.',
                    'routed_to' => null,
                    'usage' => [],
                    'meta' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                    'assistant_flow_routing_key_specified' => false,
                    'assistant_flow_routing_key' => null,
                ]);
        });

        Livewire::actingAs($user->fresh())
            ->test(AssistantChat::class)
            ->dispatch('assistant-start-automation', automationId: $funnel->id)
            ->assertSet('automationId', $funnel->id)
            ->assertSee('Empezamos el embudo.', false);
    }

    public function test_funnel_show_includes_try_in_assistant_button(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        $user->assignRole($role);
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        \App\Models\Module::firstOrCreate(
            ['key' => 'automations'],
            [
                'name' => 'Automations',
                'icon' => 'robot',
                'description' => 'Omnichannel assistant flows',
                'is_core' => false,
                'group' => 'automation',
                'order' => 4,
                'status' => 1,
            ],
        );
        $user->currentTeam->enableModule('automations');

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Embudo prueba',
            'channels' => Automation::normalizeChannels(['humano' => true]),
        ]);

        app(\App\Services\AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Cierre',
                    'instruction' => 'Resumen final',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [],
                ],
            ],
            'edges' => [],
        ]);

        $this->actingAs($user->fresh())
            ->get(route('funnel.show', $funnel))
            ->assertOk()
            ->assertSee(__('Probar en asistente'))
            ->assertSee('funnel-try-assistant-btn', false)
            ->assertSee(__('Paso final: al llegar aquí el embudo se completa y se envía un email con el resumen a quien lo completó.'));
    }
}
