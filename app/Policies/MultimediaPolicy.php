<?php

namespace App\Policies;

use App\Models\Multimedia;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MultimediaPolicy
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

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Multimedia $multimedia): bool
    {
        if ($user->hasRole(['collaborator', 'developer', 'editor', 'technical']))
        {
            return $multimedia->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Multimedia $multimedia): bool
    {
        if ($user->hasRole(['collaborator', 'developer', 'editor', 'technical']))
        {
            return $multimedia->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Multimedia $multimedia): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Multimedia $multimedia): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Multimedia $multimedia): bool
    {
        return false;
    }
}
