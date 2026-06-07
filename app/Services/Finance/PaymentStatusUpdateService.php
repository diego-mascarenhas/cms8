<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PaymentStatusUpdateService
{
    /**
     * @return array<int, string>
     */
    public function selectableStatuses(): array
    {
        return [
            1 => __('In Process'),
            2 => __('Approved'),
            3 => __('Pending'),
            4 => __('Rejected'),
            5 => __('Refunded'),
            6 => __('Cancelled'),
            7 => __('In Mediation'),
            8 => __('Charged Back'),
            9 => __('Insufficient Funds'),
            10 => __('Account Closed'),
            11 => __('Non-existent Account'),
            12 => __('Service Cancelled'),
            13 => __('Unspecified'),
            14 => __('Expired'),
            15 => __('Failed'),
            20 => __('Different Currency'),
        ];
    }

    public function canUpdateStatus(User $user, Payment $payment): bool
    {
        if (! $user->currentTeam || (int) $payment->team_id !== (int) $user->currentTeam->id)
        {
            return false;
        }

        if (! $user->ownsTeam($user->currentTeam))
        {
            return false;
        }

        return (int) $payment->status !== 0;
    }

    public function update(User $user, Payment $payment, int $status): Payment
    {
        if (! $this->canUpdateStatus($user, $payment))
        {
            throw ValidationException::withMessages([
                'status' => __('payment_status.errors.not_allowed'),
            ]);
        }

        if (! array_key_exists($status, $this->selectableStatuses()))
        {
            throw ValidationException::withMessages([
                'status' => __('payment_status.errors.invalid_status'),
            ]);
        }

        $payment->update(['status' => $status]);

        return $payment->refresh();
    }
}
