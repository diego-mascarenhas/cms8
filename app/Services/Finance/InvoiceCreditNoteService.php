<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\User;

class InvoiceCreditNoteService
{
    /** @var list<int> */
    private const BLOCKED_STATUSES = [3, 4, 5, 6, 9];

    /** @var list<string> */
    public const STRIPE_REASONS = [
        'duplicate',
        'fraudulent',
        'order_change',
        'product_unsatisfactory',
    ];

    public function canShowCreditNoteForm(User $user, Invoice $invoice): bool
    {
        if (! $user->currentTeam || ! $user->ownsTeam($user->currentTeam))
        {
            return false;
        }

        if ((int) $invoice->team_id !== (int) $user->currentTeam->id)
        {
            return false;
        }

        if (! $this->isStripeInvoice($invoice))
        {
            return false;
        }

        if (in_array((int) $invoice->status, self::BLOCKED_STATUSES, true))
        {
            return false;
        }

        if ($invoice->operation !== 'sell')
        {
            return false;
        }

        return true;
    }

    public function canIssueCreditNote(User $user, Invoice $invoice): bool
    {
        if (! $this->canShowCreditNoteForm($user, $invoice))
        {
            return false;
        }

        $secret = trim((string) $user->currentTeam->getSetting('stripe_secret'));

        return $secret !== '';
    }

    public function isStripeInvoice(Invoice $invoice): bool
    {
        if (! filled($invoice->source_reference_id) || ! str_starts_with((string) $invoice->source_reference_id, 'in_'))
        {
            return false;
        }

        if ($invoice->source_provider === 'stripe')
        {
            return true;
        }

        return filled($invoice->source_reference_id);
    }

    public function defaultReason(): string
    {
        return 'order_change';
    }
}
