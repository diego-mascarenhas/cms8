<?php

namespace App\Services;

class HumanoPricingPlanResolver
{
    /**
     * @return list<array<string, mixed>>
     */
    public function plansForDisplay(): array
    {
        return collect(config('humano_pricing.plans', []))
            ->map(function (array $plan): array
            {
                $checkoutAvailable = (bool) ($plan['checkout_available'] ?? true);
                $monthlyCheckoutUrl = trim((string) ($plan['checkout_url'] ?? ''));
                $yearlyCheckoutUrl = trim((string) ($plan['checkout_url_yearly'] ?? ''));

                if ($yearlyCheckoutUrl === '')
                {
                    $yearlyCheckoutUrl = $monthlyCheckoutUrl;
                }

                $plan['checkout_href_monthly'] = $checkoutAvailable ? $monthlyCheckoutUrl : '';
                $plan['checkout_href_yearly'] = $checkoutAvailable ? $yearlyCheckoutUrl : '';
                $plan['checkout_href'] = $plan['checkout_href_monthly'];
                $plan['external_url'] = trim((string) ($plan['external_url'] ?? ''));
                $plan['catalog'] = trim((string) ($plan['catalog'] ?? 'assistant')) ?: 'assistant';
                $plan['public'] = (bool) ($plan['public'] ?? true);

                return $plan;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plansForPublicDisplay(): array
    {
        return array_values(array_filter(
            $this->plansForDisplay(),
            static fn (array $plan): bool => (bool) ($plan['public'] ?? true),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plansForCatalog(string $catalog): array
    {
        $catalog = strtolower(trim($catalog));

        return array_values(array_filter(
            $this->plansForDisplay(),
            static fn (array $plan): bool => ($plan['catalog'] ?? 'assistant') === $catalog,
        ));
    }

    /**
     * Plans with an active Stripe checkout (public pricing surfaces).
     *
     * @return list<array<string, mixed>>
     */
    public function plansWithCheckoutAvailable(?string $referralCode = null): array
    {
        $plans = array_values(array_filter(
            $this->plansForPublicDisplay(),
            static fn (array $plan): bool => (bool) ($plan['checkout_available'] ?? true),
        ));

        $referralCode = trim((string) ($referralCode ?? ''));
        if ($referralCode === '')
        {
            return $plans;
        }

        $builder = app(AffiliateReferralLinkBuilder::class);

        return array_map(function (array $plan) use ($builder, $referralCode): array
        {
            foreach (['checkout_href_monthly', 'checkout_href_yearly', 'checkout_href'] as $key)
            {
                $href = trim((string) ($plan[$key] ?? ''));
                if ($href !== '')
                {
                    $plan[$key] = $builder->buildLink($href, $referralCode);
                }
            }

            return $plan;
        }, $plans);
    }

    /**
     * Match a Stripe product id to a `humano_pricing.plans` entry.
     *
     * @return array<string, mixed>|null
     */
    public function planByStripeProductId(string $stripeProductId): ?array
    {
        $stripeProductId = trim($stripeProductId);
        if ($stripeProductId === '')
        {
            return null;
        }

        foreach ($this->plansForDisplay() as $plan)
        {
            $configured = trim((string) ($plan['stripe_product_id'] ?? ''));
            if ($configured !== '' && $configured === $stripeProductId)
            {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Match a Stripe product id to a `humano_pricing.plans` entry (`assistant`, `business`, `mentor`).
     */
    public function resolvePlanSlugFromStripeProductId(string $stripeProductId): ?string
    {
        $plan = $this->planByStripeProductId($stripeProductId);
        $id = strtolower(trim((string) ($plan['id'] ?? '')));

        return $id !== '' ? $id : null;
    }
}
