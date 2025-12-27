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

        // Get subscription info if exists
        $subscription = $team->subscription('default');

        // Get current plan from active subscription or fallback to team setting
        if ($subscription && $subscription->active())
        {
            $currentPlan = EmailPlan::fromStripePriceId($subscription->stripe_price);
        } else
        {
            $currentPlan = EmailPlan::from($team->email_plan ?? 'free');
        }

        // Get plan usage configuration
        $planConfig = [
            'monthly_limit' => $currentPlan->getMonthlyLimit(),
            'monthly_used' => (int) $team->getSetting('email_monthly_used', 0),
            'daily_limit' => $currentPlan->getDailyLimit(),
            'daily_used' => (int) $team->getSetting('email_daily_used', 0),
            'contact_limit' => $currentPlan->getContactLimit(),
        ];

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

        try
        {
            // Get subscription directly from database
            $subscription = $team->subscriptions()
                ->where('type', 'default')
                ->where('stripe_status', 'active')
                ->whereNull('ends_at')
                ->first();

            if (! $subscription)
            {
                return redirect()->route('billing.index')
                    ->with('error', 'No se encontró una suscripción activa.');
            }

            // Cancel via Stripe API
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            \Stripe\Subscription::update($subscription->stripe_id, [
                'cancel_at_period_end' => true,
            ]);

            // Update local database
            $subscription->ends_at = \Carbon\Carbon::createFromTimestamp(
                \Stripe\Subscription::retrieve($subscription->stripe_id)->current_period_end,
            );
            $subscription->save();

            return redirect()->route('billing.index')
                ->with('success', 'Suscripción cancelada. Seguirás teniendo acceso hasta el final de tu período de facturación.');
        } catch (\Exception $e)
        {
            \Log::error('Cancel error: '.$e->getMessage());

            return redirect()->route('billing.index')
                ->with('error', 'Error al cancelar la suscripción: '.$e->getMessage());
        }
    }

    /**
     * Resume a cancelled subscription
     */
    public function resume()
    {
        $team = auth()->user()->currentTeam;

        try
        {
            // Get subscription from database
            $subscription = $team->subscriptions()
                ->where('type', 'default')
                ->where('stripe_status', 'active')
                ->whereNotNull('ends_at')
                ->first();

            if (! $subscription)
            {
                return redirect()->route('billing.index')
                    ->with('error', 'No se encontró una suscripción cancelada.');
            }

            // Resume via Stripe API
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            \Stripe\Subscription::update($subscription->stripe_id, [
                'cancel_at_period_end' => false,
            ]);

            // Update local database
            $subscription->ends_at = null;
            $subscription->save();

            return redirect()->route('billing.index')
                ->with('success', '¡Suscripción reanudada exitosamente!');
        } catch (\Exception $e)
        {
            \Log::error('Resume error: '.$e->getMessage());

            return redirect()->route('billing.index')
                ->with('error', 'Error al reanudar la suscripción: '.$e->getMessage());
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

            // Get subscription
            $subscription = $team->subscriptions()
                ->where('type', 'default')
                ->where('stripe_status', 'active')
                ->first();

            if (! $subscription)
            {
                return redirect()->route('subscription.index')
                    ->with('error', 'No se encontró una suscripción activa. Por favor, contacta a soporte.');
            }

            // Update directly via Stripe API to avoid Billable model issues
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);
            \Stripe\Subscription::update($subscription->stripe_id, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $priceId,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
            ]);

            // Update local database
            $subscription->stripe_price = $priceId;
            $subscription->save();

            // Update the team's email plan (bypass admin check for owner)
            try
            {
                $team->email_plan = $plan->value;
                $team->save();
            } catch (\Exception $e)
            {
                \Log::warning('Could not update email_plan: '.$e->getMessage());
            }

            return redirect()->route('subscription.index')
                ->with('success', '¡Plan actualizado exitosamente!');
        } catch (\Exception $e)
        {
            \Log::error('Swap error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error al actualizar el plan: '.$e->getMessage());
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
