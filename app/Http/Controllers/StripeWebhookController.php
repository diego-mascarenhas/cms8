<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Jobs\ProcessStripeInvoiceWebhookJob;
use App\Models\Team;
use App\Services\AffiliateCommissionRecorder;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    /**
     * Handle invoice paid event (includes external / manual payments recorded in Stripe).
     */
    public function handleInvoicePaid(array $payload): void
    {
        $this->dispatchInvoiceSyncJob($payload, 'invoice.paid');
    }

    /**
     * Handle invoice payment succeeded event
     */
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        $this->dispatchInvoiceSyncJob($payload, 'invoice.payment_succeeded');

        if ($this->isInvalidInvoice($payload))
        {
            return;
        }

        $invoice = $payload['data']['object'];
        $customerId = $invoice['customer'];

        $team = Team::findByStripeCustomerId($customerId);

        if (! $team)
        {
            \Log::debug('Stripe webhook: skipping invoice handler — no team for customer yet (async or public payment link before /pricing/checkout/complete)', [
                'customer' => $customerId,
            ]);

            return;
        }

        \Log::info('Invoice payment succeeded for team '.$team->id);

        // Ensure the team's subscription is synced
        $subscription = $team->subscription('mailer');

        if ($subscription && $subscription->active())
        {
            // Get the product ID from the subscription
            $stripeSubscription = $subscription->asStripeSubscription();
            $productId = $stripeSubscription->items->data[0]->price->product ?? null;

            if ($productId)
            {
                $plan = $this->getEmailPlanFromProductId($productId);

                if ($plan && $team->getEmailPlan() !== $plan)
                {
                    // Update the team's email plan
                    $team->assignEmailPlan($plan, null);
                    \Log::info('Updated team '.$team->id.' to '.$plan->value.' plan');
                }
            }
        }

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($team, $invoice);
    }

    /**
     * Refresh core invoice when Stripe marks it paid, void, or uncollectible.
     */
    public function handleInvoiceUpdated(array $payload): void
    {
        $invoice = $payload['data']['object'] ?? [];
        $status = strtolower((string) ($invoice['status'] ?? ''));

        if (
            ($invoice['paid'] ?? false)
            || in_array($status, ['paid', 'void', 'uncollectible'], true)
        ) {
            $this->dispatchInvoiceSyncJob($payload, 'invoice.updated');
        }
    }

    /**
     * Handle customer subscription deleted event (when subscription expires)
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'];

        $team = Team::findByStripeCustomerId($customerId);

        if (! $team)
        {
            \Log::debug('Stripe webhook: skipping subscription deleted — no team for customer', [
                'customer' => $customerId,
            ]);

            return;
        }

        \Log::info('Subscription deleted for team '.$team->id.', reverting to FREE plan');

        // Revert to FREE plan when subscription is deleted/cancelled
        $team->assignEmailPlan(EmailPlan::FREE, null);
    }

    /**
     * Handle customer subscription updated event
     */
    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'];

        $team = Team::findByStripeCustomerId($customerId);

        if (! $team)
        {
            \Log::debug('Stripe webhook: skipping subscription updated — no team for customer', [
                'customer' => $customerId,
            ]);

            return;
        }

        // Check if subscription is still active
        if ($subscription['status'] === 'active')
        {
            // Get the product ID from the subscription
            $productId = $subscription['items']['data'][0]['price']['product'] ?? null;

            if ($productId)
            {
                $plan = $this->getEmailPlanFromProductId($productId);

                if ($plan && $team->getEmailPlan() !== $plan)
                {
                    // Update the team's email plan
                    $team->assignEmailPlan($plan, null);
                    \Log::info('Updated team '.$team->id.' to '.$plan->value.' plan via subscription update');
                }
            }
        } elseif (in_array($subscription['status'], ['canceled', 'unpaid', 'past_due']))
        {
            \Log::info('Subscription status changed to '.$subscription['status'].' for team '.$team->id);

            // Optionally revert to FREE plan on certain statuses
            if ($subscription['status'] === 'canceled' || $subscription['status'] === 'unpaid')
            {
                $team->assignEmailPlan(EmailPlan::FREE, null);
            }
        }
    }

    /**
     * Get EmailPlan from Stripe product ID
     */
    private function getEmailPlanFromProductId(string $productId): ?EmailPlan
    {
        foreach (EmailPlan::getAll() as $plan)
        {
            if ($plan->getStripeProductId() === $productId)
            {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Check if the invoice is invalid (e.g., subscription creation)
     */
    private function isInvalidInvoice(array $payload): bool
    {
        $invoice = $payload['data']['object'];

        // Skip subscription creation invoices
        if (isset($invoice['billing_reason']) && $invoice['billing_reason'] === 'subscription_create')
        {
            return false; // We want to process subscription creation
        }

        return false;
    }

    private function dispatchInvoiceSyncJob(array $payload, string $eventType): void
    {
        $invoice = $payload['data']['object'] ?? null;
        if (! is_array($invoice))
        {
            return;
        }

        ProcessStripeInvoiceWebhookJob::dispatch($invoice, $eventType);
    }
}
