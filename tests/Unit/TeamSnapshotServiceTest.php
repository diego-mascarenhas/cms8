<?php

namespace Tests\Unit;

use App\Models\TeamSnapshot;
use App\Models\User;
use App\Services\TeamSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_latest_history_and_prune_by_team_type_and_key(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $other = User::factory()->withPersonalTeam()->create()->ownedTeams()->first();
        $service = app(TeamSnapshotService::class);

        for ($i = 1; $i <= 5; $i++)
        {
            $service->store(
                teamId: (int) $team->id,
                type: TeamSnapshot::TYPE_FINANCE_DASHBOARD,
                payload: ['n' => $i],
                key: 'year:2026',
                capturedAt: now()->subHours(6 - $i),
            );
        }

        $service->store(
            teamId: (int) $other->id,
            type: TeamSnapshot::TYPE_FINANCE_DASHBOARD,
            payload: ['n' => 99],
            key: 'year:2026',
        );

        $service->store(
            teamId: (int) $team->id,
            type: TeamSnapshot::TYPE_MAILER_STATS,
            payload: ['opens' => 10],
            key: null,
        );

        $latest = $service->latest((int) $team->id, TeamSnapshot::TYPE_FINANCE_DASHBOARD, 'year:2026');
        $this->assertNotNull($latest);
        $this->assertSame(5, (int) ($latest->payload['n'] ?? 0));

        $history = $service->history((int) $team->id, TeamSnapshot::TYPE_FINANCE_DASHBOARD, 'year:2026', 10);
        $this->assertCount(5, $history);

        $deleted = $service->prune((int) $team->id, TeamSnapshot::TYPE_FINANCE_DASHBOARD, 'year:2026', 2);
        $this->assertSame(3, $deleted);
        $this->assertSame(2, TeamSnapshot::query()
            ->forTeam((int) $team->id)
            ->ofType(TeamSnapshot::TYPE_FINANCE_DASHBOARD)
            ->withKey('year:2026')
            ->count());
        $this->assertSame(1, TeamSnapshot::query()->forTeam((int) $other->id)->count());
        $this->assertSame(1, TeamSnapshot::query()->ofType(TeamSnapshot::TYPE_MAILER_STATS)->count());
    }
}
