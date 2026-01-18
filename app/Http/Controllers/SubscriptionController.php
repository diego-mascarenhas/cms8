<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Models\SubscriptionProduct;
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
            ->where('type', 'mailer')
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

        // Get products from database (synced from Stripe)
        $products = SubscriptionProduct::active()->get();

        // Get all active subscription products grouped by category
        $mentoringProducts = SubscriptionProduct::active()
            ->where('category', 'mentoring')
            ->orderBy('unit_amount')
            ->get();

        $mailerProducts = SubscriptionProduct::active()
            ->where('category', 'mailer')
            ->orderBy('unit_amount')
            ->get();

        $hostingProducts = SubscriptionProduct::active()
            ->whereIn('category', ['hosting', 'support'])
            ->orderByRaw("CASE WHEN category = 'hosting' THEN 0 ELSE 1 END")
            ->orderBy('unit_amount', 'desc')
            ->get();

        // Get active subscriptions for each category
        $activeSubscriptions = $team->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->get()
            ->filter(fn ($sub) => $sub->active());

        // Get active mentoring subscription to determine current plan
        $mentoringSubscription = $activeSubscriptions->filter(function ($sub) use ($mentoringProducts)
        {
            return $mentoringProducts->contains(function ($product) use ($sub)
            {
                return $product->stripe_price === $sub->stripe_price;
            });
        })->first();

        $currentMentoringPlan = null;
        if ($mentoringSubscription)
        {
            $mentoringProduct = $mentoringProducts->firstWhere('stripe_price', $mentoringSubscription->stripe_price);
            $currentMentoringPlan = $mentoringProduct?->plan;
        }

        // Get active hosting subscription
        $hostingSubscription = $activeSubscriptions->filter(function ($sub) use ($hostingProducts)
        {
            return $hostingProducts->contains(function ($product) use ($sub)
            {
                return $product->stripe_price === $sub->stripe_price;
            });
        })->first();

        // Fallback to EmailPlan if no products synced yet
        $plans = $products->isEmpty() ? EmailPlan::getAll() : null;
        $prices = $this->getStripePrices();

        return view('subscription.index', [
            'team' => $team,
            'currentPlan' => $currentPlan,
            'planConfig' => $planConfig,
            'subscription' => $subscription,
            'stripeSubscription' => $stripeSubscription,
            'plans' => $plans,
            'products' => $products,
            'prices' => $prices,
            'mentoringProducts' => $mentoringProducts,
            'mailerProducts' => $mailerProducts,
            'hostingProducts' => $hostingProducts,
            'currentMentoringPlan' => $currentMentoringPlan,
            'mentoringSubscription' => $mentoringSubscription,
            'hostingSubscription' => $hostingSubscription,
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
     * Check if customer has complete billing info in Stripe
     */
    private function hasCompleteBillingInfo($team): bool
    {
        if (! $team->stripe_id)
        {
            return false;
        }

        try
        {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $customer = \Stripe\Customer::retrieve($team->stripe_id);

            // Check if we have all required fields
            $hasName = ! empty($customer->metadata->individual_name ?? $customer->name ?? '');
            $hasCountry = ! empty($customer->address->country ?? '');
            $hasPhone = ! empty($customer->phone ?? '');
            $hasTaxId = false;

            // Check if tax ID exists
            $taxIds = \Stripe\Customer::allTaxIds($team->stripe_id, ['limit' => 1]);
            if (! empty($taxIds->data))
            {
                $hasTaxId = true;
            }

            return $hasName && $hasCountry && $hasPhone && $hasTaxId;
        } catch (\Exception $e)
        {
            \Log::warning('Error checking billing info: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Show billing info form before checkout
     */
    public function billingInfo(Request $request)
    {
        $request->validate([
            'plan' => 'nullable|in:basic,foundation,scale',
            'product_id' => 'nullable|exists:subscription_products,id',
            'price_id' => 'nullable|string',
            'domain' => 'nullable|string|max:255',
        ]);

        $team = auth()->user()->currentTeam;
        $plan = $request->plan;
        $product = null;
        $prices = $this->getStripePrices();

        // Get product if product_id is provided
        if ($request->product_id)
        {
            $product = SubscriptionProduct::findOrFail($request->product_id);
        } elseif ($request->price_id)
        {
            $product = SubscriptionProduct::where('stripe_price', $request->price_id)->first();
        }

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
            'product' => $product,
            'prices' => $prices,
            'customerData' => $customerData,
            'domain' => $request->domain,
        ]);
    }

    /**
     * Save billing info and redirect to checkout
     */
    public function saveBillingInfo(Request $request)
    {
        $request->validate([
            'plan' => 'nullable|in:basic,foundation,scale',
            'product_id' => 'nullable|exists:subscription_products,id',
            'price_id' => 'nullable|string',
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

        // Normalize phone number to E.164 format
        $phone = $this->normalizePhoneNumber($request->phone, $request->country);

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
                    'phone' => $phone,
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

        // Redirect to checkout (preserve domain if provided)
        $redirectParams = [];
        if ($request->product_id)
        {
            $redirectParams['product_id'] = $request->product_id;
        } elseif ($request->price_id)
        {
            $redirectParams['price_id'] = $request->price_id;
        } else
        {
            $redirectParams['plan'] = $request->plan;
        }
        if ($request->domain)
        {
            $redirectParams['domain'] = $request->domain;
        }

        return redirect()->route('subscription.checkout', $redirectParams);
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
            'plan' => 'nullable|in:basic,foundation,scale',
            'product_id' => 'nullable|exists:subscription_products,id',
            'price_id' => 'nullable|string',
            'domain' => 'nullable|string|max:255',
        ]);

        $team = auth()->user()->currentTeam;
        $priceId = null;
        $product = null;
        $subscriptionType = 'mailer';

        // If product_id is provided, get product and use its stripe_price
        if ($request->product_id)
        {
            $product = SubscriptionProduct::findOrFail($request->product_id);
            $priceId = $product->stripe_price;
            $subscriptionType = $product->category ?? 'mailer';
        }
        // If price_id is provided directly, use it
        elseif ($request->price_id)
        {
            $priceId = $request->price_id;
            $product = SubscriptionProduct::where('stripe_price', $priceId)->first();
            if ($product)
            {
                $subscriptionType = $product->category ?? 'mailer';
            }
        }
        // Otherwise, use the plan parameter (for Mailer plans)
        elseif ($request->plan)
        {
            $plan = EmailPlan::from($request->plan);
            $priceId = $plan->getStripePriceId();
            $subscriptionType = 'mailer';
        } else
        {
            return redirect()->route('subscription.index')
                ->with('error', 'Debes especificar un plan o producto.');
        }

        if (! $priceId || str_contains($priceId, 'REPLACE_ME'))
        {
            return redirect()->route('subscription.index')
                ->with('error', 'Este plan aún no está configurado. Por favor, configure los Price IDs de Stripe.');
        }

        // Validate domain for hosting/support products
        if ($product && in_array($product->category, ['hosting', 'support']) && ! $request->domain)
        {
            // Redirect back with product_id to show modal with error
            $redirectParams = ['product_id' => $request->product_id];
            if ($request->domain)
            {
                $redirectParams['domain'] = $request->domain;
            }

            return redirect()->route('subscription.index', $redirectParams)
                ->with('error', 'Debes especificar un dominio para este servicio.');
        }

        // For hosting/support products, allow multiple subscriptions (one per domain)
        // For other products, check if already has an active subscription of the same type
        $isHostingOrSupport = $product && in_array($product->category, ['hosting', 'support']);

        if (! $isHostingOrSupport)
        {
            // Check if already has an active subscription of the same type (not canceled)
            $activeSubscription = $team->subscriptions()
                ->where('stripe_status', '!=', 'canceled')
                ->get()
                ->filter(function ($sub) use ($subscriptionType, $product)
                {
                    // For same category subscriptions, check if active
                    if ($product)
                    {
                        $subProduct = SubscriptionProduct::where('stripe_price', $sub->stripe_price)->first();
                        if ($subProduct && $subProduct->category === $product->category)
                        {
                            return $sub->active();
                        }
                    }
                    // For mailer, use the existing logic
                    if ($subscriptionType === 'mailer' && $sub->type === 'mailer')
                    {
                        return $sub->active();
                    }

                    return false;
                })
                ->first();

            // If already has an active subscription of the same type, automatically swap instead of showing error
            if ($activeSubscription)
            {
                // Redirect to swap method to upgrade/downgrade
                $swapRequest = new Request;
                if ($request->product_id)
                {
                    $swapRequest->merge(['product_id' => $request->product_id]);
                } elseif ($request->price_id)
                {
                    $swapRequest->merge(['price_id' => $request->price_id]);
                } elseif ($request->plan)
                {
                    $swapRequest->merge(['plan' => $request->plan]);
                }

                return $this->swap($swapRequest);
            }
        }

        // Validate domain for hosting/support products
        if ($product && in_array($product->category, ['hosting', 'support']))
        {
            try
            {
                $request->validate([
                    'domain' => [
                        'required',
                        'string',
                        'max:255',
                        function ($attribute, $value, $fail)
                        {
                            if (empty($value))
                            {
                                return;
                            }

                            // Clean domain
                            $domain = trim($value);
                            // Remove protocol if present
                            $domain = preg_replace('#^https?://#', '', $domain);
                            // Remove trailing slash
                            $domain = rtrim($domain, '/');
                            // Remove www. if present
                            $domain = preg_replace('#^www\.#', '', $domain);

                            // Validate domain format: alphanumeric, dots, hyphens, at least one dot, valid TLD
                            if (! preg_match('/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain))
                            {
                                $fail('El formato del dominio no es válido. Debe ser algo como: ejemplo.com');
                            }
                        },
                    ],
                ], [
                    'domain.required' => 'Debes especificar un dominio para este servicio.',
                    'domain.string' => 'El dominio debe ser una cadena de texto válida.',
                    'domain.max' => 'El dominio no puede exceder 255 caracteres.',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e)
            {
                // Redirect back with errors and product_id to show modal
                return redirect()->route('subscription.index', ['product_id' => $request->product_id])
                    ->withErrors($e->errors())
                    ->withInput();
            }

            // Clean and store domain
            $domain = trim($request->domain);
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');
            $domain = preg_replace('#^www\.#', '', $domain);
            $request->merge(['domain' => $domain]);
        }

        // If customer has payment method, create subscription directly (skip checkout)
        // This prevents creating duplicate payment methods
        if ($team->stripe_id)
        {
            try
            {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $customer = \Stripe\Customer::retrieve($team->stripe_id);

                // Get default payment method or first available payment method
                $paymentMethodId = $customer->invoice_settings->default_payment_method;

                \Log::info('Checking payment methods', [
                    'team_id' => $team->id,
                    'stripe_customer_id' => $team->stripe_id,
                    'default_payment_method' => $paymentMethodId,
                ]);

                // If no default payment method, try to get the first available one
                if (! $paymentMethodId)
                {
                    $paymentMethods = \Stripe\PaymentMethod::all([
                        'customer' => $team->stripe_id,
                        'type' => 'card',
                        'limit' => 10, // Get more to find an active one
                    ]);

                    \Log::info('Found payment methods', [
                        'team_id' => $team->id,
                        'count' => count($paymentMethods->data),
                        'payment_methods' => array_map(fn ($pm) => [
                            'id' => $pm->id,
                            'type' => $pm->type,
                            'card_last4' => $pm->card->last4 ?? null,
                        ], $paymentMethods->data),
                    ]);

                    // Find the first active payment method
                    foreach ($paymentMethods->data as $pm)
                    {
                        if ($pm->card && ! empty($pm->card->last4))
                        {
                            $paymentMethodId = $pm->id;
                            \Log::info('Selected payment method', [
                                'team_id' => $team->id,
                                'payment_method_id' => $paymentMethodId,
                                'card_last4' => $pm->card->last4,
                            ]);
                            break;
                        }
                    }
                } else
                {
                    \Log::info('Using default payment method', [
                        'team_id' => $team->id,
                        'payment_method_id' => $paymentMethodId,
                    ]);
                }

                // If we have a payment method, create subscription directly
                if ($paymentMethodId)
                {
                    \Log::info('Creating subscription directly with payment method', [
                        'team_id' => $team->id,
                        'payment_method_id' => $paymentMethodId,
                        'subscription_type' => $subscriptionType,
                        'product_id' => $product?->id,
                    ]);

                    // Build metadata
                    $metadata = [
                        'team_id' => $team->id,
                        'subscription_type' => $subscriptionType,
                    ];

                    // Add domain to metadata if provided
                    if ($request->domain)
                    {
                        $metadata['domain'] = $request->domain;
                    }

                    // Create subscription directly using existing payment method
                    $stripeSubscription = \Stripe\Subscription::create([
                        'customer' => $team->stripe_id,
                        'items' => [[
                            'price' => $priceId,
                        ]],
                        'default_payment_method' => $paymentMethodId,
                        'expand' => ['latest_invoice.payment_intent'],
                        'metadata' => $metadata,
                    ]);

                    // Sync subscription to local database
                    $team->subscriptions()->create([
                        'user_id' => $team->owner->id ?? $team->user_id,
                        'type' => $subscriptionType,
                        'stripe_id' => $stripeSubscription->id,
                        'stripe_status' => $stripeSubscription->status,
                        'stripe_price' => $priceId,
                        'quantity' => $stripeSubscription->items->data[0]->quantity,
                        'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                        'ends_at' => null,
                        'data' => $metadata, // Store metadata including domain
                    ]);

                    // Only assign EmailPlan if it's a mailer subscription
                    if ($subscriptionType === 'mailer')
                    {
                        try
                        {
                            $plan = EmailPlan::fromStripePriceId($priceId);
                            $team->assignEmailPlan($plan, auth()->id());
                        } catch (\Exception $e)
                        {
                            \Log::warning('Could not update email_plan: '.$e->getMessage());
                        }
                    }

                    // Custom success message based on subscription type
                    $successMessage = match ($subscriptionType)
                    {
                        'hosting', 'support' => '¡Servicio contratado exitosamente usando tu método de pago guardado!',
                        default => '¡Suscripción activada exitosamente usando tu método de pago guardado!',
                    };

                    return redirect()->route('subscription.index')
                        ->with('success', $successMessage);
                }
            } catch (\Exception $e)
            {
                \Log::warning('Could not create subscription directly, falling back to checkout', [
                    'team_id' => $team->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Fall through to checkout flow
            }
        } else
        {
            \Log::info('No stripe_id for team, going to checkout', [
                'team_id' => $team->id,
            ]);
        }

        // Check if billing info is required (for non-mailer subscriptions)
        // Only check if we didn't create subscription directly (no payment method found)
        if ($subscriptionType !== 'mailer')
        {
            // Check if customer has complete billing info in Stripe
            $hasBillingInfo = $this->hasCompleteBillingInfo($team);

            if (! $hasBillingInfo)
            {
                \Log::info('Billing info incomplete, redirecting to billing-info', [
                    'team_id' => $team->id,
                    'subscription_type' => $subscriptionType,
                ]);

                // Redirect to billing-info page (preserve domain if provided)
                $redirectParams = [];
                if ($request->product_id)
                {
                    $redirectParams['product_id'] = $request->product_id;
                } elseif ($request->price_id)
                {
                    $redirectParams['price_id'] = $request->price_id;
                }
                if ($request->domain)
                {
                    $redirectParams['domain'] = $request->domain;
                }

                return redirect()->route('subscription.billing-info', $redirectParams);
            }
        }

        // Clean up any canceled subscriptions of the same type to avoid conflicts
        if ($subscriptionType === 'mailer')
        {
            $team->subscriptions()
                ->where('type', 'mailer')
                ->where('stripe_status', 'canceled')
                ->delete();
        }

        try
        {
            // Ensure team has a Stripe customer ID
            if (! $team->stripe_id)
            {
                $team->createAsStripeCustomer();
            }

            // Create Stripe checkout session directly via API
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Check if customer has existing payment methods
            $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $team->stripe_id,
                'type' => 'card',
                'limit' => 1,
            ]);

            // Build subscription metadata
            $subscriptionMetadata = [
                'team_id' => $team->id,
                'subscription_type' => $subscriptionType,
            ];

            // Add domain to metadata if provided (for hosting/support products)
            if ($request->domain)
            {
                $subscriptionMetadata['domain'] = $request->domain;
            }

            $checkoutConfig = [
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
                    'metadata' => $subscriptionMetadata,
                ],
            ];

            // If customer has payment methods, allow them to choose
            if (! empty($paymentMethods->data))
            {
                $checkoutConfig['payment_method_types'] = ['card'];
                $checkoutConfig['saved_payment_method_options'] = [
                    'payment_method_save' => 'enabled',
                    'payment_method_remove' => 'enabled',
                ];
            }

            $checkoutSession = \Stripe\Checkout\Session::create($checkoutConfig);

            return redirect($checkoutSession->url);
        } catch (\Exception $e)
        {
            \Log::error('Checkout error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error al crear la sesión de pago: '.$e->getMessage());
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
                    ->with('error', 'Sesión inválida.');
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

                // Get product ID and price ID from Stripe subscription
                $priceId = $stripeSubscription->items->data[0]->price->id;
                $productId = $stripeSubscription->items->data[0]->price->product;

                // Determine subscription type from local product
                $subscriptionProduct = SubscriptionProduct::where('stripe_price', $priceId)
                    ->orWhere('stripe_product', $productId)
                    ->orWhere('stripe_id', $productId)
                    ->first();

                $subscriptionType = 'mailer'; // Default
                if ($subscriptionProduct)
                {
                    $subscriptionType = $subscriptionProduct->category ?? 'mailer';
                }

                // Get metadata from Stripe subscription (includes domain for hosting/support)
                $metadata = [];
                if ($stripeSubscription->metadata)
                {
                    $metadata = $stripeSubscription->metadata->toArray();
                }

                // Sync subscription to local database if it doesn't exist
                $localSubscription = $team->subscriptions()
                    ->where('stripe_id', $stripeSubscription->id)
                    ->first();

                if (! $localSubscription)
                {
                    // Create the subscription record manually
                    $team->subscriptions()->create([
                        'user_id' => $team->owner->id ?? $team->user_id,
                        'type' => $subscriptionType,
                        'stripe_id' => $stripeSubscription->id,
                        'stripe_status' => $stripeSubscription->status,
                        'stripe_price' => $priceId,
                        'quantity' => $stripeSubscription->items->data[0]->quantity,
                        'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                        'ends_at' => null,
                        'data' => $metadata, // Store metadata including domain
                    ]);
                } else
                {
                    // Update existing subscription with metadata if not already set
                    if (empty($localSubscription->data) && ! empty($metadata))
                    {
                        $localSubscription->update(['data' => $metadata]);
                    }
                }

                // Only assign EmailPlan if it's a mailer subscription
                if ($subscriptionType === 'mailer')
                {
                    // Map product ID to EmailPlan
                    $plan = $this->getEmailPlanFromProductId($productId);

                    if ($plan)
                    {
                        // Assign the plan to the team
                        $team->assignEmailPlan($plan, auth()->id());
                    }
                }
            }

            return redirect()->route('subscription.index')
                ->with('success', '¡Suscripción activada exitosamente!');
        } catch (\Exception $e)
        {
            \Log::error('Success handler error: '.$e->getMessage());

            return redirect()->route('subscription.index')
                ->with('error', 'Error al procesar la suscripción: '.$e->getMessage());
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request)
    {
        $team = auth()->user()->currentTeam;

        try
        {
            // Get subscription by stripe_id if provided, otherwise fallback to mailer
            $stripeId = $request->input('stripe_id');

            if ($stripeId)
            {
                // Find by stripe_id (supports any subscription type)
                $subscription = $team->subscriptions()
                    ->where('stripe_id', $stripeId)
                    ->where('stripe_status', 'active')
                    ->whereNull('ends_at')
                    ->first();
            } else
            {
                // Fallback: find active mailer subscription
                $subscription = $team->subscriptions()
                    ->where('type', 'mailer')
                    ->where('stripe_status', 'active')
                    ->whereNull('ends_at')
                    ->first();
            }

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
                ->where('type', 'mailer')
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
            'plan' => 'nullable|in:basic,foundation,scale',
            'product_id' => 'nullable|exists:subscription_products,id',
            'price_id' => 'nullable|string',
        ]);

        $team = auth()->user()->currentTeam;
        $priceId = null;
        $product = null;
        $subscriptionType = 'mailer';

        // Determine priceId and subscriptionType
        if ($request->product_id)
        {
            $product = SubscriptionProduct::findOrFail($request->product_id);
            $priceId = $product->stripe_price;
            $subscriptionType = $product->category ?? 'mailer';
        } elseif ($request->price_id)
        {
            $priceId = $request->price_id;
            $product = SubscriptionProduct::where('stripe_price', $priceId)->first();
            if ($product)
            {
                $subscriptionType = $product->category ?? 'mailer';
            }
        } elseif ($request->plan)
        {
            $plan = EmailPlan::from($request->plan);
            $priceId = $plan->getStripePriceId();
            $subscriptionType = 'mailer';
        } else
        {
            return redirect()->route('subscription.index')
                ->with('error', 'Debes especificar un plan o producto para cambiar.');
        }

        if (! $priceId || str_contains($priceId, 'REPLACE_ME'))
        {
            return redirect()->route('subscription.index')
                ->with('error', 'Este plan aún no está configurado. Por favor, configure los Price IDs de Stripe.');
        }

        try
        {
            // Get subscription by type
            $subscription = $team->subscriptions()
                ->where('stripe_status', '!=', 'canceled')
                ->get()
                ->filter(function ($sub) use ($subscriptionType, $product)
                {
                    // For same category subscriptions, check if active
                    if ($product)
                    {
                        $subProduct = SubscriptionProduct::where('stripe_price', $sub->stripe_price)->first();
                        if ($subProduct && $subProduct->category === $product->category)
                        {
                            return $sub->active();
                        }
                    }
                    // For mailer, use the existing logic
                    if ($subscriptionType === 'mailer' && $sub->type === 'mailer')
                    {
                        return $sub->active();
                    }

                    return false;
                })
                ->first();

            if (! $subscription)
            {
                return redirect()->route('subscription.index')
                    ->with('error', 'No se encontró una suscripción activa de este tipo. Por favor, crea una nueva suscripción primero.');
            }

            // Update directly via Stripe API to avoid Billable model issues
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);

            // Update subscription with new price and ensure it's not set to cancel
            \Stripe\Subscription::update($subscription->stripe_id, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $priceId,
                    ],
                ],
                'cancel_at_period_end' => false, // Always remove cancellation
                'proration_behavior' => 'create_prorations',
            ]);

            // Update local database
            $subscription->stripe_price = $priceId;
            $subscription->stripe_status = 'active';
            $subscription->ends_at = null; // Always clear cancellation date
            $subscription->save();

            // Update the team's email plan if it's a mailer subscription
            if ($subscriptionType === 'mailer')
            {
                try
                {
                    $team->email_plan = EmailPlan::fromStripePriceId($priceId)->value;
                    $team->save();
                } catch (\Exception $e)
                {
                    \Log::warning('Could not update email_plan: '.$e->getMessage());
                }
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

    /**
     * Normalize phone number to E.164 format
     */
    private function normalizePhoneNumber(string $phone, string $country): string
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // If already has + at the start, return as is (already in international format)
        if (str_starts_with($cleaned, '+'))
        {
            return $cleaned;
        }

        // Get country code based on country
        $countryCode = match ($country)
        {
            'AR' => '+54',
            'ES' => '+34',
            'MX' => '+52',
            'US' => '+1',
            'CO' => '+57',
            'CL' => '+56',
            'PE' => '+51',
            'UY' => '+598',
            'BR' => '+55',
            default => '',
        };

        // For Argentina, ensure mobile numbers have the '9' prefix after country code
        if ($country === 'AR' && ! str_starts_with($cleaned, '9'))
        {
            // If starts with 15, remove it (old mobile format)
            if (str_starts_with($cleaned, '15'))
            {
                $cleaned = substr($cleaned, 2);
            }
            // Add 9 prefix for mobile
            $cleaned = '9'.$cleaned;
        }

        return $countryCode.$cleaned;
    }
}
