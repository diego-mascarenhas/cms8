<?php

namespace Tests\Feature;

use App\Enums\AutomationReplyType;
use App\Mail\AutomationFunnelCompletedMail;
use App\Models\Automation;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantAutomationRunner;
use App\Services\AssistantChatService;
use App\Services\AutomationFlowEngine;
use App\Services\AutomationFlowGraphSyncer;
use App\Services\AutomationFunnelCompletionNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomationFunnelCompletionNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_funnel_step_sends_completion_email_once(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'founder@example.com',
            'name' => 'Founder',
        ]);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'name' => 'Embudo estratégico Humano',
            'slug' => 'embudo-estrategico-humano',
            'channels' => Automation::normalizeChannels(['humano' => true, 'api' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Cierre',
                    'instruction' => 'Resumen final en bullets',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [],
                ],
            ],
            'edges' => [],
        ]);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Resumen: valor, mercado, posicionamiento.',
                    'routed_to' => null,
                ]);
        });

        $this->actingAs($user);

        $result = app(AssistantAutomationRunner::class)->run(
            $funnel->fresh(),
            Automation::CHANNEL_API,
            'empezar',
            null,
            null,
            false,
            'user:'.$user->id,
        );

        $this->assertSame('Resumen: valor, mercado, posicionamiento.', $result['response']);

        Mail::assertSent(AutomationFunnelCompletedMail::class, function (AutomationFunnelCompletedMail $mail) use ($user, $funnel): bool
        {
            return $mail->hasTo($user->email)
                && $mail->automation->is($funnel)
                && $mail->recipientName === 'Founder'
                && in_array('Cierre', $mail->summaryLines, true);
        });

        $session = app(AutomationFlowEngine::class)->sessionFor($funnel->fresh(), Automation::CHANNEL_API, 'user:'.$user->id);
        $this->assertNotNull(data_get($session->meta, 'completion_email_sent_at'));

        Mail::fake();
        app(AutomationFunnelCompletionNotifier::class)->notifyIfEligible(
            $funnel->fresh(),
            $session->fresh(),
            $funnel->fresh()->entryStep(),
            false,
        );
        Mail::assertNothingSent();
    }

    public function test_non_terminal_step_does_not_send_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'ops@example.com']);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Inicio',
                    'instruction' => 'Empezar',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Fallback->value,
                            'match_value' => null,
                            'label' => 'Siguiente',
                        ],
                    ],
                ],
                [
                    'client_id' => '2',
                    'label' => 'Cierre',
                    'instruction' => 'Fin',
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

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Arrancamos',
                    'routed_to' => null,
                ]);
        });

        $this->actingAs($user);

        app(AssistantAutomationRunner::class)->run(
            $funnel->fresh(),
            Automation::CHANNEL_API,
            'hola',
            null,
            null,
            false,
            'user:'.$user->id,
        );

        Mail::assertNothingSent();
    }

    public function test_exit_to_send_funnel_summary_email_action_sends_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'founder@example.com',
            'name' => 'Founder',
        ]);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'name' => 'Embudo con salida email',
            'channels' => Automation::normalizeChannels(['api' => true, 'humano' => true]),
        ]);
        $emailAction = Automation::factory()->sendFunnelSummaryEmail()->create([
            'team_id' => $team->id,
            'name' => 'Enviar resumen por email',
            'slug' => 'enviar-resumen-por-email',
            'channels' => Automation::normalizeChannels(['api' => true, 'humano' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => '¿Querés el resumen?',
                    'instruction' => 'Ofrecé enviar el resumen',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Choice->value,
                            'match_value' => 'email',
                            'label' => 'Email',
                            'to_automation_id' => $emailAction->id,
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'from_client_id' => '1',
                    'from_output' => 'output_1',
                    'to_client_id' => null,
                    'to_automation_id' => $emailAction->id,
                ],
            ],
        ]);

        $engine = app(AutomationFlowEngine::class);
        $session = $engine->sessionFor($funnel->fresh(), Automation::CHANNEL_API, 'user:'.$user->id);
        $engine->resolveStepForMessage($session, 'hola');
        $engine->markAwaitingReply($session->fresh());

        $this->actingAs($user);

        $result = app(AssistantAutomationRunner::class)->run(
            $funnel->fresh(),
            Automation::CHANNEL_API,
            'mandame el email',
            null,
            null,
            false,
            'user:'.$user->id,
        );

        $this->assertTrue($result['flow_completed'] ?? false);
        $this->assertTrue($result['flow_exited'] ?? false);
        $this->assertSame($emailAction->id, $result['automation_id']);
        $this->assertSame(__('Listo. Te enviamos el resumen por email.'), $result['response']);

        Mail::assertSent(AutomationFunnelCompletedMail::class, function (AutomationFunnelCompletedMail $mail) use ($user, $funnel): bool
        {
            return $mail->hasTo($user->email)
                && $mail->automation->is($funnel)
                && $mail->recipientName === 'Founder';
        });
    }
}
