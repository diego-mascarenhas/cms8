<?php

namespace Tests\Feature;

use App\Jobs\SendScheduledMessageJob;
use App\Models\ScheduledMessage;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
    }

    private function userWithTeam(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user;
    }

    public function test_schedule_message_creates_record_and_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->userWithTeam();
        $scheduledAt = now()->addHour()->toIso8601String();

        $response = $this->actingAs($user)->postJson(route('chat.schedule-message'), [
            'recipient' => '34722372858',
            'body' => 'Hola, te escribo mañana.',
            'scheduled_at' => $scheduledAt,
            'channel' => 'whatsapp',
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('scheduled_messages', [
            'recipient' => '34722372858',
            'body' => 'Hola, te escribo mañana.',
            'status' => 'pending',
            'team_id' => $user->currentTeam->id,
        ]);

        Queue::assertPushed(SendScheduledMessageJob::class);
    }

    public function test_schedule_message_requires_future_date(): void
    {
        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.schedule-message'), [
            'recipient' => '34722372858',
            'body' => 'Mensaje pasado.',
            'scheduled_at' => now()->subMinute()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
    }

    public function test_schedule_message_requires_authentication(): void
    {
        $response = $this->postJson(route('chat.schedule-message'), [
            'recipient' => '34722372858',
            'body' => 'Test',
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_scheduled_message_can_be_marked_sent(): void
    {
        $user = $this->userWithTeam();
        $scheduled = ScheduledMessage::create([
            'team_id' => $user->currentTeam->id,
            'scheduled_by_user_id' => $user->id,
            'recipient' => '34722372858',
            'channel' => 'whatsapp',
            'body' => 'Test',
            'scheduled_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $scheduled->markAsSent();

        $this->assertDatabaseHas('scheduled_messages', [
            'id' => $scheduled->id,
            'status' => 'sent',
        ]);
        $this->assertNotNull($scheduled->fresh()->sent_at);
    }
}
