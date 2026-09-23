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
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return null;  // Continue to specific policy methods
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessBilling() || $user->hasRole('client');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->canAccessBilling())
        {
            return $invoice->team_id === $user->currentTeam?->id;
        }

        if ($user->hasRole('client'))
        {
            return in_array($invoice->enterprise_id, self::clientEnterpriseIds($user), true);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canAccessBilling();
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
            if ($user->canAccessBilling())
            {
                return $query->where('team_id', $user->currentTeam->id);
            }

            if ($user->hasRole('client'))
            {
                $enterpriseIds = self::clientEnterpriseIds($user);

                return $enterpriseIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('enterprise_id', $enterpriseIds);
            }

            return $query->whereRaw('1 = 0');
        };
    }

    /**
     * @return array<int, int>
     */
    public static function clientEnterpriseIds(User $user): array
    {
        $contact = $user->contact;
        if (! $contact)
        {
            return [];
        }

        return $contact->enterprises()->pluck('enterprises.id')->map(fn ($id) => (int) $id)->all();
    }
}
