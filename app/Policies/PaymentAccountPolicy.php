<?php

namespace App\Policies;

use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentAccountPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('root'))
        {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canAccessBilling();
    }

    public function view(User $user, PaymentAccount $paymentAccount): bool
    {
        return $user->canAccessBilling()
            && (int) $paymentAccount->team_id === (int) $user->currentTeam?->id;
    }

    public function create(User $user): bool
    {
        return $user->canAccessBilling();
    }

    public function update(User $user, PaymentAccount $paymentAccount): bool
    {
        return $user->canAccessBilling()
            && (int) $paymentAccount->team_id === (int) $user->currentTeam?->id;
    }

    public function delete(User $user, PaymentAccount $paymentAccount): bool
    {
        return $user->canAccessBilling()
            && (int) $paymentAccount->team_id === (int) $user->currentTeam?->id;
    }
}
