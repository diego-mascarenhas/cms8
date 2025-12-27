<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $currentPlan = EmailPlan::from($team->email_plan ?? 'free');

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
        } catch (\Exception $e)
        {
            Log::error('Error fetching Stripe data: '.$e->getMessage());
        }

        // Get Cashier subscription
        $subscription = $team->subscription('default');

        return view('billing.index', compact(
            'team',
            'currentPlan',
            'subscription',
            'planConfig',
            'invoices',
            'subscriptions',
            'paymentMethods',
            'stripeData',
        ));
    }
}
