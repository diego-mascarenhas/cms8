<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;

class ServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(['admin', 'collaborator', 'client'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Service $service): bool
    {
        // Admin can see all services
        if ($user->hasRole('admin')) {
            return true;
        }

        // Collaborator can see services they are responsible for
        if ($user->hasRole('collaborator')) {
            return $service->responsible_id === $user->id;
        }

        // Client can see services of their enterprise
        if ($user->hasRole('client')) {
            // Get user's contact
            $contact = $user->contact;
            if (!$contact) {
                return false;
            }

            // Check if service belongs to any of the contact's enterprises
            $enterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();
            return in_array($service->enterprise_id, $enterpriseIds);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'collaborator']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Service $service): bool
    {
        // Admin can update all services
        if ($user->hasRole('admin')) {
            return true;
        }

        // Collaborator can update services they are responsible for
        if ($user->hasRole('collaborator')) {
            return $service->responsible_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Service $service): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Service $service): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Service $service): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Get query filter for role-based access
     */
    public static function getQueryFilter(User $user): \Closure
    {
        return function (Builder $query) use ($user) {
            if ($user->hasRole('admin')) {
                // Admin can see all services in their team
                return $query;
            }

            if ($user->hasRole('collaborator')) {
                // Collaborator can see services they are responsible for
                return $query->where('responsible_id', $user->id);
            }

            if ($user->hasRole('client')) {
                // Client can see services of their enterprises
                $contact = $user->contact;
                if (!$contact) {
                    return $query->whereRaw('1 = 0'); // Return no results
                }

                $enterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();
                return $query->whereIn('enterprise_id', $enterpriseIds);
            }

            // Other roles have no access
            return $query->whereRaw('1 = 0');
        };
    }
}
