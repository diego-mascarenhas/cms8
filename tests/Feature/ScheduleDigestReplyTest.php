<?php

namespace Tests\Feature;

use App\Jobs\SendScheduledMessageJob;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\PerformanceDigestScheduleReplyService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleDigestReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
    }

    public function test_schedule_digest_email_reply_creates_scheduled_message(): void
    {
        Queue::fake();
        $user = $this->createUserWithRole('admin');
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->postJson(route('notification.schedule-digest-reply', $notification), [
            'schedule_action' => 'email',
            'schedule_recipient' => 'client@example.com',
            'schedule_subject' => 'Re: Presupuesto',
            'highlight_key' => 'email_unread',
            'digest_message_id' => 42,
            'body' => 'Hola, te confirmo en breve.',
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('scheduled_messages', [
            'team_id' => $user->currentTeam->id,
            'recipient' => 'client@example.com',
            'channel' => 'email',
            'status' => 'pending',
            'scheduled_by_user_id' => $user->id,
        ]);

        $scheduled = ScheduledMessage::query()->first();
        $this->assertSame('performance_digest', $scheduled->metadata['source'] ?? null);
        $this->assertSame($notification->id, $scheduled->metadata['notification_id'] ?? null);
        $this->assertSame('email_unread', $scheduled->metadata['highlight_key'] ?? null);
        $this->assertSame(42, $scheduled->metadata['digest_message_id'] ?? null);

        Queue::assertPushed(SendScheduledMessageJob::class);
    }

    public function test_scheduled_digest_reply_persists_on_notification_highlights(): void
    {
        Queue::fake();
        $user = $this->createUserWithRole('admin');
        $notification = $this->createNotification($user);

        app(PerformanceDigestScheduleReplyService::class)->schedule(
            $user->currentTeam,
            $user,
            'email',
            'client@example.com',
            'Hola, te confirmo en breve.',
            'Re: Presupuesto',
            $notification->id,
            'email_unread',
            42,
        );

        $highlights = app(PerformanceDigestScheduleReplyService::class)->attachToHighlights([
            [
                'key' => 'email_unread',
                'label' => 'Unread emails',
                'count' => 1,
                'detail_mode' => 'messages',
                'messages' => [
                    [
                        'id' => 42,
                        'channel' => 'email',
                        'schedule_action' => 'email',
                        'schedule_recipient' => 'client@example.com',
                        'schedule_subject' => 'Re: Presupuesto',
                        'action_label' => 'Programar correo (2 h)',
                    ],
                ],
            ],
        ], $notification->fresh());

        $this->assertNotNull($highlights[0]['messages'][0]['scheduled_message_id'] ?? null);
        $this->assertNull($highlights[0]['messages'][0]['schedule_action'] ?? null);
    }

    public function test_cancel_digest_reply_marks_scheduled_message_as_cancelled(): void
    {
        Queue::fake();
        $user = $this->createUserWithRole('admin');
        $notification = $this->createNotification($user);

        $scheduled = app(PerformanceDigestScheduleReplyService::class)->schedule(
            $user->currentTeam,
            $user,
            'email',
            'client@example.com',
            'Hola, te confirmo en breve.',
            'Re: Presupuesto',
            $notification->id,
            'email_unread',
            42,
        );

        $response = $this->actingAs($user)->deleteJson(
            route('notification.cancel-digest-reply', [$notification, $scheduled]),
        );

        $response->assertOk()->assertJsonFragment(['success' => true]);
        $this->assertSame('cancelled', $scheduled->fresh()->status);
    }

    public function test_schedule_digest_whatsapp_reply_creates_scheduled_message(): void
    {
        Queue::fake();
        $user = $this->createUserWithRole('admin');
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->postJson(route('notification.schedule-digest-reply', $notification), [
            'schedule_action' => 'whatsapp',
            'schedule_recipient' => '34600111222',
            'body' => 'Hola, te escribo por la factura pendiente.',
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('scheduled_messages', [
            'team_id' => $user->currentTeam->id,
            'recipient' => '34600111222',
            'channel' => 'whatsapp',
            'status' => 'pending',
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user;
    }

    private function createNotification(User $user): Notification
    {
        $typeId = NotificationType::query()->firstOrCreate(
            ['name' => 'General Message'],
            ['template_subject' => 'Test', 'template_body' => 'Test', 'is_customizable' => true, 'is_active' => true],
        )->id;

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'email' => $user->email,
            'name' => 'Admin',
            'surname' => 'User',
            'phone' => '34600000001',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);

        return Notification::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'type_id' => $typeId,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'subject' => 'Test',
            'message' => 'Test',
        ]);
    }
}
