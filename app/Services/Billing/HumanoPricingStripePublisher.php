<?php

namespace App\Services\Billing;

use App\Support\HumanoPricingCatalog;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

class HumanoPricingStripePublisher
{
    /**
     * Catalogs that get a paid Stripe product (Affiliates stays free).
     *
     * @var list<string>
     */
    public const PAID_APP_CATALOGS = [
        HumanoPricingCatalog::SHOP,
        HumanoPricingCatalog::ADS,
        HumanoPricingCatalog::PROJECTS,
    ];

    public function __construct(private readonly StripeClient $client) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function publishablePlans(): array
    {
        $plans = [];

        foreach (config('humano_pricing.plans', []) as $plan)
        {
            if (! is_array($plan))
            {
                continue;
            }

            $catalog = strtolower(trim((string) ($plan['catalog'] ?? '')));
            $planId = trim((string) ($plan['id'] ?? ''));
            $amount = trim((string) ($plan['monthly_amount'] ?? ''));

            if ($planId === '' || $amount === '' || (float) $amount <= 0)
            {
                continue;
            }

            if (! in_array($catalog, self::PAID_APP_CATALOGS, true))
            {
                continue;
            }

            $plans[] = $plan;
        }

        return $plans;
    }

    /**
     * @return list<array{
     *     plan_id: string,
     *     name: string,
     *     created: bool,
     *     product_id: string,
     *     price_id: string,
     *     amount: string,
     *     currency: string
     * }>
     */
    public function publish(bool $dryRun = false): array
    {
        $results = [];

        foreach ($this->publishablePlans() as $plan)
        {
            $planId = (string) $plan['id'];
            $name = (string) trans("humano_pricing.plans.{$planId}.name", [], 'en');
            $description = (string) trans("humano_pricing.plans.{$planId}.description", [], 'en');
            $amount = (string) $plan['monthly_amount'];
            $cents = (int) round(((float) $amount) * 100);
            $currency = 'eur';

            $existing = $this->findProductByPlanId($planId);

            if ($dryRun)
            {
                $results[] = [
                    'plan_id' => $planId,
                    'name' => $name,
                    'created' => $existing === null,
                    'product_id' => $existing?->id ?? '(would create)',
                    'price_id' => $existing !== null
                        ? ($this->findMonthlyPrice($existing->id, $cents, $currency)?->id ?? '(would create price)')
                        : '(would create)',
                    'amount' => $amount,
                    'currency' => $currency,
                ];

                continue;
            }

            $created = false;
            if ($existing === null)
            {
                $existing = $this->client->products->create([
                    'name' => $name,
                    'description' => $description,
                    'active' => true,
                    'metadata' => [
                        'humano_plan_id' => $planId,
                        'catalog' => (string) ($plan['catalog'] ?? ''),
                        'subscription_type' => (string) ($plan['subscription_type'] ?? $planId),
                    ],
                ]);
                $created = true;
            } elseif ($existing->name !== $name || ($existing->description ?? '') !== $description)
            {
                $existing = $this->client->products->update($existing->id, [
                    'name' => $name,
                    'description' => $description,
                ]);
            }

            $price = $this->findMonthlyPrice($existing->id, $cents, $currency);
            if ($price === null)
            {
                $price = $this->client->prices->create([
                    'product' => $existing->id,
                    'currency' => $currency,
                    'unit_amount' => $cents,
                    'recurring' => [
                        'interval' => 'month',
                        'interval_count' => 1,
                    ],
                    'metadata' => [
                        'humano_plan_id' => $planId,
                    ],
                ]);

                $this->client->products->update($existing->id, [
                    'default_price' => $price->id,
                ]);
            }

            $results[] = [
                'plan_id' => $planId,
                'name' => $name,
                'created' => $created,
                'product_id' => $existing->id,
                'price_id' => $price->id,
                'amount' => $amount,
                'currency' => $currency,
            ];
        }

        return $results;
    }

    private function findProductByPlanId(string $planId): ?Product
    {
        try
        {
            $result = $this->client->products->search([
                'query' => 'metadata["humano_plan_id"]:"'.$planId.'"',
                'limit' => 1,
            ]);

            return $result->data[0] ?? null;
        } catch (ApiErrorException)
        {
            foreach ($this->client->products->all(['limit' => 100, 'active' => true])->autoPagingIterator() as $product)
            {
                if (($product->metadata['humano_plan_id'] ?? '') === $planId)
                {
                    return $product;
                }
            }
        }

        return null;
    }

    private function findMonthlyPrice(string $productId, int $cents, string $currency): ?Price
    {
        foreach ($this->client->prices->all([
            'product' => $productId,
            'active' => true,
            'limit' => 100,
        ])->autoPagingIterator() as $price)
        {
            $interval = $price->recurring->interval ?? null;
            if (
                (int) $price->unit_amount === $cents
                && strtolower((string) $price->currency) === $currency
                && $interval === 'month'
            ) {
                return $price;
            }
        }

        return null;
    }
}
