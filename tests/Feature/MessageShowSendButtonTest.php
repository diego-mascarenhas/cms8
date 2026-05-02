<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageShowSendButtonTest extends TestCase
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

    public function test_send_now_visible_when_message_linked_to_campaign_and_not_operational(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Linked mail',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => false,
        ]);

        $campaign = Campaign::factory()->create(['team_id' => $teamId]);
        $campaign->messages()->attach($message->id);

        $response = $this->actingAs($user)->get(route('message.show', $message->id));

        $response->assertOk();
        $response->assertSee('Enviar ahora', false);
    }

    public function test_send_now_visible_when_message_not_linked_to_campaign(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Standalone mail',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->get(route('message.show', $message->id));

        $response->assertOk();
        $response->assertSee('Enviar ahora', false);
    }
}
