<?php

namespace App\Actions\Products;

use App\Models\SubscriptionProduct;
use App\Services\Stripe\StripeProductService;

class SyncStripeProducts
{
    public function __construct(
        private readonly StripeProductService $stripe,
    ) {}

    public function handle(): int
    {
        $processed = 0;

        foreach ($this->stripe->products() as $stripeProduct)
        {
            $mapped = $this->mapProduct($stripeProduct);

            // First try to find by stripe_id
            $product = SubscriptionProduct::firstWhere('stripe_id', $mapped['stripe_id']);

            // If not found, try to match with precached products by category, plan, and type
            if (! $product)
            {
                $category = $mapped['category'] ?? null;
                $type = $mapped['type'] ?? null;
                $plan = $mapped['plan'] ?? null;

                if ($category && $type)
                {
                    $query = SubscriptionProduct::where('category', $category)
                        ->where('type', $type)
                        ->whereNull('stripe_id'); // Only match precached products (no stripe_id yet)

                    // Match plan if provided
                    if ($plan)
                    {
                        $query->where('plan', $plan);
                    } else
                    {
                        $query->whereNull('plan');
                    }

                    $product = $query->first();
                }
            }

            if ($product)
            {
                // Update existing product with Stripe data
                $this->updateProduct($product, $mapped);
            } else
            {
                // Create new product (not precached)
                SubscriptionProduct::create($mapped + ['last_synced_at' => now()]);
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * Map Stripe product to local format
     */
    private function mapProduct(\Stripe\Product $stripeProduct): array
    {
        $payload = $stripeProduct->toArray();

        // Get main price (recurring, most recent, or default)
        $mainPrice = $this->getMainPrice($stripeProduct->id);

        $mapped = [
            'stripe_id' => $stripeProduct->id,
            'stripe_product' => $stripeProduct->id,
            'stripe_price' => $mainPrice?->id,
            'name' => $stripeProduct->name,
            'description' => $stripeProduct->description,
            'active' => $stripeProduct->active ?? true,
            'category' => $stripeProduct->metadata['category'] ?? null,
            'plan' => $stripeProduct->metadata['plan'] ?? null,
            'type' => $stripeProduct->metadata['type'] ?? null,
            'currency' => $mainPrice?->currency ?? 'usd',
            'unit_amount' => $mainPrice?->unit_amount ?? null,
            'recurring_interval' => $mainPrice?->recurring?->interval ?? null,
            'recurring_interval_count' => $mainPrice?->recurring?->interval_count ?? 1,
            'metadata' => $stripeProduct->metadata ?? [],
            'raw_payload' => $payload,
        ];

        return $mapped;
    }

    /**
     * Get main price for a product (recurring, most recent, or default)
     */
    private function getMainPrice(string $productId): ?\Stripe\Price
    {
        $prices = [];
        foreach ($this->stripe->prices($productId) as $price)
        {
            $prices[] = $price;
        }

        if (empty($prices))
        {
            return null;
        }

        // Prefer recurring prices
        $recurringPrices = array_filter($prices, fn ($p) => $p->recurring !== null);
        if (! empty($recurringPrices))
        {
            // Sort by created date (most recent first)
            usort($recurringPrices, fn ($a, $b) => $b->created <=> $a->created);

            return reset($recurringPrices);
        }

        // Fallback to one-time prices
        usort($prices, fn ($a, $b) => $b->created <=> $a->created);

        return reset($prices);
    }

    /**
     * Update existing product
     */
    private function updateProduct(SubscriptionProduct $product, array $mapped): void
    {
        // If product was precached (no stripe_id), update with Stripe data including name/description
        // If product was already synced (has last_synced_at), update from Stripe but preserve name/description
        // Only skip if product was created locally and manually edited (has stripe_id but no last_synced_at)
        if ($product->stripe_id && ! $product->last_synced_at)
        {
            // Product was created locally and manually edited, don't overwrite
            return;
        }

        // Update with Stripe data
        $updateData = [
            'stripe_id' => $mapped['stripe_id'],
            'stripe_product' => $mapped['stripe_product'],
            'stripe_price' => $mapped['stripe_price'],
            'currency' => $mapped['currency'],
            'unit_amount' => $mapped['unit_amount'],
            'recurring_interval' => $mapped['recurring_interval'],
            'recurring_interval_count' => $mapped['recurring_interval_count'],
            'metadata' => $mapped['metadata'],
            'raw_payload' => $mapped['raw_payload'],
            'active' => $mapped['active'],
            'last_synced_at' => now(),
        ];

        // If product was precached (no stripe_id), update name/description from Stripe
        // If product was already synced, preserve name/description (assume they were manually edited)
        if (! $product->stripe_id)
        {
            $updateData['name'] = $mapped['name'];
            $updateData['description'] = $mapped['description'];
        }

        $product->fill($updateData);
        $product->save();
    }
}
