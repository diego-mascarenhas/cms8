<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OpportunityPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical', 'client']);
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $this->canAccessTeamOpportunity($user, $opportunity);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'technical']);
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $this->canManageTeamOpportunity($user, $opportunity);
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $this->canManageTeamOpportunity($user, $opportunity);
    }

    private function canAccessTeamOpportunity(User $user, Opportunity $opportunity): bool
    {
        if ($opportunity->team_id !== $user->currentTeam->id)
        {
            return false;
        }

        if ($user->hasRole('collaborator') && $opportunity->responsible_id !== $user->id)
        {
            return false;
        }

        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical', 'client']);
    }

    private function canManageTeamOpportunity(User $user, Opportunity $opportunity): bool
    {
        if ($opportunity->team_id !== $user->currentTeam->id)
        {
            return false;
        }

        if ($user->hasRole('collaborator') && $opportunity->responsible_id !== $user->id)
        {
            return false;
        }

        return $user->hasRole(['collaborator', 'developer', 'technical']);
    }
}
