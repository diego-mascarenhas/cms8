<?php

namespace App\Policies;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CertificationPolicy
{
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
    public function view(User $user, Certification $certification): bool
    {
        // Check if user has permission and certification belongs to user's team
        return $user->hasAnyRole(['admin', 'collaborator', 'client']) && 
               $certification->team_id === $user->currentTeam->id;
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
    public function update(User $user, Certification $certification): bool
    {
        // Check if user has permission and certification belongs to user's team
        return $user->hasAnyRole(['admin', 'collaborator']) && 
               $certification->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Certification $certification): bool
    {
        // Check if user has permission and certification belongs to user's team
        return $user->hasAnyRole(['admin', 'collaborator']) && 
               $certification->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Certification $certification): bool
    {
        return $user->hasRole('admin') && 
               $certification->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Certification $certification): bool
    {
        return $user->hasRole('admin') && 
               $certification->team_id === $user->currentTeam->id;
    }
}
