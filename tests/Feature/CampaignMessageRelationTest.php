<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignMessageRelationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    #[Test]
    public function message_can_belong_to_multiple_campaigns(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user);

        $teamId = (int) $user->current_team_id;

        $message = Message::create([
            'name' => 'Shared send',
            'type_id' => 1,
            'text' => 'Hello',
            'team_id' => $teamId,
        ]);

        $campaignA = Campaign::factory()->create(['team_id' => $teamId]);
        $campaignB = Campaign::factory()->create(['team_id' => $teamId]);

        $campaignA->messages()->attach($message->id);
        $campaignB->messages()->attach($message->id);

        $message->unsetRelation('campaigns')->load('campaigns');

        $this->assertSame(2, $message->campaigns()->count());

        foreach ($message->campaigns as $campaign)
        {
            $campaign->unsetRelation('messages')->loadCount('messages');
            $this->assertSame(1, $campaign->messages_count);
        }
    }

    #[Test]
    public function attaching_same_pair_twice_fails_constraint(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user);

        $teamId = (int) $user->current_team_id;

        $message = Message::create([
            'name' => 'Dup',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
        ]);

        $campaign = Campaign::factory()->create(['team_id' => $teamId]);
        $campaign->messages()->attach($message->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $campaign->messages()->attach($message->id);
    }
}
