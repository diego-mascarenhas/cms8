<?php

namespace App\Http\Controllers;

use App\Actions\Subscriptions\SyncStripeSubscriptions as SyncStripeSubscriptionsAction;
use App\DataTables\StripeSubscriptionDataTable;
use App\Enums\EmailPlan;
use App\Enums\ProspectPlan;
use App\Models\Enterprise;
use App\Models\StripeSubscription;
use App\Models\SubscriptionProduct;
use App\Services\Stripe\StripeSubscriptionService;
use App\Services\StripeAccountResolver;
use App\Services\TeamStripeCustomerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Stripe\Stripe;
use Stripe\StripeClient;

class SubscriptionController extends Controller
{
    /**
     * Display client subscriptions (Stripe) list.
     */
    public function index(StripeSubscriptionDataTable $dataTable)
    {
        return $dataTable->render('subscription.index');
    }

    /**
     * Sync client subscriptions from Stripe to local stripe_subscriptions table.
     * Uses the current team's Stripe API key (test or live) from team settings.
     */
    public function syncFromStripe()
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('subscription.index')->with('error', __('stripe_subscription.errors.no_team'));
        }

        $secret = $team->getSetting('stripe_secret');
        if (empty($secret))
        {
            return redirect()->route('subscription.index')->with('error', __('stripe_subscription.errors.no_stripe_secret'));
        }

        $stripeService = new StripeSubscriptionService(new StripeClient($secret));
        $sync = new SyncStripeSubscriptionsAction($stripeService);
        $count = $sync->handle($team);

        return redirect()->route('subscription.index')->with('success', __('stripe_subscription.sync_success', ['count' => $count]));
    }

    /**
     * Form: assign this subscription's Stripe customer id (cus_…) to a local client (enterprise) code.
     */
    public function linkClientForm(StripeSubscription $stripeSubscription)
    {
        $this->denyIfCannotLinkClient();
        $this->ensureStripeSubscriptionInCurrentTeam($stripeSubscription);

        if (! $stripeSubscription->customer_id)
        {
            return redirect()->route('subscription.index')->with('error', __('stripe_subscription.link.errors.missing_stripe_customer'));
        }

        if ($this->isSubscriptionLinkedToEnterprise($stripeSubscription))
        {
            return redirect()->route('subscription.index')->with('error', __('stripe_subscription.link.errors.already_linked'));
        }

        $enterprises = Enterprise::query()
            ->clients()
            ->orderBy('name')
            ->get();

        return view('subscription.link-client', [
            'subscription' => $stripeSubscription,
            'enterprises' => $enterprises,
        ]);
    }

    public function linkClient(Request $request, StripeSubscription $stripeSubscription)
    {
        $this->denyIfCannotLinkClient();
        $this->ensureStripeSubscriptionInCurrentTeam($stripeSubscription);

        if (! $stripeSubscription->customer_id)
        {
            return redirect()->route('subscription.index')->with('error', __('stripe_subscription.link.errors.missing_stripe_customer'));
        }

        if ($this->isSubscriptionLinkedToEnterprise($stripeSubscription))
        {
            return redirect()->route('subscription.index')->with('error', __('stripe_subscription.link.errors.already_linked'));
        }

        $teamId = (int) auth()->user()->currentTeam->id;

        $validated = $request->validate([
            'enterprise_id' => [
                'required',
                'integer',
                Rule::exists('enterprises', 'id')
                    ->where(fn ($q) => $q->where('team_id', $teamId)->where('type_id', 1)->whereNull('deleted_at')),
            ],
        ]);

        $enterprise = Enterprise::query()->findOrFail($validated['enterprise_id']);
        $this->authorize('update', $enterprise);

        $customerId = (string) $stripeSubscription->customer_id;
        if (filled($enterprise->getAttribute('code')) && (string) $enterprise->getAttribute('code') !== $customerId)
        {
            return back()->withInput()->with('error', __('stripe_subscription.link.errors.client_has_different_code'));
        }

        $taken = Enterprise::query()
            ->where('team_id', $teamId)
            ->where('code', $customerId)
            ->where('id', '!=', $enterprise->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($taken)
        {
            return back()->withInput()->with('error', __('stripe_subscription.link.errors.stripe_customer_taken'));
        }

        $enterprise->update([
            'code' => $customerId,
        ]);

        return redirect()->route('subscription.index')->with('success', __('stripe_subscription.link.success'));
    }

    private function denyIfCannotLinkClient(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['admin', 'collaborator']))
        {
            abort(403);
        }
    }

    private function ensureStripeSubscriptionInCurrentTeam(StripeSubscription $stripeSubscription): void
    {
        $teamId = auth()->user()->currentTeam?->id;
        abort_unless($teamId && (int) $stripeSubscription->team_id === (int) $teamId, 403);
    }

    private function isSubscriptionLinkedToEnterprise(StripeSubscription $subscription): bool
    {
        return Enterprise::query()
            ->where('team_id', $subscription->team_id)
            ->where('code', $subscription->customer_id)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Get prospect plan prices from Stripe (Basic, Growth).
     * Returns amount, currency and billing interval (month or quarter when interval_count is 3).
     *
     * @return array<string, array{amount: float, currency: string, interval: string, interval_count: int}|null>
     */
    private function getProspectStripePrices(): array
    {
        Stripe::setApiKey(StripeAccountResolver::secretForCategory('prospecting'));
        $prospectPrices = [
            'basic' => null,
            'growth' => null,
        ];
        try
        {
            foreach ([ProspectPlan::BASIC, ProspectPlan::GROWTH] as $plan)
            {
                $priceId = $plan->getStripePriceId();
                if ($priceId)
                {
                    try
                    {
                        $priceData = \Stripe\Price::retrieve($priceId);
                        $interval = $priceData->recurring->interval ?? 'month';
                        $intervalCount = (int) ($priceData->recurring->interval_count ?? 1);
                        $prospectPrices[$plan->value] = [
                            'amount' => $priceData->unit_amount / 100,
                            'currency' => strtoupper($priceData->currency ?? 'EUR'),
                            'interval' => $interval,
                            'interval_count' => $intervalCount,
                        ];
                    } catch (\Exception $e)
                    {
                        \Log::warning("Error fetching prospect price for {$plan->value}: ".$e->getMessage());
                    }
                }
            }
        } catch (\Exception $e)
        {
            \Log::error('Error fetching prospect Stripe prices: '.$e->getMessage());
        }

        return $prospectPrices;
    }

    /**
     * Get Prospection export product config for the subscription page (price from Stripe when configured).
     *
     * @return array{enabled: bool, name: string, description: string, amount: float|null, currency: string|null, price_id: string|null, app_url: string|null}
     */
    private function getProspectionExportConfig(): array
    {
        $config = config('services.prospect_search', []);
        $priceId = SubscriptionProduct::getProspectionPriceId() ?? $config['export_price_id'] ?? null;
        $name = $config['export_name'] ?? 'Prospection';
        $description = $config['export_description'] ?? 'Exporta tus resultados de búsqueda de prospectos y descarga el CSV con los contactos.';
        $appUrl = $config['access_base_url'] ?? null;

        $amount = null;
        $currency = null;

        if ($priceId)
        {
            try
            {
                Stripe::setApiKey(StripeAccountResolver::secretForCategory('prospecting'));
                $priceData = \Stripe\Price::retrieve($priceId);
                $amount = ($priceData->unit_amount ?? 0) / 100;
                $currency = strtoupper($priceData->currency ?? 'EUR');
            } catch (\Exception $e)
            {
                \Log::warning('Prospection export: could not fetch Stripe price: '.$e->getMessage());
            }
        }

        $credits = (int) ($config['export_credits'] ?? 0);

        return [
            'enabled' => ! empty($priceId) && ! empty($appUrl),
            'name' => $name,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
            'credits' => $credits,
            'price_id' => $priceId,
            'app_url' => $appUrl ? rtrim($appUrl, '/') : null,
        ];
    }

    /**
     * Get prices from Stripe API for each plan
     */
    private function getStripePrices(): array
    {
        Stripe::setApiKey(StripeAccountResolver::secretForCategory('mailer'));

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
     * Resolve Stripe account category from billing/checkout request.
     */
    private function resolveBillingCategory(Request $request, ?SubscriptionProduct $product): string
    {
        if ($request->prospection)
        {
            return 'prospecting';
        }
        if ($request->prospect_plan)
        {
            return 'prospecting';
        }
        if ($product)
        {
            $category = $product->category;

            return StripeAccountResolver::normalizeCategory(
                is_string($category) && trim($category) !== '' ? $category : 'mailer',
            );
        }
        if ($request->plan)
        {
            return 'mailer';
        }

        return 'mailer';
    }

    /**
     * Subscription product row for the configured registration Stripe product (when price_id alone does not match a row).
     */
    private function resolveRegistrationSubscriptionProductFromConfig(): ?SubscriptionProduct
    {
        $stripeProductId = trim((string) config('registration.stripe_product_id', ''));

        if ($stripeProductId === '')
        {
            return null;
        }

        return SubscriptionProduct::query()
            ->where(function ($query) use ($stripeProductId): void
            {
                $query->where('stripe_product', $stripeProductId)
                    ->orWhere('stripe_id', $stripeProductId);
            })
            ->first();
    }

    /**
     * In-app destination when leaving the subscription checkout flow with an error or cancel.
     */
    private function subscriptionCheckoutHomeRoute(Request $request): string
    {
        return $request->boolean('from_registration')
            ? 'registration.billing'
            : 'subscription.index';
    }

    /**
     * Check if customer has complete billing info in Stripe for the given category's account.
     */
    private function hasCompleteBillingInfo($team, string $category): bool
    {
        $customerService = app(TeamStripeCustomerService::class);
        $customerId = $customerService->getStripeCustomerIdForCategory($team, $category);
        if (! $customerId)
        {
            return false;
        }

        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));
            $customer = \Stripe\Customer::retrieve($customerId);

            // Check if we have all required fields
            $hasName = ! empty($customer->metadata->individual_name ?? $customer->name ?? '');
            $hasCountry = ! empty($customer->address->country ?? '');
            $hasPhone = ! empty($customer->phone ?? '');
            $hasTaxId = false;

            // Check if tax ID exists
            $taxIds = \Stripe\Customer::allTaxIds($customerId, ['limit' => 1]);
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
            'prospect_plan' => 'nullable|in:basic,growth',
            'domain' => 'nullable|string|max:255',
            'coupon' => 'nullable|string|max:255',
            'prospection' => 'nullable|in:1',
            'from_registration' => 'nullable|in:1',
        ]);

        $team = auth()->user()->currentTeam;

        // Log for debugging
        \Log::info('Billing info requested', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'stripe_id' => $team->stripe_id,
            'user_id' => auth()->id(),
        ]);

        $plan = $request->plan;
        $product = null;
        $prices = $this->getStripePrices();
        $prospectionConfig = null;

        if ($request->prospection)
        {
            $prospectionConfig = $this->getProspectionExportConfig();
        } elseif ($request->product_id)
        {
            $product = SubscriptionProduct::findOrFail($request->product_id);
        } elseif ($request->price_id)
        {
            $product = SubscriptionProduct::where('stripe_price', $request->price_id)->first();
        }

        if ($product === null && ! $request->prospection && ! $request->filled('prospect_plan'))
        {
            $product = $this->resolveRegistrationSubscriptionProductFromConfig();
        }

        $prospectPlan = ($request->prospect_plan && in_array($request->prospect_plan, ['basic', 'growth'], true))
            ? ProspectPlan::from($request->prospect_plan)
            : null;

        $billingCategory = $this->resolveBillingCategory($request, $product);

        $customerData = [
            'individual_name' => '',
            'business_name' => '',
            'country' => '',
            'phone' => '',
            'tax_id' => '',
        ];

        $customerService = app(TeamStripeCustomerService::class);
        $customerId = $customerService->getStripeCustomerIdForCategory($team, $billingCategory);
        if ($customerId)
        {
            try
            {
                \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($billingCategory));
                $customer = \Stripe\Customer::retrieve($customerId);

                $customerTeamId = $customer->metadata->team_id ?? null;
                if ($customerTeamId && (int) $customerTeamId !== (int) $team->id)
                {
                    \Log::warning('Customer stripe_id does not match team', [
                        'team_id' => $team->id,
                        'customer_team_id' => $customerTeamId,
                        'stripe_customer_id' => $customerId,
                    ]);
                } else
                {
                    $customerData = [
                        'individual_name' => $customer->metadata->individual_name ?? '',
                        'business_name' => $customer->metadata->business_name ?? '',
                        'country' => $customer->address->country ?? '',
                        'phone' => $customer->phone ?? '',
                        'tax_id' => '',
                    ];

                    $taxIds = \Stripe\Customer::allTaxIds($customerId, ['limit' => 1]);
                    if (! empty($taxIds->data))
                    {
                        $customerData['tax_id'] = $taxIds->data[0]->value;
                    }
                }
            } catch (\Exception $e)
            {
                \Log::warning('Error fetching customer data from Stripe: '.$e->getMessage(), [
                    'team_id' => $team->id,
                    'customer_id' => $customerId,
                ]);
            }
        } else
        {
            \Log::info('Team has no Stripe customer for category, using empty customer data', [
                'team_id' => $team->id,
                'category' => $billingCategory,
            ]);
        }

        return view('subscription.billing-info', [
            'team' => $team,
            'plan' => $plan,
            'product' => $product,
            'prospect_plan' => $request->prospect_plan,
            'prospectPlan' => $prospectPlan,
            'prices' => $prices,
            'customerData' => $customerData,
            'domain' => $request->domain,
            'coupon' => $request->coupon,
            'prospection' => (bool) $request->prospection,
            'prospectionConfig' => $prospectionConfig,
            'fromRegistration' => $request->boolean('from_registration'),
            'registrationCheckoutPriceId' => $request->filled('price_id') ? (string) $request->price_id : null,
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
            'prospect_plan' => 'nullable|in:basic,growth',
            'domain' => 'nullable|string|max:255',
            'coupon' => 'nullable|string|max:255',
            'prospection' => 'nullable|in:1',
            'from_registration' => 'nullable|in:1',
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

        $product = null;
        if ($request->product_id)
        {
            $product = SubscriptionProduct::find($request->product_id);
        } elseif ($request->price_id)
        {
            $product = SubscriptionProduct::where('stripe_price', $request->price_id)->first();
        }
        if ($product === null && ! $request->boolean('prospection') && ! $request->filled('prospect_plan'))
        {
            $product = $this->resolveRegistrationSubscriptionProductFromConfig();
        }
        $billingCategory = $this->resolveBillingCategory($request, $product);

        $customerService = app(TeamStripeCustomerService::class);
        $customerId = $customerService->getOrCreateStripeCustomerIdForCategory($team, $billingCategory);
        if (! $customerId)
        {
            return redirect()->back()
                ->withInput()
                ->with('error', __('No se pudo crear el cliente de facturación. Asegúrate de tener un email de usuario.'));
        }

        // Use business name if provided, otherwise use individual name
        $displayName = $request->business_name ?: $request->individual_name;

        // Normalize phone number to E.164 format
        $phone = $this->normalizePhoneNumber($request->phone, $request->country);

        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($billingCategory));

            \Stripe\Customer::update($customerId, [
                'name' => $displayName,
                'phone' => $phone,
                'address' => [
                    'country' => $request->country,
                ],
            ]);

            try
            {
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

                if ($taxIdType !== 'unknown' && ! empty($request->tax_id))
                {
                    $newTaxId = \Stripe\Customer::createTaxId($customerId, [
                        'type' => $taxIdType,
                        'value' => $request->tax_id,
                    ]);

                    if ($newTaxId)
                    {
                        $taxIds = \Stripe\Customer::allTaxIds($customerId, ['limit' => 100]);
                        foreach ($taxIds->data as $taxId)
                        {
                            if ($taxId->id !== $newTaxId->id)
                            {
                                try
                                {
                                    \Stripe\Customer::deleteTaxId($customerId, $taxId->id);
                                } catch (\Exception $e)
                                {
                                    \Log::warning('Error deleting old tax ID: '.$e->getMessage());
                                }
                            }
                        }
                        \Log::info('Tax ID updated successfully', [
                            'team_id' => $team->id,
                            'tax_id_type' => $taxIdType,
                        ]);
                    }
                }
            } catch (\Exception $e)
            {
                \Log::error('Error updating tax ID: '.$e->getMessage(), [
                    'team_id' => $team->id,
                    'country' => $request->country,
                    'tax_id' => $request->tax_id,
                ]);
            }

            \Stripe\Customer::update($customerId, [
                'metadata' => [
                    'individual_name' => $request->individual_name,
                    'business_name' => $request->business_name,
                    'tax_id' => $request->tax_id,
                    'country' => $request->country,
                ],
            ]);

            \Log::info('Stripe customer updated successfully', [
                'customer_id' => $customerId,
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

        // Redirect to checkout (preserve domain if provided)
        $redirectParams = [];
        if ($request->product_id)
        {
            $redirectParams['product_id'] = $request->product_id;
        } elseif ($request->price_id)
        {
            $redirectParams['price_id'] = $request->price_id;
        } elseif ($request->prospect_plan)
        {
            $redirectParams['prospect_plan'] = $request->prospect_plan;
        } else
        {
            $redirectParams['plan'] = $request->plan;
        }
        if ($request->domain)
        {
            $redirectParams['domain'] = $request->domain;
        }
        if ($request->coupon)
        {
            $redirectParams['coupon'] = $request->coupon;
        }
        if ($request->boolean('from_registration'))
        {
            $redirectParams['from_registration'] = 1;
        }

        // Prospection: create one-time Stripe Checkout Session and redirect to Stripe
        if ($request->prospection)
        {
            $priceId = SubscriptionProduct::getProspectionPriceId() ?? config('services.prospect_search.export_price_id');
            if (empty($priceId) || ! str_starts_with($priceId, 'price_'))
            {
                return redirect()->route('subscription.index')
                    ->with('error', __('El producto Prospection no está configurado.'));
            }

            $successUrl = url()->route('subscription.prospection-success').'?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = url()->route('subscription.billing-info', ['prospection' => 1]);

            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory('prospecting'));
            $sessionParams = [
                'mode' => 'payment',
                'locale' => 'es',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'source' => 'prospect_search_export',
                    'lead_email' => auth()->user()->email,
                    'individual_name' => $request->individual_name,
                    'business_name' => $request->business_name ?? '',
                    'country' => $request->country,
                    'phone' => $phone ?? $request->phone,
                    'tax_id' => $request->tax_id,
                ],
            ];
            if ($customerId)
            {
                $sessionParams['customer'] = $customerId;
            } else
            {
                $sessionParams['customer_email'] = auth()->user()->email;
            }
            $session = \Stripe\Checkout\Session::create($sessionParams);

            \Illuminate\Support\Facades\Cache::put('prospect_export_session:'.$session->id, [
                'email' => auth()->user()->email,
                'filters' => [],
            ], now()->addHours(24));

            return redirect($session->url);
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
    /**
     * Validate promotion code (coupon code)
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'coupon' => 'required|string|max:255',
        ]);

        try
        {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Search for promotion code by code
            // Stripe's PromotionCode::all() doesn't support filtering by code directly
            // We need to list all and filter, or use a different approach
            // For better performance, we'll list active promotion codes and filter
            $promotionCodes = \Stripe\PromotionCode::all([
                'active' => true,
                'limit' => 100, // Get more to find the matching code
            ]);

            // Find promotion code by code (case-insensitive)
            $promotionCode = null;
            $searchCode = strtoupper(trim($request->coupon));

            foreach ($promotionCodes->data as $pc)
            {
                if (strtoupper($pc->code) === $searchCode)
                {
                    $promotionCode = $pc;
                    break;
                }
            }

            if (! $promotionCode)
            {
                return response()->json([
                    'valid' => false,
                    'message' => 'El código de promoción no existe o no es válido.',
                ], 400);
            }

            // Check if promotion code is active
            if (! $promotionCode->active)
            {
                return response()->json([
                    'valid' => false,
                    'message' => 'El código de promoción no está activo.',
                ], 400);
            }

            // Get the coupon associated with the promotion code
            $coupon = $promotionCode->coupon;

            // Check if coupon is valid
            if (! $coupon->valid)
            {
                return response()->json([
                    'valid' => false,
                    'message' => 'El cupón asociado no es válido o ha expirado.',
                ], 400);
            }

            $discount = [
                'promotion_code_id' => $promotionCode->id,
                'code' => $promotionCode->code,
                'coupon_id' => $coupon->id,
                'name' => $coupon->name ?? $promotionCode->code,
                'percent_off' => $coupon->percent_off,
                'amount_off' => $coupon->amount_off,
                'currency' => $coupon->currency,
                'duration' => $coupon->duration,
                'duration_in_months' => $coupon->duration_in_months,
            ];

            return response()->json([
                'valid' => true,
                'coupon' => $discount,
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e)
        {
            \Log::warning('Stripe error validating promotion code: '.$e->getMessage());

            return response()->json([
                'valid' => false,
                'message' => 'El código de promoción no existe o no es válido.',
            ], 400);
        } catch (\Exception $e)
        {
            \Log::error('Error validating promotion code: '.$e->getMessage());

            return response()->json([
                'valid' => false,
                'message' => 'Error al validar el código de promoción. Por favor, intenta nuevamente.',
            ], 500);
        }
    }

    /**
     * Process checkout
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'plan' => 'nullable|in:basic,foundation,scale',
            'product_id' => 'nullable|exists:subscription_products,id',
            'price_id' => 'nullable|string',
            'prospect_plan' => 'nullable|in:basic,growth',
            'domain' => 'nullable|string|max:255',
            'coupon' => 'nullable|string|max:255',
            'prospection' => 'nullable|in:1',
            'from_registration' => 'nullable|in:1',
        ]);

        $team = auth()->user()->currentTeam;
        $isFromRegistration = $request->boolean('from_registration');
        $priceId = null;
        $product = null;
        $subscriptionType = 'mailer';

        // Prospection: redirect to billing-info to collect data, then create one-time checkout
        if ($request->prospection)
        {
            $priceId = SubscriptionProduct::getProspectionPriceId() ?? config('services.prospect_search.export_price_id');
            if (empty($priceId) || ! str_starts_with($priceId, 'price_'))
            {
                return redirect()->route('subscription.index')
                    ->with('error', __('El producto Prospection no está configurado.'));
            }
            $redirectParams = ['prospection' => 1];
            if ($request->coupon)
            {
                $redirectParams['coupon'] = $request->coupon;
            }

            return redirect()->route('subscription.billing-info', $redirectParams);
        }

        // Prospect recurring plan (Basic/Growth from enum, no product in DB)
        if ($request->prospect_plan)
        {
            $prospectPlan = ProspectPlan::from($request->prospect_plan);
            $priceId = $prospectPlan->getStripePriceId();
            $subscriptionType = 'prospecting';
        }
        // If product_id is provided, get product and use its stripe_price
        elseif ($request->product_id)
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
            $product = $this->resolveRegistrationSubscriptionProductFromConfig();
            if ($product && $product->stripe_price && str_starts_with((string) $product->stripe_price, 'price_'))
            {
                $priceId = $product->stripe_price;
                $subscriptionType = $product->category ?? 'mailer';
            } else
            {
                return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
                    ->with('error', 'Debes especificar un plan o producto.');
            }
        }

        if ($isFromRegistration && $product === null)
        {
            $product = $this->resolveRegistrationSubscriptionProductFromConfig();
        }

        if ($isFromRegistration && $request->filled('price_id') && str_starts_with((string) $request->price_id, 'price_'))
        {
            $priceId = $request->price_id;
        }

        if (! $priceId || str_contains($priceId, 'REPLACE_ME'))
        {
            return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
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

            if ($isFromRegistration)
            {
                return redirect()->route('registration.billing')
                    ->with('error', 'Debes especificar un dominio para este servicio.');
            }

            return redirect()->route('subscription.index', $redirectParams)
                ->with('error', 'Debes especificar un dominio para este servicio.');
        }

        // For hosting/support products, allow multiple subscriptions (one per domain)
        // For one-time prospect packs, no subscription conflict (payment only)
        // For other products, check if already has an active subscription of the same type
        $isHostingOrSupport = $product && in_array($product->category, ['hosting', 'support']);
        $isOneTimeProspect = $product && $product->category === 'prospecting' && ! $product->recurring_interval;

        if (! $isHostingOrSupport && ! $isOneTimeProspect)
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
                if ($isFromRegistration)
                {
                    $swapRequest->merge(['from_registration' => 1]);
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
                if ($isFromRegistration)
                {
                    return redirect()->route('registration.billing')
                        ->withErrors($e->errors())
                        ->withInput();
                }

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

        // Validate duplicate domain for hosting/support products
        // This must happen AFTER domain cleaning but BEFORE creating subscription
        if ($product && in_array($product->category, ['hosting', 'support']) && $request->domain)
        {
            $normalizedDomain = strtolower($request->domain);

            \Log::info('Validating domain subscription', [
                'team_id' => $team->id,
                'subscription_type' => $subscriptionType,
                'domain' => $request->domain,
                'normalized_domain' => $normalizedDomain,
            ]);

            $existingSubscription = $team->subscriptions()
                ->where('type', $subscriptionType) // Same type (hosting or support)
                ->where('stripe_status', '!=', 'canceled')
                ->get()
                ->filter(function ($sub) use ($normalizedDomain)
                {
                    \Log::info('Checking subscription', [
                        'subscription_id' => $sub->id,
                        'subscription_type' => $sub->type,
                        'stripe_status' => $sub->stripe_status,
                        'is_active' => $sub->active(),
                        'data' => $sub->data,
                    ]);

                    // Check if subscription is active
                    if (! $sub->active())
                    {
                        return false;
                    }

                    // Check if domain matches in data field
                    // Handle both array and JSON string formats
                    $subData = $sub->data;
                    if (is_string($subData))
                    {
                        $subData = json_decode($subData, true);
                    }

                    if ($subData && is_array($subData) && isset($subData['domain']))
                    {
                        $subDomain = strtolower(trim($subData['domain']));

                        \Log::info('Comparing domains', [
                            'sub_domain' => $subDomain,
                            'normalized_domain' => $normalizedDomain,
                            'match' => $subDomain === $normalizedDomain,
                        ]);

                        return $subDomain === $normalizedDomain;
                    }

                    return false;
                })
                ->first();

            if ($existingSubscription)
            {
                $categoryName = $subscriptionType === 'hosting' ? 'hosting' : 'support';
                $errorMessage = "Ya tienes una suscripción activa de {$categoryName} para el dominio {$request->domain}.";

                \Log::warning('Duplicate subscription detected', [
                    'team_id' => $team->id,
                    'subscription_type' => $subscriptionType,
                    'domain' => $request->domain,
                    'existing_subscription_id' => $existingSubscription->id,
                ]);

                if ($isFromRegistration)
                {
                    return redirect()->route('registration.billing')
                        ->with('error', $errorMessage)
                        ->withInput();
                }

                return redirect()->route('subscription.index', ['product_id' => $request->product_id])
                    ->with('error', $errorMessage)
                    ->withInput();
            }
        }

        // Always check if billing info is complete (for ALL subscription types)
        // If billing info is already complete, we skip this step
        // This check happens BEFORE trying to create subscription directly
        $hasBillingInfo = $this->hasCompleteBillingInfo($team, $subscriptionType);

        if (! $hasBillingInfo)
        {
            \Log::info('Billing info incomplete, redirecting to billing-info (before direct creation)', [
                'team_id' => $team->id,
                'subscription_type' => $subscriptionType,
            ]);

            // Redirect to billing-info page (preserve domain and coupon if provided)
            $redirectParams = [];
            if ($request->product_id)
            {
                $redirectParams['product_id'] = $request->product_id;
            } elseif ($request->price_id)
            {
                $redirectParams['price_id'] = $request->price_id;
            } elseif ($request->plan)
            {
                $redirectParams['plan'] = $request->plan;
            } elseif ($request->prospect_plan)
            {
                $redirectParams['prospect_plan'] = $request->prospect_plan;
            }
            if ($request->domain)
            {
                $redirectParams['domain'] = $request->domain;
            }
            if ($request->coupon)
            {
                $redirectParams['coupon'] = $request->coupon;
            }
            if ($isFromRegistration)
            {
                $redirectParams['from_registration'] = 1;
            }

            return redirect()->route('subscription.billing-info', $redirectParams);
        }

        $customerService = app(TeamStripeCustomerService::class);
        $checkoutCustomerId = $customerService->getOrCreateStripeCustomerIdForCategory($team, $subscriptionType);

        // If customer has payment method, create subscription directly (skip checkout)
        if ($checkoutCustomerId)
        {
            try
            {
                \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($subscriptionType));
                $customer = \Stripe\Customer::retrieve($checkoutCustomerId);

                // Get default payment method or first available payment method
                $paymentMethodId = $customer->invoice_settings->default_payment_method;

                \Log::info('Checking payment methods', [
                    'team_id' => $team->id,
                    'stripe_customer_id' => $checkoutCustomerId,
                    'default_payment_method' => $paymentMethodId,
                ]);

                // If no default payment method, try to get the first available one
                if (! $paymentMethodId)
                {
                    $paymentMethods = \Stripe\PaymentMethod::all([
                        'customer' => $checkoutCustomerId,
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

                    // Build subscription creation config
                    $subscriptionConfig = [
                        'customer' => $checkoutCustomerId,
                        'items' => [[
                            'price' => $priceId,
                        ]],
                        'default_payment_method' => $paymentMethodId,
                        'expand' => ['latest_invoice.payment_intent'],
                        'metadata' => $metadata,
                    ];

                    // Add promotion code if provided
                    if ($request->coupon)
                    {
                        // The coupon field contains the promotion code ID
                        $subscriptionConfig['promotion_code'] = $request->coupon;
                    }

                    // Create subscription directly using existing payment method
                    $stripeSubscription = \Stripe\Subscription::create($subscriptionConfig);

                    // Log Stripe subscription data before saving
                    \Log::info('Stripe subscription created - data before save', [
                        'stripe_subscription_id' => $stripeSubscription->id,
                        'stripe_subscription_status' => $stripeSubscription->status,
                        'stripe_subscription_metadata' => $stripeSubscription->metadata ? $stripeSubscription->metadata->toArray() : null,
                        'local_metadata' => $metadata,
                        'metadata_type' => gettype($metadata),
                        'metadata_is_array' => is_array($metadata),
                        'metadata_json_encoded' => json_encode($metadata),
                    ]);

                    // Sync subscription to local database
                    // Note: Cast 'array' doesn't work with mass assignment through relations,
                    // so we need to encode explicitly
                    $team->subscriptions()->create([
                        'user_id' => $team->owner->id ?? $team->user_id,
                        'type' => $subscriptionType,
                        'stripe_id' => $stripeSubscription->id,
                        'stripe_status' => $stripeSubscription->status,
                        'stripe_price' => $priceId,
                        'quantity' => $stripeSubscription->items->data[0]->quantity,
                        'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                        'ends_at' => null,
                        'data' => ! empty($metadata) ? json_encode($metadata) : null,
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

                    if ($subscriptionType === 'prospecting')
                    {
                        try
                        {
                            $plan = ProspectPlan::fromStripePriceId($priceId);
                            $team->assignProspectPlan($plan, auth()->id());
                        } catch (\Exception $e)
                        {
                            \Log::warning('Could not assign prospect plan: '.$e->getMessage());
                        }
                    }

                    // Custom success message based on subscription type
                    $successMessage = match ($subscriptionType)
                    {
                        'hosting', 'support' => '¡Servicio contratado exitosamente usando tu método de pago guardado!',
                        default => '¡Suscripción activada exitosamente usando tu método de pago guardado!',
                    };

                    if ($isFromRegistration)
                    {
                        return redirect()->route('registration.onboarding.qr')
                            ->with('success', __('auth.registration.welcome_plan_active'));
                    }

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
            \Log::info('No Stripe customer for category, going to checkout', [
                'team_id' => $team->id,
                'subscription_type' => $subscriptionType,
            ]);
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
            if (! $checkoutCustomerId)
            {
                return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
                    ->with('error', __('No se pudo crear el cliente de pago. Asegúrate de tener un email.'));
            }

            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($subscriptionType));

            $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $checkoutCustomerId,
                'type' => 'card',
                'limit' => 1,
            ]);

            $successUrlWithCategory = route('subscription.success').'?session_id={CHECKOUT_SESSION_ID}&category='.urlencode($subscriptionType);
            if ($isFromRegistration)
            {
                $successUrlWithCategory .= '&from_registration=1';
            }
            $checkoutCancelUrl = $isFromRegistration
                ? route('registration.billing')
                : route('subscription.index');

            // One-time prospect credit pack: create payment session instead of subscription
            if ($product && $product->category === 'prospecting' && ! $product->recurring_interval)
            {
                $paymentSession = \Stripe\Checkout\Session::create([
                    'customer' => $checkoutCustomerId,
                    'mode' => 'payment',
                    'locale' => 'es',
                    'line_items' => [[
                        'price' => $priceId,
                        'quantity' => 1,
                    ]],
                    'success_url' => $successUrlWithCategory,
                    'cancel_url' => $checkoutCancelUrl,
                ]);

                return redirect($paymentSession->url);
            }

            // Build subscription metadata
            $subscriptionMetadata = [
                'team_id' => $team->id,
                'subscription_type' => $subscriptionType,
            ];

            if ($isFromRegistration)
            {
                $subscriptionMetadata['registration_checkout'] = '1';
            }

            // Add domain to metadata if provided (for hosting/support products)
            if ($request->domain)
            {
                $subscriptionMetadata['domain'] = $request->domain;
            }

            $checkoutConfig = [
                'customer' => $checkoutCustomerId,
                'mode' => 'subscription',
                'locale' => 'es',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => $successUrlWithCategory,
                'cancel_url' => $checkoutCancelUrl,
                'subscription_data' => [
                    'metadata' => $subscriptionMetadata,
                ],
            ];

            // Add promotion code if provided
            // Note: In Stripe Checkout, we can either:
            // 1. Use allow_promotion_codes: true to let user enter code manually
            // 2. Use discounts with promotion_code to apply automatically
            if ($request->coupon)
            {
                // The coupon field contains the promotion code ID
                $checkoutConfig['discounts'] = [[
                    'promotion_code' => $request->coupon,
                ]];
            } else
            {
                // Allow users to enter promotion codes manually in checkout
                $checkoutConfig['allow_promotion_codes'] = true;
            }

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

            return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
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
            if ($request->boolean('from_registration'))
            {
                return redirect()->route('registration.billing')
                    ->with('error', __('auth.registration.invalid_payment_session'));
            }

            return redirect()->route('subscription.index');
        }

        $team = auth()->user()->currentTeam;
        $category = $request->get('category');
        $category = $category ? StripeAccountResolver::normalizeCategory($category) : 'mailer';
        $secret = StripeAccountResolver::secretForCategory($category);
        $teamCustomerId = $category ? app(TeamStripeCustomerService::class)->getStripeCustomerIdForCategory($team, $category) : $team->stripe_id;

        try
        {
            Stripe::setApiKey($secret);
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            $sessionCustomerId = is_string($session->customer)
                ? $session->customer
                : (isset($session->customer->id) ? (string) $session->customer->id : null);

            if (($sessionCustomerId ?? '') !== ($teamCustomerId ?? ''))
            {
                if ($request->boolean('from_registration'))
                {
                    return redirect()->route('registration.billing')
                        ->with('error', __('auth.registration.invalid_payment_session'));
                }

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
                $rawProduct = $stripeSubscription->items->data[0]->price->product;
                $productId = is_string($rawProduct) ? $rawProduct : (string) $rawProduct->id;

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

                // Log Stripe subscription data before saving
                \Log::info('Stripe subscription from checkout - data before save', [
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'stripe_subscription_status' => $stripeSubscription->status,
                    'stripe_subscription_metadata_raw' => $stripeSubscription->metadata,
                    'stripe_subscription_metadata_array' => $metadata,
                    'metadata_type' => gettype($metadata),
                    'metadata_is_array' => is_array($metadata),
                    'metadata_json_encoded' => json_encode($metadata),
                    'session_metadata' => $session->subscription_data->metadata ?? null,
                ]);

                // Sync subscription to local database if it doesn't exist
                $localSubscription = $team->subscriptions()
                    ->where('stripe_id', $stripeSubscription->id)
                    ->first();

                if (! $localSubscription)
                {
                    // Create the subscription record manually
                    // Note: Cast 'array' doesn't work with mass assignment through relations,
                    // so we need to encode explicitly
                    \Log::info('Creating subscription with data', [
                        'data_value' => ! empty($metadata) ? json_encode($metadata) : null,
                        'data_type' => gettype(! empty($metadata) ? json_encode($metadata) : null),
                    ]);
                    $team->subscriptions()->create([
                        'user_id' => $team->owner->id ?? $team->user_id,
                        'type' => $subscriptionType,
                        'stripe_id' => $stripeSubscription->id,
                        'stripe_status' => $stripeSubscription->status,
                        'stripe_price' => $priceId,
                        'quantity' => $stripeSubscription->items->data[0]->quantity,
                        'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                        'ends_at' => null,
                        'data' => ! empty($metadata) ? json_encode($metadata) : null,
                    ]);
                } else
                {
                    if (! empty($metadata))
                    {
                        $existing = is_array($localSubscription->data) ? $localSubscription->data : [];
                        $merged = array_merge($existing, $metadata);

                        if ($merged !== $existing || $localSubscription->stripe_status !== $stripeSubscription->status)
                        {
                            $localSubscription->update([
                                'data' => $merged,
                                'stripe_status' => $stripeSubscription->status,
                            ]);
                        }
                    } elseif ($localSubscription->stripe_status !== $stripeSubscription->status)
                    {
                        $localSubscription->update(['stripe_status' => $stripeSubscription->status]);
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

                if ($subscriptionType === 'prospecting')
                {
                    try
                    {
                        $plan = ProspectPlan::fromStripePriceId($priceId);
                        $team->assignProspectPlan($plan, auth()->id());
                    } catch (\Exception $e)
                    {
                        \Log::warning('Could not assign prospect plan: '.$e->getMessage());
                    }
                }
            } else
            {
                // One-time payment: check for prospect credit packs
                $this->applyProspectCreditPackFromSession($session, $team);
            }

            $sessionMetadata = $session->metadata ? $session->metadata->toArray() : [];

            $fromRegistration = $request->boolean('from_registration')
                || (($sessionMetadata['registration_checkout'] ?? null) === '1');

            if ($fromRegistration && auth()->check())
            {
                return redirect()->route('registration.onboarding.qr')
                    ->with('success', __('auth.registration.welcome_plan_active'));
            }

            return redirect()->route('subscription.index')
                ->with('success', '¡Suscripción activada exitosamente!');
        } catch (\Exception $e)
        {
            \Log::error('Success handler error: '.$e->getMessage());

            if ($request->boolean('from_registration'))
            {
                return redirect()->route('registration.billing')
                    ->with('error', __('auth.registration.confirm_payment_failed'));
            }

            return redirect()->route('subscription.index')
                ->with('error', 'Error al procesar la suscripción: '.$e->getMessage());
        }
    }

    /**
     * Prospection one-time payment success: show download link for CSV export.
     */
    public function prospectionSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (empty($sessionId))
        {
            return redirect()->route('subscription.index')
                ->with('error', __('Sesión inválida.'));
        }

        $downloadUrl = url('/api/prospect-search/export-csv').'?session_id='.urlencode($sessionId);

        return view('subscription.prospection-success', [
            'sessionId' => $sessionId,
            'downloadUrl' => $downloadUrl,
        ]);
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

            $category = StripeAccountResolver::normalizeCategory($subscription->type);
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));
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

            $category = StripeAccountResolver::normalizeCategory($subscription->type);
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));
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
            'from_registration' => 'nullable|in:1',
        ]);

        $team = auth()->user()->currentTeam;
        $isFromRegistration = $request->boolean('from_registration');
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
            return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
                ->with('error', 'Debes especificar un plan o producto para cambiar.');
        }

        if (! $priceId || str_contains($priceId, 'REPLACE_ME'))
        {
            return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
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
                return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
                    ->with('error', 'No se encontró una suscripción activa de este tipo. Por favor, crea una nueva suscripción primero.');
            }

            $category = StripeAccountResolver::normalizeCategory($subscription->type);
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));

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

            if ($isFromRegistration)
            {
                return redirect()->route('registration.onboarding.qr')
                    ->with('success', __('auth.registration.welcome_plan_active'));
            }

            return redirect()->route('subscription.index')
                ->with('success', '¡Plan actualizado exitosamente!');
        } catch (\Exception $e)
        {
            \Log::error('Swap error: '.$e->getMessage());

            return redirect()->route($this->subscriptionCheckoutHomeRoute($request))
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
     * Apply prospect credits from a one-time checkout session (credit pack).
     */
    private function applyProspectCreditPackFromSession($session, $team): void
    {
        if (! $session || $session->mode !== 'payment' || $session->payment_status !== 'paid')
        {
            return;
        }

        try
        {
            $lineItemsResponse = \Stripe\Checkout\Session::allLineItems($session->id, ['expand' => ['data.price']]);
            $lineItems = $lineItemsResponse->data ?? [];
        } catch (\Exception $e)
        {
            \Log::warning('Could not retrieve checkout session line items: '.$e->getMessage());

            return;
        }

        foreach ($lineItems as $item)
        {
            $priceId = $item->price->id ?? null;
            if (! $priceId)
            {
                continue;
            }

            $product = SubscriptionProduct::where('stripe_price', $priceId)->first();
            if (! $product || $product->category !== 'prospecting' || $product->recurring_interval)
            {
                continue;
            }

            $packs = config('prospects.credit_packs', []);
            $credits = (int) ($product->metadata['credits'] ?? $packs[$priceId] ?? 0);
            if ($credits > 0)
            {
                $team->addProspectCreditsFromPurchase($credits);
                \Log::info('Prospect credits added from one-time purchase', [
                    'team_id' => $team->id,
                    'price_id' => $priceId,
                    'credits' => $credits,
                ]);
            }
        }
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
