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
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignShowSendToolbarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            SourceSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_campaign_show_displays_send_now_when_linked_message_not_started(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Newsletter paso 1',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 0,
            'started_at' => null,
        ]);

        $campaign = Campaign::factory()->create(['team_id' => $teamId]);
        $campaign->messages()->attach($message->id);

        $response = $this->actingAs($user)->get(route('campaigns.show', $campaign));

        $response->assertOk();
        $response->assertSee('Enviar ahora', false);
        $response->assertSee('data-campaign-toolbar="send-now"', false);
        $response->assertDontSee('data-campaign-toolbar="pause"', false);
    }

    public function test_campaign_show_displays_pause_when_linked_message_active_with_deliveries(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Newsletter activa',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 1,
            'started_at' => now(),
        ]);

        $campaign = Campaign::factory()->create(['team_id' => $teamId]);
        $campaign->messages()->attach($message->id);

        $contact = Contact::factory()->create(['team_id' => $teamId]);

        MessageDelivery::query()->create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.show', $campaign));

        $response->assertOk();
        $response->assertSee('data-campaign-toolbar="pause"', false);
        $response->assertSee('data-campaign-toolbar="recalculate"', false);
        $response->assertDontSee('data-campaign-toolbar="send-now"', false);
    }

    public function test_pause_messages_route_pauses_linked_messages(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Para pausar',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 1,
            'started_at' => now(),
        ]);

        $campaign = Campaign::factory()->create(['team_id' => $teamId]);
        $campaign->messages()->attach($message->id);

        $response = $this->actingAs($user)->postJson(route('campaigns.pause-messages', $campaign));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $message->refresh();
        $this->assertFalse((bool) $message->status_id);
    }
}
