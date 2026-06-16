<?php

namespace App\Http\Controllers;

use App\Services\AffiliateReferralAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliateReferralCaptureController extends Controller
{
    public function capture(Request $request, AffiliateReferralAttributionService $attribution): RedirectResponse
    {
        $referrerStripeId = trim((string) $request->query('ref', ''));
        $redirectUrl = trim((string) $request->query('url', ''));

        if ($redirectUrl === '' || ! $attribution->isAllowedRedirectUrl($redirectUrl))
        {
            Log::warning('Affiliate referral capture: invalid redirect url', [
                'ref_preview' => substr($referrerStripeId, 0, 16),
            ]);

            return redirect()->route('pricing');
        }

        if (! $attribution->captureIfValid($request, $referrerStripeId))
        {
            Log::info('Affiliate referral capture: invalid referrer, redirecting without capture', [
                'ref_preview' => substr($referrerStripeId, 0, 16),
            ]);

            return redirect()->away($redirectUrl);
        }

        Log::info('Affiliate referral capture: stored referrer before redirect', [
            'ref_preview' => substr($referrerStripeId, 0, 16),
            'ip' => $request->ip(),
        ]);

        return redirect()->away($redirectUrl);
    }
}
