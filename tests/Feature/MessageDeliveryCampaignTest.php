<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageDeliveryCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    #[Test]
    public function same_message_and_contact_allow_two_deliveries_for_different_campaigns(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Template',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $campaignA = Campaign::factory()->create(['team_id' => $teamId]);
        $campaignB = Campaign::factory()->create(['team_id' => $teamId]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaignA->id,
            'status_id' => 1,
        ]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaignB->id,
            'status_id' => 1,
        ]);

        $this->assertSame(2, MessageDelivery::where('message_id', $message->id)->where('contact_id', $contact->id)->count());
        $this->assertSame(1, $campaignA->deliveries()->count());
        $this->assertSame(1, $campaignB->deliveries()->count());
    }

    #[Test]
    public function duplicate_delivery_same_campaign_raises_integrity_exception(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Template',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $campaign = Campaign::factory()->create(['team_id' => $teamId]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
        ]);
    }
}
