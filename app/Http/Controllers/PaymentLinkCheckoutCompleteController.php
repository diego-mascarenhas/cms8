<?php

namespace App\Http\Controllers;

use App\Services\PaymentLinkSignupCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class PaymentLinkCheckoutCompleteController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const STRIPE_CATEGORIES = ['mailer', 'mentoring', 'prospecting', 'hosting', 'support'];

    public function __invoke(Request $request, PaymentLinkSignupCompletionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(self::STRIPE_CATEGORIES)],
        ]);

        $category = $validated['category'] ?? (string) config('humano_pricing.post_checkout_stripe_category', 'mailer');
        if ($category === '')
        {
            $category = 'mailer';
        }

        $outcome = $service->complete($validated['session_id'], $category);
        if ($outcome->redirect !== null)
        {
            return $outcome->redirect;
        }

        if (! $outcome->hasUser() || $outcome->user === null)
        {
            return redirect()->route('pricing')
                ->with('error', __('humano_pricing.checkout_complete_invalid_session'));
        }

        Auth::login($outcome->user, true);
        $request->session()->regenerate();

        if ($outcome->isNewUser && config('app.env') !== 'testing')
        {
            Password::sendResetLink(['email' => $outcome->user->email]);
        }

        return redirect()->route('subscription.index')
            ->with('success', __('humano_pricing.checkout_complete_success'));
    }
}
