<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Services\StripeAccountResolver;
use App\Services\TeamStripeCustomerService;
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

        // Get current plan from active subscription or fallback to team setting (e.g. from seeder)
        if ($mailerSubscription && $mailerSubscription->active())
        {
            $currentPlan = EmailPlan::fromStripePriceId($mailerSubscription->stripe_price);
        } else
        {
            $currentPlan = $team->getEmailPlan();
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
            $customerService = app(TeamStripeCustomerService::class);
            $customerId = $team->stripe_id ?: $customerService->getStripeCustomerIdForCategory($team, 'mailer');
            if ($customerId)
            {
                \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory('mailer'));

                $customer = \Stripe\Customer::retrieve($customerId);

                // Verify the customer belongs to this team
                // Check metadata team_id if it exists
                $customerTeamId = $customer->metadata->team_id ?? null;
                if ($customerTeamId && (int) $customerTeamId !== (int) $team->id)
                {
                    \Log::warning('Customer stripe_id does not match team in BillingController', [
                        'team_id' => $team->id,
                        'customer_team_id' => $customerTeamId,
                        'stripe_customer_id' => $customerId,
                    ]);
                    // Don't use this customer's data - team has wrong stripe_id
                    $stripeData = null;
                } else
                {
                    // Get tax IDs separately for more reliability
                    try
                    {
                        $taxIds = \Stripe\Customer::allTaxIds($customerId, ['limit' => 10]);
                        $customer->tax_ids = $taxIds;
                    } catch (\Exception $e)
                    {
                        \Log::warning('Could not retrieve tax IDs: '.$e->getMessage());
                    }

                    // Get invoices
                    $invoicesData = \Stripe\Invoice::all([
                        'customer' => $customerId,
                        'limit' => 20,
                    ]);
                    $invoices = collect($invoicesData->data);

                    // Get subscriptions
                    $subscriptionsData = \Stripe\Subscription::all([
                        'customer' => $customerId,
                        'limit' => 10,
                    ]);
                    $subscriptions = collect($subscriptionsData->data);

                    // Get payment methods
                    $paymentMethodsData = \Stripe\PaymentMethod::all([
                        'customer' => $customerId,
                        'type' => 'card',
                    ]);
                    $paymentMethods = collect($paymentMethodsData->data);

                    $stripeData = [
                        'customer' => $customer,
                        'invoices' => $invoicesData,
                        'subscriptions' => $subscriptionsData,
                    ];
                }
            } else
            {
                // Team has no stripe_id - don't show any billing data
                // This is correct behavior: new teams should not see data from other teams
                \Log::info('Team has no stripe_id, not showing billing data', [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                ]);
                $stripeData = null;
                $invoices = collect([]);
                $subscriptions = collect([]);
                $paymentMethods = collect([]);
            }
        } catch (\Exception $e)
        {
            Log::error('Error fetching Stripe data: '.$e->getMessage(), [
                'team_id' => $team->id,
                'stripe_id' => $team->stripe_id,
            ]);
            $stripeData = null;
        }

        $team->resetProspectMonthlyLimitsIfNeeded();
        $remainingProspectCredits = $team->getRemainingProspectCredits();
        $currentProspectPlan = $team->getProspectPlan();

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
            'remainingProspectCredits',
            'currentProspectPlan',
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
            $customerService = app(TeamStripeCustomerService::class);
            $billingCustomerId = $customerService->getOrCreateStripeCustomerIdForCategory($team, 'mailer');
            if (! $billingCustomerId)
            {
                return redirect()->route('billing.index')
                    ->with('error', __('No se pudo crear el cliente de facturación.'));
            }

            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory('mailer'));

            $customerName = $request->business_name ?: $request->individual_name;
            $taxIdError = null;
            $phone = $this->normalizePhoneNumber($request->phone, $request->country);

            // Step 1: Update customer basic info
            \Stripe\Customer::update($billingCustomerId, [
                'name' => $customerName,
                'phone' => $phone,
                'address' => [
                    'country' => $request->country,
                ],
            ]);

            // Step 2: Update or create tax ID
            try
            {
                $taxIds = \Stripe\Customer::allTaxIds($billingCustomerId, ['limit' => 100]);
                foreach ($taxIds->data as $taxId)
                {
                    \Stripe\Customer::deleteTaxId($billingCustomerId, $taxId->id);
                }

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

                if ($taxIdType !== 'unknown')
                {
                    \Stripe\Customer::createTaxId($billingCustomerId, [
                        'type' => $taxIdType,
                        'value' => $request->tax_id,
                    ]);
                    \Log::info('Tax ID created successfully for customer: '.$billingCustomerId);
                }
            } catch (\Exception $e)
            {
                \Log::error('Could not update tax ID: '.$e->getMessage());
                \Log::error('Tax ID Error details: ', [
                    'customer' => $billingCustomerId,
                    'country' => $request->country,
                    'tax_id' => $request->tax_id,
                    'error' => $e->getMessage(),
                ]);
                $taxIdError = $e->getMessage();
            }

            \Stripe\Customer::update($billingCustomerId, [
                'metadata' => [
                    'individual_name' => $request->individual_name,
                    'business_name' => $request->business_name,
                    'tax_id' => $request->tax_id,
                    'country' => $request->country,
                ],
            ]);

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
