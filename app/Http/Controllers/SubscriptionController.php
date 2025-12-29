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

        // Get only active subscription (exclude canceled)
        $subscription = $team->subscriptions()
            ->where('type', 'default')
            ->where('stripe_status', '!=', 'canceled')
            ->first();

        // Get current plan from active subscription or fallback to free
        if ($subscription && $subscription->active())
        {
            $currentPlan = EmailPlan::fromStripePriceId($subscription->stripe_price);
        } else
        {
            $currentPlan = EmailPlan::FREE;
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
     * Show billing info form before checkout
     */
    public function billingInfo(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,foundation,scale',
        ]);

        $team = auth()->user()->currentTeam;
        $plan = $request->plan;
        $prices = $this->getStripePrices();

        // Get customer data from Stripe if exists
        $customerData = [
            'individual_name' => '',
            'business_name' => '',
            'country' => '',
            'phone' => '',
            'tax_id' => '',
        ];

        if ($team->stripe_id)
        {
            try
            {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $customer = \Stripe\Customer::retrieve($team->stripe_id);

                $customerData = [
                    'individual_name' => $customer->metadata->individual_name ?? '',
                    'business_name' => $customer->metadata->business_name ?? '',
                    'country' => $customer->address->country ?? '',
                    'phone' => $customer->phone ?? '',
                    'tax_id' => '',
                ];

                // Get Tax ID if exists
                $taxIds = \Stripe\Customer::allTaxIds($team->stripe_id, ['limit' => 1]);
                if (! empty($taxIds->data))
                {
                    $customerData['tax_id'] = $taxIds->data[0]->value;
                }
            } catch (\Exception $e)
            {
                \Log::warning('Error fetching customer data from Stripe: '.$e->getMessage());
            }
        }

        return view('subscription.billing-info', [
            'team' => $team,
            'plan' => $plan,
            'prices' => $prices,
            'customerData' => $customerData,
        ]);
    }

    /**
     * Save billing info and redirect to checkout
     */
    public function saveBillingInfo(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,foundation,scale',
            'individual_name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'country' => 'required|string|size:2',
            'phone' => 'required|string|max:50',
            'tax_id' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($request)
                {
                    $country = $request->country;
                    $taxId = preg_replace('/[^0-9A-Za-z]/', '', $value); // Remove special characters for validation

                    // Validation rules by country
                    $valid = match ($country)
                    {
                        'AR' => $this->validateCUIT($taxId), // Argentina: CUIT (11 digits)
                        'ES' => $this->validateCIF_NIF($taxId), // Spain: CIF/NIF
                        'MX' => $this->validateRFC($taxId), // Mexico: RFC (12-13 characters)
                        'CL' => $this->validateRUT($taxId), // Chile: RUT
                        'CO' => $this->validateNIT($taxId), // Colombia: NIT
                        'PE' => $this->validateRUC($taxId), // Peru: RUC (11 digits)
                        'UY' => $this->validateRUT_UY($taxId), // Uruguay: RUT
                        default => strlen($taxId) >= 5, // Generic: at least 5 characters
                    };

                    if (! $valid)
                    {
                        $fail('El formato de la Identificación Fiscal no es válido para el país seleccionado.');
                    }
                },
            ],
        ]);

        $team = auth()->user()->currentTeam;

        // Use business name if provided, otherwise use individual name
        $displayName = $request->business_name ?: $request->individual_name;

        // Ensure team has a Stripe customer
        if (! $team->stripe_id)
        {
            try
            {
                $team->createAsStripeCustomer([
                    'name' => $displayName,
                    'email' => auth()->user()->email,
                ]);
            } catch (\Exception $e)
            {
                \Log::error('Error creating Stripe customer: '.$e->getMessage());
            }
        }

        // Update Stripe customer with billing info
        if ($team->stripe_id)
        {
            try
            {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));

                // Update customer basic info
                \Stripe\Customer::update($team->stripe_id, [
                    'name' => $displayName,
                    'phone' => $request->phone,
                    'address' => [
                        'country' => $request->country,
                    ],
                ]);

                // Add or update Tax ID
                try
                {
                    // Get existing tax IDs
                    $taxIds = \Stripe\Customer::allTaxIds($team->stripe_id, ['limit' => 100]);

                    // Delete existing tax IDs
                    foreach ($taxIds->data as $taxId)
                    {
                        \Stripe\Customer::deleteTaxId($team->stripe_id, $taxId->id);
                    }

                    // Map country code to Stripe tax ID type
                    $taxIdType = match ($request->country)
                    {
                        'AR' => 'ar_cuit',
                        'ES' => in_array(strtoupper(substr($request->tax_id, 0, 1)), ['X', 'Y', 'Z']) ? 'es_cif' : 'eu_vat',
                        'MX' => 'mx_rfc',
                        'CL' => 'cl_tin',
                        'CO' => 'co_nit',
                        'PE' => 'pe_ruc',
                        'UY' => 'uy_ruc',
                        'US' => 'us_ein',
                        default => 'unknown',
                    };

                    // Create new tax ID
                    if ($taxIdType !== 'unknown')
                    {
                        \Stripe\Customer::createTaxId($team->stripe_id, [
                            'type' => $taxIdType,
                            'value' => $request->tax_id,
                        ]);
                    }
                } catch (\Exception $e)
                {
                    \Log::warning('Error updating tax ID: '.$e->getMessage());
                }

                // Update metadata
                \Stripe\Customer::update($team->stripe_id, [
                    'metadata' => [
                        'individual_name' => $request->individual_name,
                        'business_name' => $request->business_name,
                        'tax_id' => $request->tax_id,
                        'country' => $request->country,
                    ],
                ]);

                \Log::info('Stripe customer updated successfully', [
                    'customer_id' => $team->stripe_id,
                    'name' => $displayName,
                    'tax_id' => $request->tax_id,
                ]);
            } catch (\Exception $e)
            {
                \Log::error('Error updating Stripe customer: '.$e->getMessage());

                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Error al actualizar los datos en Stripe: '.$e->getMessage());
            }
        }

        // Redirect to checkout
        return redirect()->route('subscription.checkout', ['plan' => $request->plan]);
    }

    /**
     * Validate Argentina CUIT format
     */
    private function validateCUIT(string $taxId): bool
    {
        // CUIT: 11 digits
        return strlen($taxId) === 11 && ctype_digit($taxId);
    }

    /**
     * Validate Spain CIF/NIF format
     */
    private function validateCIF_NIF(string $taxId): bool
    {
        // CIF/NIF: 8-9 characters (letter + numbers or numbers + letter)
        return preg_match('/^[A-Z0-9]{8,9}$/i', $taxId);
    }

    /**
     * Validate Mexico RFC format
     */
    private function validateRFC(string $taxId): bool
    {
        // RFC: 12-13 characters
        return strlen($taxId) >= 12 && strlen($taxId) <= 13 && preg_match('/^[A-Z0-9]+$/i', $taxId);
    }

    /**
     * Validate Chile RUT format
     */
    private function validateRUT(string $taxId): bool
    {
        // RUT: 8-9 digits + verification digit
        return strlen($taxId) >= 8 && strlen($taxId) <= 10 && preg_match('/^[0-9]{7,9}[0-9Kk]$/i', $taxId);
    }

    /**
     * Validate Colombia NIT format
     */
    private function validateNIT(string $taxId): bool
    {
        // NIT: 9-10 digits
        return strlen($taxId) >= 9 && strlen($taxId) <= 10 && ctype_digit($taxId);
    }

    /**
     * Validate Peru RUC format
     */
    private function validateRUC(string $taxId): bool
    {
        // RUC: 11 digits
        return strlen($taxId) === 11 && ctype_digit($taxId);
    }

    /**
     * Validate Uruguay RUT format
     */
    private function validateRUT_UY(string $taxId): bool
    {
        // RUT Uruguay: 12 digits
        return strlen($taxId) === 12 && ctype_digit($taxId);
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

            // Ensure team has a Stripe customer ID
            if (! $team->stripe_id)
            {
                $team->createAsStripeCustomer();
            }

            // Create Stripe checkout session directly via API
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            $checkoutSession = \Stripe\Checkout\Session::create([
                'customer' => $team->stripe_id,
                'mode' => 'subscription',
                'locale' => 'es',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => route('subscription.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.index'),
                'subscription_data' => [
                    'metadata' => [
                        'team_id' => $team->id,
                    ],
                ],
            ]);

            return redirect($checkoutSession->url);
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
