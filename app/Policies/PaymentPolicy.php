<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Admins and root have full access within their team.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('root'))
        {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any payments (payments, income and expense lists).
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessBilling();
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $user->canAccessBilling()
            && $payment->team_id === $user->currentTeam?->id;
    }

    /**
     * Determine whether the user can create payments.
     */
    public function create(User $user): bool
    {
        return $user->canAccessBilling();
    }

    /**
     * Determine whether the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $user->canAccessBilling()
            && $payment->team_id === $user->currentTeam?->id;
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->canAccessBilling()
            && $payment->team_id === $user->currentTeam?->id;
    }
}
