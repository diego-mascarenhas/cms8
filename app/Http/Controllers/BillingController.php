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
        $request->validate([
            'individual_name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:50',
            'billing_email' => 'required|email',
            'billing_phone' => 'nullable|string|max:50',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'required|string|max:2',
        ], [
            'individual_name.required' => 'El nombre completo es obligatorio.',
            'individual_name.max' => 'El nombre completo no puede tener más de 255 caracteres.',
            'company_name.required' => 'La razón social es obligatoria.',
            'company_name.max' => 'La razón social no puede tener más de 255 caracteres.',
            'tax_id.required' => 'El CIF/NIF es obligatorio.',
            'tax_id.max' => 'El CIF/NIF no puede tener más de 50 caracteres.',
            'billing_email.required' => 'El email de facturación es obligatorio.',
            'billing_email.email' => 'El email de facturación debe ser una dirección válida.',
            'billing_phone.max' => 'El teléfono no puede tener más de 50 caracteres.',
            'address_line1.required' => 'La dirección es obligatoria.',
            'address_line1.max' => 'La dirección no puede tener más de 255 caracteres.',
            'address_line2.max' => 'La dirección 2 no puede tener más de 255 caracteres.',
            'postal_code.required' => 'El código postal es obligatorio.',
            'postal_code.max' => 'El código postal no puede tener más de 20 caracteres.',
            'city.required' => 'La ciudad es obligatoria.',
            'city.max' => 'La ciudad no puede tener más de 100 caracteres.',
            'state.max' => 'La provincia/estado no puede tener más de 100 caracteres.',
            'country.required' => 'El país es obligatorio.',
            'country.max' => 'El país no puede tener más de 2 caracteres.',
        ]);

        $user = auth()->user();
        $team = $user->currentTeam;

        try
        {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Get or create Stripe customer
            if ($team->stripe_id)
            {
                // Update existing customer
                $customer = \Stripe\Customer::update($team->stripe_id, [
                    'name' => $request->individual_name, // Nombre del particular (aparece en facturas)
                    'email' => $request->billing_email,
                    'phone' => $request->billing_phone,
                    'address' => [
                        'line1' => $request->address_line1,
                        'line2' => $request->address_line2,
                        'postal_code' => $request->postal_code,
                        'city' => $request->city,
                        'state' => $request->state,
                        'country' => $request->country,
                    ],
                    'metadata' => [
                        'company_name' => $request->company_name, // Razón Social (solo interno)
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

                    // Create new tax ID
                    \Stripe\TaxId::create([
                        'customer' => $team->stripe_id,
                        'type' => $request->country === 'ES' ? 'es_cif' : 'eu_vat',
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
                    'name' => $request->individual_name, // Nombre del particular (aparece en facturas)
                    'email' => $request->billing_email,
                    'phone' => $request->billing_phone,
                    'address' => [
                        'line1' => $request->address_line1,
                        'line2' => $request->address_line2,
                        'postal_code' => $request->postal_code,
                        'city' => $request->city,
                        'state' => $request->state,
                        'country' => $request->country,
                    ],
                    'metadata' => [
                        'company_name' => $request->company_name, // Razón Social (solo interno)
                    ],
                ]);

                // Add tax ID
                try
                {
                    \Stripe\TaxId::create([
                        'customer' => $customer->id,
                        'type' => $request->country === 'ES' ? 'es_cif' : 'eu_vat',
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
