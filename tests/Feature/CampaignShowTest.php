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
use Tests\TestCase;

class CampaignShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_campaign_show_renders_statistics_and_linked_messages(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Mailing linked',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
        ]);

        $contactA = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $contactB = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Show Stats Campaign',
        ]);

        $campaign->messages()->syncWithoutDetaching([$message->id]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contactA->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
            'sent_at' => now()->subHour(),
            'delivered_at' => now()->subHour(),
            'opened_at' => now()->subMinutes(5),
        ]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contactB->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
            'sent_at' => now()->subHour(),
            'delivered_at' => now()->subHour(),
            'clicked_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.show', $campaign));

        $response->assertOk();
        $response->assertViewHas('deliveryStats', function (array $stats): bool
        {
            return $stats['total'] === 2
                && $stats['unique_recipients'] === 2
                && $stats['sent'] === 2
                && $stats['delivered'] === 2
                && $stats['opened'] === 1
                && $stats['clicked'] === 1;
        });
        $response->assertSee('Show Stats Campaign', false);
        $response->assertSee('Mailing linked', false);
        $response->assertSee(route('message.show', $message->id), false);
    }
}
