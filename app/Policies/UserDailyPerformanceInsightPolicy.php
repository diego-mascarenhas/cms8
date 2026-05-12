<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserDailyPerformanceInsightPolicy
{
    use HandlesAuthorization;

    /**
     * Daily insights are not gated by the performance_insights module (hidden from the sidebar);
     * admin and root may open the list route and the scheduled job runs for all teams.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->hasRole('admin') && ! $user->hasRole('root'))
        {
            return false;
        }

        $team = $user->currentTeam;

        return (bool) $team;
    }

    public function view(User $user, UserDailyPerformanceInsight $insight): bool
    {
        return $this->viewAny($user)
            && (int) $insight->team_id === (int) ($user->currentTeam?->id ?? 0);
    }
}
