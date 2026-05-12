<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserDailyPerformanceInsightPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if (! $user->hasRole('admin') && ! $user->hasRole('root'))
        {
            return false;
        }

        $team = $user->currentTeam;

        return $team && $team->hasModule('performance_insights');
    }

    public function view(User $user, UserDailyPerformanceInsight $insight): bool
    {
        return $this->viewAny($user)
            && (int) $insight->team_id === (int) ($user->currentTeam?->id ?? 0);
    }
}
