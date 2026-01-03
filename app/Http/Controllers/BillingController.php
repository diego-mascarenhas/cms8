<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        // Get ALL subscriptions from database (exclude canceled ones)
        $teamSubscriptions = $team->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('type'); // Group by type: mailer, hosting, domain, licence, etc.

        // Get mailer subscription for email plan info
        $mailerSubscription = $team->subscription('mailer');

        // Get current plan from active subscription or fallback to team setting
        if ($mailerSubscription && $mailerSubscription->active())
        {
            $currentPlan = EmailPlan::fromStripePriceId($mailerSubscription->stripe_price);
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

        // Get Stripe data
        $stripeData = null;
        $invoices = collect([]);
        $subscriptions = collect([]);
        $paymentMethods = collect([]);

        try
        {
            // Use team's stripe_id if available, otherwise fallback to email search
            if ($team->stripe_id)
            {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));

                // Get customer data
                $customer = \Stripe\Customer::retrieve($team->stripe_id);

                // Get tax IDs separately for more reliability
                try
                {
                    $taxIds = \Stripe\Customer::allTaxIds($team->stripe_id, ['limit' => 10]);
                    $customer->tax_ids = $taxIds;
                } catch (\Exception $e)
                {
                    \Log::warning('Could not retrieve tax IDs: '.$e->getMessage());
                }

                // Get invoices
                $invoicesData = \Stripe\Invoice::all([
                    'customer' => $team->stripe_id,
                    'limit' => 20,
                ]);
                $invoices = collect($invoicesData->data);

                // Get subscriptions
                $subscriptionsData = \Stripe\Subscription::all([
                    'customer' => $team->stripe_id,
                    'limit' => 10,
                ]);
                $subscriptions = collect($subscriptionsData->data);

                // Get payment methods
                $paymentMethodsData = \Stripe\PaymentMethod::all([
                    'customer' => $team->stripe_id,
                    'type' => 'card',
                ]);
                $paymentMethods = collect($paymentMethodsData->data);

                $stripeData = [
                    'customer' => $customer,
                    'invoices' => $invoicesData,
                    'subscriptions' => $subscriptionsData,
                ];
            } else
            {
                // Fallback to email search (legacy)
                $stripeService = new StripeService;
                $stripeData = $stripeService->getCustomerDataByEmail($user->email, true, 20);

                if ($stripeData)
                {
                    $invoices = collect($stripeData['invoices']->data);
                    $subscriptions = collect($stripeData['subscriptions']->data);

                    // Get payment methods
                    if (isset($stripeData['customer']))
                    {
                        \Stripe\Stripe::setApiKey(config('cashier.secret'));
                        $paymentMethodsData = \Stripe\PaymentMethod::all([
                            'customer' => $stripeData['customer']->id,
                            'type' => 'card',
                        ]);
                        $paymentMethods = collect($paymentMethodsData->data);
                    }
                }
            }
        } catch (\Exception $e)
        {
            Log::error('Error fetching Stripe data: '.$e->getMessage());
        }

        return view('billing.index', compact(
            'team',
            'currentPlan',
            'mailerSubscription',
            'teamSubscriptions',
            'planConfig',
            'invoices',
            'subscriptions',
            'paymentMethods',
            'stripeData',
        ));
    }

    public function update(Request $request)
    {
        // Validation rules with smart tax_id validation
        $request->validate([
            'individual_name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'country' => 'required|string|max:2',
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

        $user = auth()->user();
        $team = $user->currentTeam;

        try
        {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Prepare data
            $customerName = $request->business_name ?: $request->individual_name;
            $taxIdError = null;

            // Normalize phone number to E.164 format
            $phone = $this->normalizePhoneNumber($request->phone, $request->country);

            // Get or create Stripe customer
            if ($team->stripe_id)
            {
                // Step 1: Update customer basic info
                \Stripe\Customer::update($team->stripe_id, [
                    'name' => $customerName,
                    'phone' => $phone,
                    'address' => [
                        'country' => $request->country,
                    ],
                ]);

                // Step 2: Update or create tax ID
                try
                {
                    // Get existing tax IDs
                    $taxIds = \Stripe\Customer::allTaxIds($team->stripe_id, ['limit' => 100]);

                    // Delete existing tax IDs
                    foreach ($taxIds->data as $taxId)
                    {
                        \Stripe\Customer::deleteTaxId($team->stripe_id, $taxId->id);
                    }

                    // Determine tax ID type based on country
                    $taxIdType = match ($request->country)
                    {
                        'AR' => 'ar_cuit',
                        'ES' => 'es_cif',
                        'MX' => 'mx_rfc',
                        'CL' => 'cl_tin',
                        'CO' => 'co_nit',
                        'PE' => 'pe_ruc',
                        'UY' => 'uy_ruc',
                        'US' => 'us_ein',
                        default => 'unknown',
                    };

                    // Create new tax ID if type is known
                    if ($taxIdType !== 'unknown')
                    {
                        \Stripe\Customer::createTaxId($team->stripe_id, [
                            'type' => $taxIdType,
                            'value' => $request->tax_id,
                        ]);
                        \Log::info('Tax ID created successfully for customer: '.$team->stripe_id);
                    }
                } catch (\Exception $e)
                {
                    // Log the full error
                    \Log::error('Could not update tax ID: '.$e->getMessage());
                    \Log::error('Tax ID Error details: ', [
                        'customer' => $team->stripe_id,
                        'country' => $request->country,
                        'tax_id' => $request->tax_id,
                        'error' => $e->getMessage(),
                    ]);
                    $taxIdError = $e->getMessage();
                }

                // Step 3: Update metadata separately (like SubscriptionController)
                \Stripe\Customer::update($team->stripe_id, [
                    'metadata' => [
                        'individual_name' => $request->individual_name,
                        'business_name' => $request->business_name,
                        'tax_id' => $request->tax_id,
                        'country' => $request->country,
                    ],
                ]);
            } else
            {
                // Step 1: Create new customer
                $customer = $team->createAsStripeCustomer([
                    'name' => $customerName,
                    'phone' => $phone,
                    'address' => [
                        'country' => $request->country,
                    ],
                ]);

                // Step 2: Add tax ID
                try
                {
                    $taxIdType = match ($request->country)
                    {
                        'AR' => 'ar_cuit',
                        'ES' => 'es_cif',
                        'MX' => 'mx_rfc',
                        'CL' => 'cl_tin',
                        'CO' => 'co_nit',
                        'PE' => 'pe_ruc',
                        'UY' => 'uy_ruc',
                        'US' => 'us_ein',
                        default => 'unknown',
                    };

                    // Create tax ID if type is known
                    if ($taxIdType !== 'unknown')
                    {
                        \Stripe\Customer::createTaxId($customer->id, [
                            'type' => $taxIdType,
                            'value' => $request->tax_id,
                        ]);
                        \Log::info('Tax ID created successfully for new customer: '.$customer->id);
                    }
                } catch (\Exception $e)
                {
                    \Log::error('Could not create tax ID: '.$e->getMessage());
                    \Log::error('Tax ID Error details: ', [
                        'customer' => $customer->id,
                        'country' => $request->country,
                        'tax_id' => $request->tax_id,
                        'error' => $e->getMessage(),
                    ]);
                    $taxIdError = $e->getMessage();
                }

                // Step 3: Update metadata separately (like SubscriptionController)
                \Stripe\Customer::update($customer->id, [
                    'metadata' => [
                        'individual_name' => $request->individual_name,
                        'business_name' => $request->business_name,
                        'tax_id' => $request->tax_id,
                        'country' => $request->country,
                    ],
                ]);
            }

            $successMessage = 'Datos de facturación actualizados correctamente.';
            if ($taxIdError)
            {
                $successMessage .= ' Sin embargo, hubo un problema al actualizar el ID Fiscal: '.$taxIdError;

                return redirect()->route('billing.index')->with('warning', $successMessage);
            }

            return redirect()->route('billing.index')->with('success', $successMessage);
        } catch (\Exception $e)
        {
            \Log::error('Error updating billing data: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Error al actualizar los datos de facturación: '.$e->getMessage());
        }
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
