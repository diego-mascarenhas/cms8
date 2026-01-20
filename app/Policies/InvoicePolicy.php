<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InvoicePolicy
{
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
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only admin can manage invoices
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Only admin can view invoices
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Only admin can update invoices
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Get query filter for role-based access
     */
    public static function getQueryFilter(User $user): \Closure
    {
        return function (Builder $query) use ($user)
        {
            if ($user->hasRole('admin'))
            {
                // Admin can see all invoices in their team
                return $query->whereHas('enterprise', function ($q) use ($user)
                {
                    $q->where('team_id', $user->current_team_id);
                });
            }

            // Other roles have no access
            return $query->whereRaw('1 = 0');
        };
    }
}
