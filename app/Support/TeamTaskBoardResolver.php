<?php

namespace App\Support;

use App\Models\Project;
use App\Models\TaskBoard;

/**
 * Resolves the task board for new tasks on a team without relying on HTTP auth() scopes.
 * Prefers the team's default board that is not dedicated to a project kanban (visible on Tablero general).
 */
class TeamTaskBoardResolver
{
    public static function resolveBoardId(int $teamId): int
    {
        $projectBoardIds = Project::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('board_id')
            ->pluck('board_id')
            ->unique()
            ->values()
            ->all();

        $baseQuery = TaskBoard::withoutGlobalScopes()->where('team_id', $teamId);

        if ($projectBoardIds !== [])
        {
            $board = (clone $baseQuery)
                ->where('is_default', true)
                ->whereNotIn('id', $projectBoardIds)
                ->first();

            if ($board !== null)
            {
                return (int) $board->id;
            }

            $board = (clone $baseQuery)
                ->whereNotIn('id', $projectBoardIds)
                ->orderBy('order')
                ->orderBy('id')
                ->first();

            if ($board !== null)
            {
                return (int) $board->id;
            }
        } else
        {
            $board = (clone $baseQuery)->where('is_default', true)->first();

            if ($board !== null)
            {
                return (int) $board->id;
            }

            $board = (clone $baseQuery)->orderBy('order')->orderBy('id')->first();

            if ($board !== null)
            {
                return (int) $board->id;
            }
        }

        $created = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Default',
            'description' => 'Default board',
            'is_default' => true,
            'order' => 0,
        ]);

        return (int) $created->id;
    }
}
