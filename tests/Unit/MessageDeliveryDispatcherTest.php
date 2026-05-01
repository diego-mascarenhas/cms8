<?php

namespace Tests\Unit;

use App\Enums\MessageDeliverySendProfile;
use App\Jobs\SendMessageCampaignJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\MessageDeliveryDispatcher;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessageDeliveryDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('message_type')->updateOrInsert(['id' => 1], ['name' => 'Mailer', 'status' => 1]);

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    private function actingTeamContext(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_auto_profile_uses_message_queue_when_delivery_has_no_campaign(): void
    {
        Bus::fake();
        config([
            'message_delivery_dispatch.connection' => null,
            'message_delivery_dispatch.message.queue' => 'q-message',
            'message_delivery_dispatch.campaign.queue' => 'q-campaign',
        ]);

        $user = $this->actingTeamContext();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 1,
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'solo-a@example.test',
            'country' => 724,
            'language' => 'es',
        ]);

        $delivery = MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => null,
            'status_id' => 1,
        ]);

        app(MessageDeliveryDispatcher::class)->enqueue($delivery, MessageDeliverySendProfile::Auto);

        Bus::assertDispatched(SendMessageCampaignJob::class, function (SendMessageCampaignJob $job): bool
        {
            return $job->queue === 'q-message';
        });
    }

    public function test_auto_profile_uses_campaign_queue_when_delivery_linked_to_campaign(): void
    {
        Bus::fake();
        config([
            'message_delivery_dispatch.connection' => null,
            'message_delivery_dispatch.message.queue' => 'q-message',
            'message_delivery_dispatch.campaign.queue' => 'q-campaign',
        ]);

        $user = $this->actingTeamContext();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 1,
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'camp-b@example.test',
            'country' => 724,
            'language' => 'es',
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Seq',
        ]);

        $delivery = MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
        ]);

        app(MessageDeliveryDispatcher::class)->enqueue($delivery, MessageDeliverySendProfile::Auto);

        Bus::assertDispatched(SendMessageCampaignJob::class, function (SendMessageCampaignJob $job): bool
        {
            return $job->queue === 'q-campaign';
        });
    }

    public function test_explicit_message_profile_overrides_campaign_id(): void
    {
        Bus::fake();
        config([
            'message_delivery_dispatch.connection' => null,
            'message_delivery_dispatch.message.queue' => 'q-message',
            'message_delivery_dispatch.campaign.queue' => 'q-campaign',
        ]);

        $user = $this->actingTeamContext();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 1,
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'prior-c@example.test',
            'country' => 724,
            'language' => 'es',
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Seq',
        ]);

        $delivery = MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
        ]);

        app(MessageDeliveryDispatcher::class)->enqueue($delivery, MessageDeliverySendProfile::Message);

        Bus::assertDispatched(SendMessageCampaignJob::class, function (SendMessageCampaignJob $job): bool
        {
            return $job->queue === 'q-message';
        });
    }
}
