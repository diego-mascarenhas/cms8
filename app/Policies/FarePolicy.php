<?php

namespace App\Policies;

use App\Models\Fare;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FarePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'collaborator', 'client']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Fare $fare): bool
    {
        return $user->hasAnyRole(['admin', 'collaborator', 'client']) && 
               $user->currentTeam->id === $fare->team_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'collaborator']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Fare $fare): bool
    {
        return $user->hasAnyRole(['admin', 'collaborator']) && 
               $user->currentTeam->id === $fare->team_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Fare $fare): bool
    {
        return $user->hasAnyRole(['admin', 'collaborator']) && 
               $user->currentTeam->id === $fare->team_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Fare $fare): bool
    {
        return $user->hasRole('admin') && 
               $user->currentTeam->id === $fare->team_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Fare $fare): bool
    {
        return $user->hasRole('admin') && 
               $user->currentTeam->id === $fare->team_id;
    }
} 