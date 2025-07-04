<?php

namespace App\Policies;

use App\Models\Software;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SoftwarePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any software.
     */
    public function viewAny(User $user)
    {
        // Allow admin, collaborator, and client roles to view software
        return $user->hasRole(['admin', 'collaborator', 'client']);
    }

    /**
     * Determine whether the user can view the software.
     */
    public function view(User $user, Software $software)
    {
        // Allow admin, collaborator, and client roles to view software from their team
        if (!$user->hasRole(['admin', 'collaborator', 'client'])) {
            return false;
        }

        // Check if the software belongs to the user's current team
        return $software->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can create software.
     */
    public function create(User $user)
    {
        // Allow admin and collaborator to create software
        return $user->hasRole(['admin', 'collaborator']);
    }

    /**
     * Determine whether the user can update the software.
     */
    public function update(User $user, Software $software)
    {
        // Allow admin and collaborator to update software from their team
        if (!$user->hasRole(['admin', 'collaborator'])) {
            return false;
        }

        // Check if the software belongs to the user's current team
        return $software->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can delete the software.
     */
    public function delete(User $user, Software $software)
    {
        // Allow admin and collaborator to delete software from their team
        if (!$user->hasRole(['admin', 'collaborator'])) {
            return false;
        }

        // Check if the software belongs to the user's current team
        return $software->team_id === $user->currentTeam->id;
    }

    /**
     * Get the query filter for the user's role.
     */
    public function getQueryFilter(User $user)
    {
        if ($user->hasRole('admin')) {
            // Admin can see all software from their team (already filtered by global scope)
            return null;
        }

        if ($user->hasRole(['collaborator', 'client'])) {
            // Collaborators and clients see software from their team (already filtered by global scope)
            return null;
        }

        // Default: no access
        return function ($query) {
            return $query->whereRaw('1 = 0'); // No results
        };
    }
} 