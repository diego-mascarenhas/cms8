<?php

namespace App\Http\Controllers;

use App\Services\AffiliateReferralLinkBuilder;
use App\Services\StripeAccountResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliatePlanCheckoutController extends Controller
{
    public function __invoke(Request $request, string $plan, AffiliateReferralLinkBuilder $links): RedirectResponse
    {
        $planId = strtolower(trim($plan));
        $config = $this->planConfig($planId);

        if ($config === null)
        {
            return redirect()->route('pricing');
        }

        $referrer = trim((string) $request->query('ref', ''));
        $email = trim((string) $request->query('prefilled_email', ''));
        $checkoutUrl = trim((string) ($config['checkout_url'] ?? ''));

        if ($checkoutUrl !== '')
        {
            if ($referrer === '')
            {
                return redirect()->away($checkoutUrl);
            }

            return redirect()->away($links->buildCaptureRedirectLink(
                $checkoutUrl,
                $referrer,
                $email !== '' ? $email : null,
            ));
        }

        $priceId = trim((string) ($config['stripe_price_monthly_id'] ?? ''));

        if ($priceId === '')
        {
            return redirect()->route('pricing', array_filter([
                'plan' => $planId,
                'ref' => $referrer !== '' ? $referrer : null,
            ]));
        }

        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory(''));

            $payload = [
                'mode' => 'subscription',
                'locale' => 'es',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => route('pricing.checkout.complete').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('pricing'),
                'allow_promotion_codes' => true,
                'metadata' => [
                    'plan' => $planId,
                    'referrer' => $referrer,
                ],
            ];

            if ($referrer !== '')
            {
                $payload['client_reference_id'] = $referrer;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $payload['customer_email'] = $email;
            }

            $trialDays = max(0, (int) ($config['trial_days'] ?? 0));
            if ($trialDays > 0)
            {
                $payload['subscription_data'] = [
                    'trial_period_days' => $trialDays,
                ];
            }

            $session = \Stripe\Checkout\Session::create($payload);
        } catch (\Throwable $exception)
        {
            Log::warning('Affiliate plan checkout failed', [
                'plan' => $planId,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('pricing')->with('error', __('No se pudo abrir el pago de este plan.'));
        }

        if (! is_string($session->url) || $session->url === '')
        {
            return redirect()->route('pricing');
        }

        return redirect()->away($session->url);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planConfig(string $planId): ?array
    {
        foreach (config('humano_pricing.plans', []) as $plan)
        {
            if (is_array($plan) && (string) ($plan['id'] ?? '') === $planId)
            {
                return $plan;
            }
        }

        return null;
    }
}
