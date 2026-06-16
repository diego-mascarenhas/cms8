<?php

namespace App\Http\Controllers;

use App\Models\AffiliateInvitation;
use App\Services\AffiliateReferralAttributionService;
use App\Services\AffiliateReferralLinkBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AffiliateInvitationTrackingController extends Controller
{
    public function __construct(
        private readonly AffiliateReferralAttributionService $affiliateReferralAttribution,
        private readonly AffiliateReferralLinkBuilder $affiliateReferralLinkBuilder,
    ) {}

    public function trackOpen(Request $request, string $token): Response
    {
        $this->recordOpen($request, $token);

        return $this->trackingPixelResponse();
    }

    public function trackClick(Request $request, string $token): RedirectResponse|Response
    {
        $invitation = $this->findByToken($token);
        $redirectUrl = trim((string) $request->query('url', ''));
        $linkType = trim((string) $request->query('link', 'checkout'));

        if ($invitation && $redirectUrl !== '')
        {
            $invitation->markClicked($linkType);
            $this->captureReferrerFromInvitation($request, $invitation);

            Log::info('Affiliate invitation click tracked', [
                'invitation_id' => $invitation->id,
                'link' => $linkType,
                'ip' => $request->ip(),
            ]);
        }

        if ($redirectUrl !== '' && filter_var($redirectUrl, FILTER_VALIDATE_URL))
        {
            return redirect()->away($redirectUrl);
        }

        return $this->trackingPixelResponse();
    }

    private function recordOpen(Request $request, string $token): void
    {
        $invitation = $this->findByToken($token);
        if (! $invitation)
        {
            return;
        }

        $invitation->markOpened();

        Log::info('Affiliate invitation open tracked', [
            'invitation_id' => $invitation->id,
            'ip' => $request->ip(),
        ]);
    }

    private function captureReferrerFromInvitation(Request $request, AffiliateInvitation $invitation): void
    {
        $invitation->loadMissing('team');
        $referrerStripeId = $this->affiliateReferralLinkBuilder->referralCode($invitation->team);
        if ($referrerStripeId === null)
        {
            return;
        }

        $this->affiliateReferralAttribution->capture($request, $referrerStripeId, (int) $invitation->id);
    }

    private function findByToken(string $token): ?AffiliateInvitation
    {
        if ($token === '')
        {
            return null;
        }

        return AffiliateInvitation::query()
            ->where('tracking_token', $token)
            ->first();
    }

    private function trackingPixelResponse(): Response
    {
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
