<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Models\Team;
use App\Services\AffiliateCommissionRecorder;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    /**
     * Handle invoice payment succeeded event
     */
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        if ($this->isInvalidInvoice($payload))
        {
            return;
        }

        $invoice = $payload['data']['object'];
        $customerId = $invoice['customer'];

        $team = Team::findByStripeCustomerId($customerId);

        if (! $team)
        {
            \Log::warning('Stripe webhook: Team not found for customer '.$customerId);

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
     * Handle customer subscription deleted event (when subscription expires)
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'];

        $team = Team::findByStripeCustomerId($customerId);

        if (! $team)
        {
            \Log::warning('Stripe webhook: Team not found for customer '.$customerId);

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
            \Log::warning('Stripe webhook: Team not found for customer '.$customerId);

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
}
