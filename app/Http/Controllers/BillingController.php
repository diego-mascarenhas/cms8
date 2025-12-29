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

                // Get customer data with tax IDs and collected_information expanded
                $customer = \Stripe\Customer::retrieve($team->stripe_id, [
                    'expand' => ['tax_ids', 'collected_information'],
                ]);

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
        // Validation rules matching the contract form
        $validationRules = [
            'individual_name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'country' => 'required|string|max:2',
            'phone' => 'required|string|max:50',
            'tax_id' => 'required|string|max:50',
        ];

        // Country-specific tax ID validation
        $countryValidation = [
            'AR' => 'regex:/^\d{2}-\d{8}-\d{1}$/',  // CUIT format
            'ES' => 'regex:/^[A-Z]\d{8}$/',  // CIF/NIF format
            'MX' => 'regex:/^[A-Z]{4}\d{6}[A-Z0-9]{3}$/',  // RFC format
        ];

        if (isset($countryValidation[$request->country]))
        {
            $validationRules['tax_id'] .= '|'.$countryValidation[$request->country];
        }

        $request->validate($validationRules);

        $user = auth()->user();
        $team = $user->currentTeam;

        try
        {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Prepare data
            $customerName = $request->business_name ?: $request->individual_name;

            // Get or create Stripe customer
            if ($team->stripe_id)
            {
                // Update existing customer
                $customer = \Stripe\Customer::update($team->stripe_id, [
                    'name' => $customerName,
                    'phone' => $request->phone,
                    'address' => [
                        'country' => $request->country,
                    ],
                    'metadata' => [
                        'individual_name' => $request->individual_name,
                        'company_name' => $request->business_name ?: $request->individual_name,
                    ],
                ]);

                // Update or create tax ID separately
                try
                {
                    // Get existing tax IDs
                    $taxIds = \Stripe\TaxId::all(['customer' => $team->stripe_id]);

                    // Delete existing tax IDs
                    foreach ($taxIds->data as $taxId)
                    {
                        $taxId->delete();
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
                        default => 'eu_vat',
                    };

                    // Create new tax ID
                    \Stripe\TaxId::create([
                        'customer' => $team->stripe_id,
                        'type' => $taxIdType,
                        'value' => $request->tax_id,
                    ]);
                } catch (\Exception $e)
                {
                    // If tax ID fails, just log it but don't fail the whole update
                    \Log::warning('Could not update tax ID: '.$e->getMessage());
                }
            } else
            {
                // Create new customer
                $customer = $team->createAsStripeCustomer([
                    'name' => $customerName,
                    'phone' => $request->phone,
                    'address' => [
                        'country' => $request->country,
                    ],
                    'metadata' => [
                        'individual_name' => $request->individual_name,
                        'company_name' => $request->business_name ?: $request->individual_name,
                    ],
                ]);

                // Add tax ID
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
                        default => 'eu_vat',
                    };

                    \Stripe\TaxId::create([
                        'customer' => $customer->id,
                        'type' => $taxIdType,
                        'value' => $request->tax_id,
                    ]);
                } catch (\Exception $e)
                {
                    \Log::warning('Could not create tax ID: '.$e->getMessage());
                }
            }

            return redirect()->route('billing.index')->with('success', 'Datos de facturación actualizados correctamente.');
        } catch (\Exception $e)
        {
            \Log::error('Error updating billing data: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Error al actualizar los datos de facturación: '.$e->getMessage());
        }
    }
}
