<?php

namespace Tests\Feature;

use App\Jobs\SendScheduledMessageJob;
use App\Models\Conversation;
use App\Models\ScheduledMessage;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '5491167284492';

    public function test_schedule_requires_authentication(): void
    {
        $this->postJson('/api/chat/schedule-message', [
            'recipient' => self::CLIENT_PHONE,
            'body' => 'Hola',
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])->assertStatus(401);
    }

    public function test_schedule_creates_pending_message_and_exposes_recipient_clock(): void
    {
        Queue::fake();
        [$token] = $this->inbox();
        $scheduledAt = now()->addHours(3);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/schedule-message', [
                'recipient' => self::CLIENT_PHONE,
                'body' => 'Te escribo más tarde',
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'channel' => 'whatsapp',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('recipient_clock.calling_code', '54')
            ->assertJsonPath('recipient_clock.timezone', 'America/Argentina/Buenos_Aires');

        $this->assertNotEmpty($response->json('scheduled_at_recipient'));
        $this->assertDatabaseHas('scheduled_messages', [
            'recipient' => self::CLIENT_PHONE,
            'body' => 'Te escribo más tarde',
            'status' => 'pending',
        ]);
        Queue::assertPushed(SendScheduledMessageJob::class);
    }

    public function test_thread_includes_scheduled_message_and_spain_argentina_clocks(): void
    {
        [$token, $user] = $this->inbox();
        $scheduledAt = now()->addHours(2);

        ScheduledMessage::create([
            'team_id' => $user->currentTeam->id,
            'scheduled_by_user_id' => $user->id,
            'recipient' => self::CLIENT_PHONE,
            'channel' => 'whatsapp',
            'body' => 'Programado para Argentina',
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE);

        $response->assertOk();
        $response->assertJsonPath('thread_clock.recipient.timezone', 'America/Argentina/Buenos_Aires');
        $response->assertJsonPath('thread_clock.sender.timezone', 'Europe/Madrid');
        $response->assertJsonPath('whatsapp_session.open', true);
        $this->assertNotEmpty($response->json('whatsapp_session.last_inbound_at'));
        $this->assertTrue(collect($response->json('messages'))->contains(
            fn (array $message): bool => ($message['is_scheduled'] ?? false) === true
                && $message['body'] === 'Programado para Argentina',
        ));
    }

    public function test_thread_reports_closed_whatsapp_session_without_recent_inbound(): void
    {
        [$token] = $this->inbox();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/5491100000000')
            ->assertOk()
            ->assertJsonPath('whatsapp_session.open', false)
            ->assertJsonPath('whatsapp_session.last_inbound_at', null);
    }

    public function test_scheduled_message_can_be_rescheduled_and_cancelled_via_api(): void
    {
        Queue::fake();
        [$token, $user] = $this->inbox();
        $scheduled = ScheduledMessage::create([
            'team_id' => $user->currentTeam->id,
            'scheduled_by_user_id' => $user->id,
            'recipient' => self::CLIENT_PHONE,
            'channel' => 'whatsapp',
            'body' => 'Hola',
            'scheduled_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $newTime = now()->addHours(4);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/scheduled-message/'.$scheduled->id, [
                'scheduled_at' => $newTime->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(
            $newTime->toDateTimeString(),
            $scheduled->fresh()->scheduled_at->toDateTimeString(),
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/chat/scheduled-message/'.$scheduled->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('cancelled', $scheduled->fresh()->status);
    }

    /**
     * @return array{0: string, 1: User}
     */
    private function inbox(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Http::fake(['*' => Http::response(['pictures' => []], 200)]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        Conversation::create([
            'message_sid' => 'SM_api_schedule_1',
            'channel' => 'whatsapp',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        return [$user->createToken('schedule')->plainTextToken, $user];
    }
}
