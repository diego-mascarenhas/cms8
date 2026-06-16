<?php

namespace App\Services;

use App\Models\AffiliateInvitation;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AffiliateReferralAttributionService
{
    public const SESSION_REFERRER_KEY = 'affiliate_referrer_stripe_id';

    public const SESSION_INVITATION_KEY = 'affiliate_invitation_id';

    public function captureIfValid(Request $request, string $referrerStripeId, ?int $affiliateInvitationId = null): bool
    {
        $referrerStripeId = trim($referrerStripeId);
        if (! $this->isValidReferrerStripeId($referrerStripeId))
        {
            return false;
        }

        if (Team::findByStripeCustomerId($referrerStripeId) === null)
        {
            return false;
        }

        $this->capture($request, $referrerStripeId, $affiliateInvitationId);

        return true;
    }

    public function capture(Request $request, string $referrerStripeId, ?int $affiliateInvitationId = null): void
    {
        $referrerStripeId = trim($referrerStripeId);
        if (! $this->isValidReferrerStripeId($referrerStripeId))
        {
            return;
        }

        $request->session()->put(self::SESSION_REFERRER_KEY, $referrerStripeId);

        if ($affiliateInvitationId !== null && $affiliateInvitationId > 0)
        {
            $request->session()->put(self::SESSION_INVITATION_KEY, $affiliateInvitationId);
        }

        Cookie::queue($this->makeReferrerCookie($referrerStripeId));
    }

    public function getStoredReferrerStripeId(Request $request): ?string
    {
        $fromSession = trim((string) $request->session()->get(self::SESSION_REFERRER_KEY, ''));
        if ($fromSession !== '' && $this->isValidReferrerStripeId($fromSession))
        {
            return $fromSession;
        }

        $fromCookie = trim((string) $request->cookie($this->cookieName(), ''));
        if ($fromCookie !== '' && $this->isValidReferrerStripeId($fromCookie))
        {
            return $fromCookie;
        }

        return null;
    }

    public function resolveReferrerFromInvitationEmail(string $email): ?string
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '')
        {
            return null;
        }

        $invitation = AffiliateInvitation::query()
            ->with('team')
            ->whereRaw('LOWER(invitee_email) = ?', [$normalizedEmail])
            ->orderByDesc('clicked_at')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if ($invitation === null)
        {
            return null;
        }

        return app(AffiliateReferralLinkBuilder::class)->referralCode($invitation->team);
    }

    public function isAllowedRedirectUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL))
        {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '')
        {
            return false;
        }

        if (in_array($host, ['buy.stripe.com', 'checkout.stripe.com'], true))
        {
            return true;
        }

        if (str_ends_with($host, '.stripe.com'))
        {
            return true;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '' && $host === $appHost)
        {
            return true;
        }

        return false;
    }

    private function isValidReferrerStripeId(string $referrerStripeId): bool
    {
        return str_starts_with(strtolower($referrerStripeId), 'cus_');
    }

    private function cookieName(): string
    {
        return (string) config('humano_pricing.affiliate_referral_cookie_name', 'humano_affiliate_ref');
    }

    private function makeReferrerCookie(string $referrerStripeId): \Symfony\Component\HttpFoundation\Cookie
    {
        $days = max(1, (int) config('humano_pricing.affiliate_referral_cookie_days', 90));

        return cookie(
            $this->cookieName(),
            $referrerStripeId,
            $days * 24 * 60,
            '/',
            null,
            (bool) config('session.secure', false),
            true,
            false,
            'lax',
        );
    }
}
