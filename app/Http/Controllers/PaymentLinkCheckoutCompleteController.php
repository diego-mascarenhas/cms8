<?php

namespace App\Http\Controllers;

use App\Services\PaymentLinkSignupCompletionService;
use App\Support\HumanoPublicPaymentLinkCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentLinkCheckoutCompleteController extends Controller
{
    /**
     * Humano plan slugs allowed on ?category= (must match Stripe Payment Link "After payment" URL).
     * Checkout session and billing always use the platform Stripe account from .env (Cashier).
     *
     * @var array<int, string>
     */
    private const PRICING_PLAN_SLUGS = ['assistant', 'business', 'mentor'];

    public function __invoke(Request $request, PaymentLinkSignupCompletionService $service): RedirectResponse
    {
        Log::info('pricing.checkout.complete: request received', [
            'has_session_id' => $request->filled('session_id'),
            'session_id_prefix' => $request->filled('session_id') ? substr((string) $request->query('session_id'), 0, 16) : null,
            'category' => $request->query('category'),
            'ip' => $request->ip(),
        ]);

        $validator = Validator::make($request->all(), [
            'session_id' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(self::PRICING_PLAN_SLUGS)],
        ]);

        if ($validator->fails())
        {
            Log::warning('pricing.checkout.complete: validation failed', [
                'errors' => $validator->errors()->toArray(),
                'has_session_id' => $request->filled('session_id'),
            ]);

            return redirect()->route('pricing')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $pricingPlanSlug = $validated['category'] ?? (string) config('humano_pricing.post_checkout_plan_slug', 'assistant');

        $outcome = $service->complete($validated['session_id']);
        if ($outcome->redirect !== null)
        {
            Log::warning('pricing.checkout.complete: signup service returned redirect', [
                'target' => $outcome->redirect->getTargetUrl(),
                'session_id_prefix' => substr($validated['session_id'], 0, 16),
                'pricing_plan_slug' => $pricingPlanSlug,
                'stripe_scope' => 'humano_platform',
            ]);

            return $outcome->redirect;
        }

        if (! $outcome->hasUser() || $outcome->user === null)
        {
            Log::warning('pricing.checkout.complete: outcome missing user after service', [
                'session_id_prefix' => substr($validated['session_id'], 0, 16),
                'pricing_plan_slug' => $pricingPlanSlug,
                'stripe_scope' => 'humano_platform',
            ]);

            return redirect()->route('pricing')
                ->with('error', __('humano_pricing.checkout_complete_invalid_session'));
        }

        Auth::login($outcome->user, true);
        $request->session()->regenerate();
        $request->session()->put('humano_after_public_payment_link_checkout', true);
        $request->session()->put(HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA, true);

        if ($outcome->isNewUser && config('app.env') !== 'testing')
        {
            Password::sendResetLink(['email' => $outcome->user->email]);
        }

        Log::info('pricing.checkout.complete: user logged in, redirecting to dashboard', [
            'user_id' => $outcome->user->id,
            'is_new_user' => $outcome->isNewUser,
            'pricing_plan_slug' => $pricingPlanSlug,
            'stripe_scope' => 'humano_platform',
        ]);

        return redirect()->route('dashboard')
            ->with('success', __('humano_pricing.checkout_complete_success'));
    }
}
