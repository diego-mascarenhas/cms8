<?php

namespace Tests\Unit;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignEffectiveStatusTest extends TestCase
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

    /**
     * @return array{0: User, 1: int}
     */
    private function userAndTeamId(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        return [$user->fresh(), (int) $user->current_team_id];
    }

    public function test_stored_sent_is_not_overridden_by_messages(): void
    {
        [, $teamId] = $this->userAndTeamId();
        $campaign = Campaign::factory()->sentWithMetrics()->create(['team_id' => $teamId]);
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 0,
        ]);
        $campaign->messages()->attach($message->id);

        $this->assertSame(CampaignStatus::Sent, $campaign->fresh()->effectiveStatus());
    }

    public function test_derived_active_when_message_operational_even_if_db_paused(): void
    {
        [, $teamId] = $this->userAndTeamId();
        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'status' => CampaignStatus::Paused->value,
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 1,
            'started_at' => now(),
        ]);
        $campaign->messages()->attach($message->id);

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->effectiveStatus());
    }

    public function test_pending_launch_when_message_on_but_not_yet_operational(): void
    {
        [, $teamId] = $this->userAndTeamId();
        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'status' => CampaignStatus::Active->value,
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 1,
            'started_at' => null,
        ]);
        $campaign->messages()->attach($message->id);

        $this->assertSame(CampaignStatus::PendingLaunch, $campaign->fresh()->effectiveStatus());
    }

    public function test_derived_paused_when_all_messages_stopped_after_start(): void
    {
        [, $teamId] = $this->userAndTeamId();
        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'status' => CampaignStatus::Active->value,
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 0,
            'started_at' => now(),
        ]);
        $campaign->messages()->attach($message->id);

        $this->assertSame(CampaignStatus::Paused, $campaign->fresh()->effectiveStatus());
    }

    public function test_not_started_messages_show_active_not_paused(): void
    {
        [, $teamId] = $this->userAndTeamId();
        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'status' => CampaignStatus::Paused->value,
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 'x',
            'team_id' => $teamId,
            'status_id' => 0,
            'started_at' => null,
        ]);
        $campaign->messages()->attach($message->id);

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->effectiveStatus());
    }
}
