<?php

namespace App\Services;

use App\Models\Team;
use App\Support\HumanoHomeAsset;
use App\Support\HumanoPricingCatalog;

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

    public function normalizeCatalog(?string $catalog): ?string
    {
        return HumanoPricingCatalog::normalize($catalog);
    }

    /**
     * @return list<array{id: string, name: string, checkout_url: string, catalog: string, monthly_amount: string, yearly_amount: string, currency: string}>
     */
    public function availablePlans(?string $catalog = null): array
    {
        $catalog = $this->normalizeCatalog($catalog);
        $plans = [];

        foreach (config('humano_pricing.plans', []) as $plan)
        {
            if (! is_array($plan))
            {
                continue;
            }

            $planId = (string) ($plan['id'] ?? '');
            $planCatalog = strtolower(trim((string) ($plan['catalog'] ?? 'assistant')));

            if ($planId === '' || $planId === 'innovation')
            {
                continue;
            }

            if ($catalog !== null && $planCatalog !== $catalog)
            {
                continue;
            }

            $plans[] = [
                'id' => $planId,
                'name' => (string) __("humano_pricing.plans.{$planId}.name"),
                'checkout_url' => trim((string) ($plan['checkout_url'] ?? '')),
                'catalog' => $planCatalog,
                'monthly_amount' => trim((string) ($plan['monthly_amount'] ?? '')),
                'yearly_amount' => trim((string) ($plan['yearly_amount'] ?? '')),
                'currency' => 'usd',
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
     * Shareable checkout for a plan. Payment Links keep their Stripe URL.
     * Every other plan gets a link that opens checkout with the referrer.
     *
     * @param  array<string, mixed>  $plan
     */
    public function referralUrl(array $plan, string $referralCode, ?string $prefilledEmail = null): string
    {
        $checkoutUrl = trim((string) ($plan['checkout_url'] ?? ''));

        if ($checkoutUrl !== '')
        {
            return $this->buildCaptureRedirectLink($checkoutUrl, $referralCode, $prefilledEmail);
        }

        $query = ['ref' => $referralCode];

        if ($prefilledEmail !== null && $prefilledEmail !== '')
        {
            $query['prefilled_email'] = $prefilledEmail;
        }

        return route('affiliate.plan-checkout', ['plan' => $plan['id']]).'?'.http_build_query($query);
    }

    /**
     * @return array{name: string, description: string, features: list<string>, image_url: string}|null
     */
    public function planMarketing(string $planId, ?string $locale = null): ?array
    {
        $resolve = function () use ($planId): ?array
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
        };

        if ($locale !== null)
        {
            return $this->runWithLocale($locale, $resolve);
        }

        return $resolve();
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runWithLocale(string $locale, callable $callback): mixed
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);

        try
        {
            return $callback();
        } finally
        {
            app()->setLocale($previousLocale);
        }
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
