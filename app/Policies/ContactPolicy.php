<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContactPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Admins have full access to everything in their team.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null; // Continue to specific policy methods
    }

    /**
     * Determine whether the user can view any contacts.
     */
    public function viewAny(User $user): bool
    {
        // Admin can see all contacts
        if ($user->hasRole('admin'))
        {
            return true;
        }

        // Collaborators can see contacts (but filtered to their own)
        if ($user->hasRole('collaborator'))
        {
            return true;
        }

        // Clients can see their own contact information
        if ($user->hasRole('client'))
        {
            return true;
        }

        // Developers and editors can view contacts
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the contact.
     */
    public function view(User $user, Contact $contact): bool
    {
        // Admin can see any contact in their team
        if ($user->hasRole('admin'))
        {
            return $contact->team_id === $user->currentTeam->id;
        }

        // Collaborators can only see their own contact
        if ($user->hasRole('collaborator'))
        {
            return $contact->user_id === $user->id && $contact->team_id === $user->currentTeam->id;
        }

        // Clients can see their own contact or contacts from their enterprises
        if ($user->hasRole('client'))
        {
            // Can see their own contact
            if ($contact->user_id === $user->id && $contact->team_id === $user->currentTeam->id)
            {
                return true;
            }

            // Can see contacts from enterprises they belong to
            $userContact = $user->contact;
            if ($userContact)
            {
                $enterpriseIds = $userContact->enterprises()->pluck('enterprises.id')->toArray();
                $contactEnterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();

                return ! empty(array_intersect($enterpriseIds, $contactEnterpriseIds));
            }

            return false;
        }

        // Developers and editors can view contacts in their team
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return $contact->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create contacts.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'collaborator', 'developer', 'technical']);
    }

    /**
     * Determine whether the user can update the contact.
     */
    public function update(User $user, Contact $contact): bool
    {
        // Admin can update any contact in their team
        if ($user->hasRole('admin'))
        {
            return $contact->team_id === $user->currentTeam->id;
        }

        // Collaborators can only update their own contact
        if ($user->hasRole('collaborator'))
        {
            return $contact->user_id === $user->id && $contact->team_id === $user->currentTeam->id;
        }

        // Developers and technical users can update contacts in their team
        if ($user->hasRole(['developer', 'technical']))
        {
            return $contact->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the contact.
     */
    public function delete(User $user, Contact $contact): bool
    {
        // Only admins can delete contacts
        return $user->hasRole('admin') && $contact->team_id === $user->currentTeam->id;
    }

    /**
     * Get the query filter for the user's role.
     */
    public static function getQueryFilter(User $user)
    {
        return function ($query) use ($user)
        {
            // Admin can see all contacts in their team
            if ($user->hasRole('admin'))
            {
                return $query->where('team_id', $user->currentTeam->id);
            }

            // Collaborators can only see their own contact
            if ($user->hasRole('collaborator'))
            {
                return $query->where('team_id', $user->currentTeam->id)
                    ->where('user_id', $user->id);
            }

            // Clients can see their own contact and contacts from their enterprises
            if ($user->hasRole('client'))
            {
                $userContact = $user->contact;
                if (! $userContact)
                {
                    return $query->where('team_id', $user->currentTeam->id)
                        ->where('user_id', $user->id);
                }

                $enterpriseIds = $userContact->enterprises()->pluck('enterprises.id')->toArray();

                return $query->where('team_id', $user->currentTeam->id)
                    ->where(function ($q) use ($user, $enterpriseIds)
                    {
                        $q->where('user_id', $user->id)
                            ->orWhereHas('enterprises', function ($enterpriseQuery) use ($enterpriseIds)
                            {
                                $enterpriseQuery->whereIn('enterprises.id', $enterpriseIds);
                            });
                    });
            }

            // Developers and editors can see all contacts in their team
            if ($user->hasRole(['developer', 'editor', 'technical']))
            {
                return $query->where('team_id', $user->currentTeam->id);
            }

            // No access
            return $query->whereRaw('1 = 0');
        };
    }
}
