<?php

namespace App\Services;

use App\Models\Team;
use App\Support\HumanoHomeAsset;

class AffiliateReferralLinkBuilder
{
    public function referralCode(Team $team): ?string
    {
        $stripeId = trim((string) ($team->stripe_id ?? ''));
        if ($stripeId === '')
        {
            return null;
        }

        return $stripeId;
    }

    /**
     * @return list<array{id: string, name: string, checkout_url: string}>
     */
    public function availablePlans(): array
    {
        $plans = [];

        foreach (config('humano_pricing.plans', []) as $plan)
        {
            if (! is_array($plan))
            {
                continue;
            }

            $checkoutUrl = trim((string) ($plan['checkout_url'] ?? ''));
            $available = (bool) ($plan['checkout_available'] ?? false);
            $planId = (string) ($plan['id'] ?? '');

            if ($planId === '' || $checkoutUrl === '' || ! $available)
            {
                continue;
            }

            $plans[] = [
                'id' => $planId,
                'name' => (string) __("humano_pricing.plans.{$planId}.name"),
                'checkout_url' => $checkoutUrl,
            ];
        }

        return $plans;
    }

    public function buildLink(string $checkoutUrl, string $referralCode, ?string $prefilledEmail = null): string
    {
        $query = [
            'client_reference_id' => $referralCode,
        ];

        if ($prefilledEmail !== null && $prefilledEmail !== '')
        {
            $query['prefilled_email'] = $prefilledEmail;
        }

        $separator = str_contains($checkoutUrl, '?') ? '&' : '?';

        return $checkoutUrl.$separator.http_build_query($query);
    }

    public function buildCaptureRedirectLink(string $checkoutUrl, string $referralCode, ?string $prefilledEmail = null): string
    {
        return route('affiliate.referral.capture', [
            'ref' => $referralCode,
            'url' => $this->buildLink($checkoutUrl, $referralCode, $prefilledEmail),
        ]);
    }

    /**
     * @return array{name: string, description: string, features: list<string>}|null
     */
    public function planMarketing(string $planId): ?array
    {
        $plan = collect($this->availablePlans())->firstWhere('id', $planId);
        if ($plan === null)
        {
            return null;
        }

        $features = __("humano_pricing.plans.{$planId}.features");
        if (! is_array($features))
        {
            $features = [];
        }

        return [
            'name' => $plan['name'],
            'description' => (string) __("humano_pricing.plans.{$planId}.description"),
            'features' => array_values(array_map('strval', $features)),
            'image_url' => $this->planImageUrl($planId),
        ];
    }

    public function planImageUrl(string $planId): string
    {
        $paths = [
            'assistant' => 'img/plans/assistant.png',
            'hunter' => 'img/plans/hunter.png',
            'business' => 'img/plans/business.png',
            'mentor' => 'img/plans/mentor.png',
        ];

        $path = $paths[$planId] ?? 'img/plans/assistant.png';

        return HumanoHomeAsset::url($path);
    }

    public function pricingPageUrl(): string
    {
        return route('pricing');
    }
}
