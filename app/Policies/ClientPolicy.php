<?php

namespace App\Policies;

use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
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
     * Determine whether the user can create enterprises (clients).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'collaborator']);
    }

    /**
     * Determine whether the user can view any enterprises.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(['admin', 'collaborator', 'client']))
        {
            return true;
        }

        return false;
    }

    public function view(User $user, Enterprise $client)
    {
        if ($user->hasRole('admin'))
        {
            return $client->team_id === $user->currentTeam->id;
        }

        if ($user->hasRole('collaborator'))
        {
            return $client->assigned_to == $user->id && $client->team_id === $user->currentTeam->id;
        }

        // Client can see enterprises they belong to
        if ($user->hasRole('client'))
        {
            $contact = $user->contact;
            if (! $contact)
            {
                return false;
            }

            $enterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();

            return in_array($client->id, $enterpriseIds) && $client->team_id === $user->currentTeam->id;
        }

        return false;
    }

    public function manage(User $user, Enterprise $client)
    {
        return $user->hasRole('collaborator') && $client->assigned_to == $user->id;
    }

    /**
     * Determine whether the user can open the edit form (same rules as update).
     */
    public function edit(User $user, Enterprise $client): bool
    {
        return $this->update($user, $client);
    }

    /**
     * Determine whether the user can update the client (enterprise type client).
     */
    public function update(User $user, Enterprise $client): bool
    {
        if ($client->type_id !== 1)
        {
            return false;
        }

        if ($user->hasRole('admin'))
        {
            return $client->team_id === $user->currentTeam->id;
        }

        if ($user->hasRole('collaborator'))
        {
            return $client->assigned_to == $user->id && $client->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Get query filter for role-based access
     */
    public static function getQueryFilter(User $user)
    {
        return function ($query) use ($user)
        {
            // Admin can see all enterprises in their team
            if ($user->hasRole('admin'))
            {
                return $query->where('team_id', $user->currentTeam->id);
            }

            // Collaborator can see enterprises they are assigned to
            if ($user->hasRole('collaborator'))
            {
                return $query->where('team_id', $user->currentTeam->id)
                    ->where('assigned_to', $user->id);
            }

            // Client can see enterprises they belong to
            if ($user->hasRole('client'))
            {
                $contact = $user->contact;
                if (! $contact)
                {
                    return $query->whereRaw('1 = 0'); // No results
                }

                $enterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();

                return $query->where('team_id', $user->currentTeam->id)
                    ->whereIn('id', $enterpriseIds);
            }

            // No access
            return $query->whereRaw('1 = 0');
        };
    }
}
