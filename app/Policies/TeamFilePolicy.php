<?php

namespace App\Policies;

use App\Models\TeamFile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamFilePolicy
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
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    public function view(User $user, TeamFile $teamFile): bool
    {
        if ($user->hasRole(['collaborator', 'developer', 'editor', 'technical']))
        {
            return $teamFile->team_id === $user->currentTeam->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    public function update(User $user, TeamFile $teamFile): bool
    {
        if ($user->hasRole(['collaborator', 'developer', 'editor', 'technical']))
        {
            return $teamFile->team_id === $user->currentTeam->id;
        }

        return false;
    }

    public function delete(User $user, TeamFile $teamFile): bool
    {
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return $teamFile->team_id === $user->currentTeam->id;
        }

        return false;
    }

    public function restore(User $user, TeamFile $teamFile): bool
    {
        return false;
    }

    public function forceDelete(User $user, TeamFile $teamFile): bool
    {
        return false;
    }
}
