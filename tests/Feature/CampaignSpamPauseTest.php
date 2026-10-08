<?php

namespace Tests\Feature;

use App\Jobs\SendMessageCampaignJob;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\Mail\CampaignMessageApiService;
use App\Services\Mail\MessageCampaignActivationService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CampaignSpamPauseTest extends TestCase
{
    use RefreshDatabase;

    private const SPAM_ERROR = 'Expected response code "250" but got code "550", with message "550 This message was classified as rSPAM and may not be delivered https://mail.outboundspamprotec"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
        ]);
    }

    public function test_provider_spam_rejection_matches_the_mailbaby_550(): void
    {
        $this->assertTrue(Message::isProviderSpamRejection(self::SPAM_ERROR));
        $this->assertFalse(Message::isProviderSpamRejection('550 5.1.1 The email account that you tried to reach does not exist.'));
        $this->assertFalse(Message::isCriticalError(self::SPAM_ERROR));
    }

    public function test_the_first_spam_rejection_pauses_the_campaign_and_stops_new_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Structured',
            'type_id' => 1,
            'text' => 'Hello',
            'team_id' => $team->id,
            'status_id' => 1,
            'started_at' => now()->subHour(),
        ]);
        $sent = $this->contact($user, 'sent-person@acme.test');
        $waiting = $this->contact($user, 'waiting-person@acme.test');
        $delivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $sent->id,
            'status_id' => 2,
            'scheduled_for' => now()->subMinute(),
        ]);

        $delivery->markAsError(self::SPAM_ERROR);

        $this->assertFalse((bool) $message->fresh()->status_id);
        $this->assertSame(Message::PAUSE_REASON_SPAM, $message->fresh()->pause_reason);
        $this->assertSame(1, MessageDelivery::query()->where('message_id', $message->id)->count());

        $this->artisan('campaigns:process-active', ['--message' => $message->id])->assertSuccessful();

        $this->assertSame(1, MessageDelivery::query()->where('message_id', $message->id)->count());
        $this->assertNull(
            MessageDelivery::query()->where('message_id', $message->id)->where('contact_id', $waiting->id)->first(),
        );
    }

    public function test_a_recipient_reject_does_not_pause_the_campaign(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Structured',
            'type_id' => 1,
            'text' => 'Hello',
            'team_id' => $team->id,
            'status_id' => 1,
            'started_at' => now()->subHour(),
        ]);
        $contact = $this->contact($user, 'missing-person@acme.test');
        $delivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
        ]);

        $delivery->markAsError('550 5.1.1 The email account that you tried to reach does not exist.');

        $this->assertTrue((bool) $message->fresh()->status_id);
        $this->assertNull($message->fresh()->pause_reason);
    }

    public function test_scheduled_sender_skips_a_paused_campaign(): void
    {
        Bus::fake();
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $paused = Message::withoutGlobalScopes()->create([
            'name' => 'Paused',
            'type_id' => 1,
            'text' => 'Hello',
            'team_id' => $team->id,
            'status_id' => 0,
            'started_at' => now()->subHour(),
        ]);
        $active = Message::withoutGlobalScopes()->create([
            'name' => 'Active',
            'type_id' => 1,
            'text' => 'Hello',
            'team_id' => $team->id,
            'status_id' => 1,
            'started_at' => now()->subHour(),
        ]);
        $pausedContact = $this->contact($user, 'paused-person@acme.test');
        $activeContact = $this->contact($user, 'active-person@acme.test');
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $paused->id,
            'contact_id' => $pausedContact->id,
            'status_id' => 1,
            'scheduled_for' => now()->subMinute(),
        ]);
        $activeDelivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $active->id,
            'contact_id' => $activeContact->id,
            'status_id' => 1,
            'scheduled_for' => now()->subMinute(),
        ]);

        $this->artisan('campaigns:send-scheduled')->assertSuccessful();

        Bus::assertNotDispatched(
            SendMessageCampaignJob::class,
            fn (SendMessageCampaignJob $job): bool => (int) $job->messageDelivery->message_id === (int) $paused->id,
        );
        Bus::assertDispatched(
            SendMessageCampaignJob::class,
            fn (SendMessageCampaignJob $job): bool => (int) $job->messageDelivery->id === (int) $activeDelivery->id,
        );
    }

    private function contact(User $user, string $email): Contact
    {
        return Contact::factory()->create([
            'team_id' => $user->ownedTeams()->first()->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => $email,
        ]);
    }
}
