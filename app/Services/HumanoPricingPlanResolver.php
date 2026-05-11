<?php

namespace App\Services;

class HumanoPricingPlanResolver
{
    /**
     * Match a Stripe product id to a `humano_pricing.plans` entry (`assistant`, `business`, `foundation`).
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
