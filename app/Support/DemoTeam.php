<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;

class DemoTeam
{
    public const TEAM_NAME = 'Demo';

    /** @var list<string> */
    public const ADMIN_EMAILS = [
        'admin@humano.app',
        'victor@machbel.com',
        'diego.mascarenhas@icloud.com',
    ];

    public static function isDemoTeam(?Team $team): bool
    {
        return $team !== null && $team->name === self::TEAM_NAME;
    }

    public static function trimAdministrators(Team $team): int
    {
        if (! self::isDemoTeam($team))
        {
            return 0;
        }

        $demoted = 0;

        foreach ($team->allUsers() as $user)
        {
            if (in_array($user->email, self::ADMIN_EMAILS, true))
            {
                continue;
            }

            if (! $user->hasRole('admin'))
            {
                continue;
            }

            $team->users()->updateExistingPivot($user->id, ['role' => 'employee']);

            $otherTeams = $user->teams()->where('teams.id', '!=', $team->id)->count();
            if ($otherTeams === 0)
            {
                $user->removeRole('admin');

                if (! $user->hasRole('employee'))
                {
                    $user->assignRole('employee');
                }
            }

            $demoted++;
        }

        UserDailyPerformanceInsight::query()
            ->where('team_id', $team->id)
            ->whereNotIn('user_id', User::query()->whereIn('email', self::ADMIN_EMAILS)->pluck('id'))
            ->delete();

        return $demoted;
    }
}
