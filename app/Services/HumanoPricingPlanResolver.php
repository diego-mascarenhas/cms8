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

                return $plan;
            })
            ->values()
            ->all();
    }

    /**
     * Plans with an active Stripe checkout (public pricing surfaces).
     *
     * @return list<array<string, mixed>>
     */
    public function plansWithCheckoutAvailable(): array
    {
        return array_values(array_filter(
            $this->plansForDisplay(),
            static fn (array $plan): bool => (bool) ($plan['checkout_available'] ?? true),
        ));
    }

    /**
     * Match a Stripe product id to a `humano_pricing.plans` entry (`assistant`, `business`, `mentor`).
     */
    public function resolvePlanSlugFromStripeProductId(string $stripeProductId): ?string
    {
        $stripeProductId = trim($stripeProductId);
        if ($stripeProductId === '')
        {
            return null;
        }

        foreach (config('humano_pricing.plans', []) as $plan)
        {
            $configured = trim((string) ($plan['stripe_product_id'] ?? ''));
            if ($configured !== '' && $configured === $stripeProductId)
            {
                $id = strtolower(trim((string) ($plan['id'] ?? '')));

                return $id !== '' ? $id : null;
            }
        }

        return null;
    }
}
