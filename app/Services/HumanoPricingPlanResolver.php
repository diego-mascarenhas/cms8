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
                $plan['checkout_href'] = $checkoutAvailable
                    ? (string) $plan['checkout_url']
                    : '';
                $plan['external_url'] = trim((string) ($plan['external_url'] ?? ''));

                return $plan;
            })
            ->values()
            ->all();
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
