<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentPolicy
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

    public function view(User $user, Content $content): bool
    {
        if ($user->hasRole(['collaborator', 'developer', 'editor', 'technical']))
        {
            return $content->team_id === $user->currentTeam->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    public function update(User $user, Content $content): bool
    {
        if ($user->hasRole(['collaborator', 'developer', 'editor', 'technical']))
        {
            return $content->team_id === $user->currentTeam->id;
        }

        return false;
    }

    public function delete(User $user, Content $content): bool
    {
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return $content->team_id === $user->currentTeam->id;
        }

        return false;
    }

    public function restore(User $user, Content $content): bool
    {
        return false;
    }

    public function forceDelete(User $user, Content $content): bool
    {
        return false;
    }
}
