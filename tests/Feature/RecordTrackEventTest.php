<?php

namespace Tests\Feature;

use App\Jobs\RecordTrackEventJob;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\Track\TrackEventRecorder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecordTrackEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
        ]);

        config([
            'services.go.track_connection' => 'redis',
            'services.go.track_queue' => 'track',
        ]);
    }

    public function test_the_open_pixel_queues_the_write_on_the_track_queue(): void
    {
        Queue::fake();
        $delivery = $this->delivery();
        $token = $delivery->getTrackingToken();

        $this->get('/message/track/'.$token)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        Queue::assertPushedOn('track', RecordTrackEventJob::class, function (RecordTrackEventJob $job) use ($token): bool
        {
            return $job->connection === 'redis'
                && $job->type === 'mailer.open'
                && ($job->payload['token'] ?? null) === $token;
        });
        $this->assertNull($delivery->fresh()->opened_at);

        (new RecordTrackEventJob('mailer.open', [
            'token' => $token,
            'ip' => '203.0.113.8',
            'user_agent' => 'TestAgent',
        ]))->handle(app(TrackEventRecorder::class));

        $delivery->refresh();
        $this->assertNotNull($delivery->opened_at);
        $this->assertSame('203.0.113.8', $delivery->trackingEvents()->first()->ip_address);
    }

    public function test_unsubscribe_queues_the_status_change(): void
    {
        Queue::fake();
        $user = User::factory()->withPersonalTeam()->create();
        $leadId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $lostId = (int) ContactStatus::query()->where('name', 'Perdido')->value('id');
        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'reader@example.test',
            'status_id' => $leadId,
        ]);

        $this->post('/unsubscribe/'.rawurlencode('reader@example.test'), [
            'List-Unsubscribe' => 'One-Click',
        ])->assertOk();

        Queue::assertPushedOn('track', RecordTrackEventJob::class, function (RecordTrackEventJob $job): bool
        {
            return $job->type === 'unsubscribe'
                && ($job->payload['email'] ?? null) === 'reader@example.test';
        });
        $this->assertSame($leadId, (int) $contact->fresh()->status_id);

        (new RecordTrackEventJob('unsubscribe', [
            'email' => 'reader@example.test',
        ]))->handle(app(TrackEventRecorder::class));

        $this->assertSame($lostId, (int) $contact->fresh()->status_id);
    }

    private function delivery(): MessageDelivery
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Newsletter',
            'type_id' => 1,
            'text' => 'Hello',
            'team_id' => $team->id,
            'status_id' => 1,
            'enable_open_tracking' => true,
        ]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'reader@acme.test',
        ]);

        return MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
        ]);
    }
}
