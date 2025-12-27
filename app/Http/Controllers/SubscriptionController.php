<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use Illuminate\Http\Request;
use Stripe\Stripe;

class SubscriptionController extends Controller
{
    /**
     * Display the subscription plans page
     */
    public function index()
    {
        $team = auth()->user()->currentTeam;
        $currentPlan = EmailPlan::from($team->email_plan ?? 'free');

        // Get plan usage configuration
        $planConfig = [
            'monthly_limit' => $currentPlan->getMonthlyLimit(),
            'monthly_used' => (int) $team->getSetting('email_monthly_used', 0),
            'daily_limit' => $currentPlan->getDailyLimit(),
            'daily_used' => (int) $team->getSetting('email_daily_used', 0),
            'contact_limit' => $currentPlan->getContactLimit(),
        ];

        // Get subscription info if exists
        $subscription = $team->subscription('default');
        $stripeSubscription = null;

        if ($subscription && $subscription->stripe_id)
        {
            try
            {
                Stripe::setApiKey(config('cashier.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);
            } catch (\Exception $e)
            {
                \Log::error('Error fetching Stripe subscription: '.$e->getMessage());
            }
        }

        // Get prices from Stripe API
        $prices = $this->getStripePrices();

        return view('subscription.index', [
            'team' => $team,
            'currentPlan' => $currentPlan,
            'planConfig' => $planConfig,
            'subscription' => $subscription,
            'stripeSubscription' => $stripeSubscription,
            'plans' => EmailPlan::getAll(),
            'prices' => $prices,
        ]);
    }

    /**
     * Get prices from Stripe API for each plan
     */
    private function getStripePrices(): array
    {
        Stripe::setApiKey(config('cashier.secret'));

        $prices = [
            'basic' => null,
            'foundation' => null,
            'scale' => null,
        ];

        try
        {
            foreach (EmailPlan::getAll() as $plan)
            {
                if ($plan->isPaid())
                {
                    $priceId = $plan->getStripePriceId();
                    if ($priceId)
                    {
                        try
                        {
                            $priceData = \Stripe\Price::retrieve($priceId);
                            $prices[$plan->value] = [
                                'amount' => $priceData->unit_amount / 100, // Convert from cents to euros
                                'currency' => strtoupper($priceData->currency),
                            ];
                        } catch (\Exception $e)
                        {
                            \Log::warning("Error fetching price for {$plan->value}: ".$e->getMessage());
                        }
                    }
                }
            }
        } catch (\Exception $e)
        {
            \Log::error('Error fetching Stripe prices: '.$e->getMessage());
        }

        return $prices;
    }

    /**
     * Create a checkout session for upgrading to a paid plan
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,foundation,scale',
        ]);

        $team = auth()->user()->currentTeam;
        $plan = EmailPlan::from($request->plan);

        // Check if already has active subscription
        if ($team->subscribed('default'))
        {
            return redirect()->route('subscription.index')
                ->with('error', 'You already have an active subscription. Please manage your current plan first.');
        }

        try
        {
            // Get the Stripe price ID directly from the plan
            $priceId = $plan->getStripePriceId();

            if (! $priceId || str_contains($priceId, 'REPLACE_ME'))
            {
                return redirect()->route('subscription.index')
                    ->with('error', 'Este plan aún no está configurado. Por favor, configure los Price IDs de Stripe en su archivo .env');
            }

            // Create Stripe checkout session
            $checkout = $team->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => route('subscription.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('subscription.index'),
                ]);

            return redirect($checkout->url);
        } catch (\Exception $e)
        {
            \Log::error('Checkout error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error creating checkout session: '.$e->getMessage());
        }
    }

    /**
     * Handle successful subscription
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');

        if (! $sessionId)
        {
            return redirect()->route('subscription.index');
        }

        $team = auth()->user()->currentTeam;

        try
        {
            Stripe::setApiKey(config('cashier.secret'));
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            // Verify the session belongs to this team
            if ($session->customer !== $team->stripe_id)
            {
                return redirect()->route('subscription.index')
                    ->with('error', 'Invalid session.');
            }

            // Get the subscription ID from the session
            $subscriptionId = $session->subscription;

            if ($subscriptionId)
            {
                // Retrieve the full subscription from Stripe
                $stripeSubscription = \Stripe\Subscription::retrieve([
                    'id' => $subscriptionId,
                    'expand' => ['items.data.price.product'],
                ]);

                // Sync subscription to local database if it doesn't exist
                $localSubscription = $team->subscription('default');

                if (! $localSubscription)
                {
                    // Create the subscription record manually
                    $team->subscriptions()->create([
                        'user_id' => $team->owner->id ?? $team->user_id,
                        'type' => 'default',
                        'stripe_id' => $stripeSubscription->id,
                        'stripe_status' => $stripeSubscription->status,
                        'stripe_price' => $stripeSubscription->items->data[0]->price->id,
                        'quantity' => $stripeSubscription->items->data[0]->quantity,
                        'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                        'ends_at' => null,
                    ]);
                }

                // Get product ID to determine the plan
                $productId = $stripeSubscription->items->data[0]->price->product;

                // Map product ID to EmailPlan
                $plan = $this->getEmailPlanFromProductId($productId);

                if ($plan)
                {
                    // Assign the plan to the team
                    $team->assignEmailPlan($plan, auth()->id());
                }
            }

            return redirect()->route('subscription.index')
                ->with('success', 'Subscription activated successfully!');
        } catch (\Exception $e)
        {
            \Log::error('Success handler error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error processing subscription: '.$e->getMessage());
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel()
    {
        $team = auth()->user()->currentTeam;

        if (! $team->subscribed('default'))
        {
            return redirect()->route('subscription.index')
                ->with('error', 'No active subscription found.');
        }

        try
        {
            $team->subscription('default')->cancel();

            return redirect()->route('subscription.index')
                ->with('success', 'Subscription cancelled. You will continue to have access until the end of your billing period.');
        } catch (\Exception $e)
        {
            \Log::error('Cancel error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error cancelling subscription: '.$e->getMessage());
        }
    }

    /**
     * Resume a cancelled subscription
     */
    public function resume()
    {
        $team = auth()->user()->currentTeam;
        $subscription = $team->subscription('default');

        if (! $subscription || ! $subscription->onGracePeriod())
        {
            return redirect()->route('subscription.index')
                ->with('error', 'No cancelled subscription found.');
        }

        try
        {
            $subscription->resume();

            return redirect()->route('subscription.index')
                ->with('success', 'Subscription resumed successfully!');
        } catch (\Exception $e)
        {
            \Log::error('Resume error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error resuming subscription: '.$e->getMessage());
        }
    }

    /**
     * Update subscription to a different plan
     */
    public function swap(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,foundation,scale',
        ]);

        $team = auth()->user()->currentTeam;

        if (! $team->subscribed('default'))
        {
            return redirect()->route('subscription.index')
                ->with('error', 'No active subscription found. Please subscribe first.');
        }

        $plan = EmailPlan::from($request->plan);

        try
        {
            // Get the Stripe price ID directly from the plan
            $priceId = $plan->getStripePriceId();

            if (! $priceId || str_contains($priceId, 'REPLACE_ME'))
            {
                return redirect()->route('subscription.index')
                    ->with('error', 'Este plan aún no está configurado. Por favor, configure los Price IDs de Stripe en su archivo .env');
            }

            // Swap subscription
            $team->subscription('default')->swap($priceId);

            // Update the team's email plan
            $team->assignEmailPlan($plan, auth()->id());

            return redirect()->route('subscription.index')
                ->with('success', 'Plan updated successfully!');
        } catch (\Exception $e)
        {
            \Log::error('Swap error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error updating plan: '.$e->getMessage());
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
}
