<?php

namespace Tests\Unit;

use App\Models\TaskBoard;
use App\Models\Team;
use App\Support\TeamTaskBoardResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTaskBoardResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_scopes_board_to_team(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        $boardB = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamB->id,
            'name' => 'B only',
            'description' => null,
            'is_default' => true,
            'order' => 0,
        ]);

        $boardAId = TeamTaskBoardResolver::resolveBoardId($teamA->id);

        $boardA = TaskBoard::withoutGlobalScopes()->find($boardAId);
        $this->assertNotNull($boardA);
        $this->assertSame($teamA->id, $boardA->team_id);
        $this->assertNotSame($boardB->id, $boardA->id);
    }
}
