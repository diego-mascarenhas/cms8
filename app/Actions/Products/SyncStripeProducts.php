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

            $product = SubscriptionProduct::firstWhere('stripe_id', $mapped['stripe_id']);

            if ($product)
            {
                // Update existing product
                $this->updateProduct($product, $mapped);
            } else
            {
                // Create new product
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
        // Don't update if product was created locally (has no last_synced_at)
        // This prevents overwriting local changes
        if (! $product->last_synced_at)
        {
            return;
        }

        $product->fill($mapped + ['last_synced_at' => now()]);
        $product->save();
    }
}
