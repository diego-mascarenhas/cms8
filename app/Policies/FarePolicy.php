<?php

namespace App\Policies;

use App\Models\Fare;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FarePolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Admins have full access to everything in their team.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return null; // Continue to specific policy methods
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->canAccessBilling())
        {
            return false;
        }

        return $user->hasAnyRole(['admin', 'client']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Fare $fare): bool
    {
        if (! $user->canAccessBilling())
        {
            return false;
        }

        return $user->hasAnyRole(['admin', 'client']) &&
            $user->currentTeam->id === $fare->team_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (! $user->canAccessBilling())
        {
            return false;
        }

        return $user->hasAnyRole(['admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Fare $fare): bool
    {
        if (! $user->canAccessBilling())
        {
            return false;
        }

        // Admin can update any fare in their team
        if ($user->hasRole('admin') && $user->currentTeam->id === $fare->team_id)
        {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Fare $fare): bool
    {
        if (! $user->canAccessBilling())
        {
            return false;
        }

        return $user->hasAnyRole(['admin']) &&
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
