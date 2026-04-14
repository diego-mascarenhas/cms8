<?php

namespace App\Actions\Products;

use App\Models\SubscriptionProduct;
use App\Services\Stripe\StripeProductService;
use App\Services\StripeAccountResolver;
use Stripe\StripeClient;

class SyncStripeProducts
{
    public function __construct(
        private readonly StripeProductService $stripe,
    ) {}

    /**
     * Sync products from Stripe. Optionally use a specific category's Stripe account.
     *
     * @param  string|null  $category  mentoring|mailer|prospecting|hosting|support; null = default Cashier account
     */
    public function handle(?string $category = null): int
    {
        $stripe = $this->stripe;
        if ($category !== null)
        {
            $category = StripeAccountResolver::normalizeCategory($category);
            $secret = StripeAccountResolver::secretForCategory($category);
            $stripe = new StripeProductService(new StripeClient($secret));
        }

        $processed = 0;
        $productsByName = [];

        // Group products by name to handle duplicates
        foreach ($stripe->products() as $stripeProduct)
        {
            $name = $stripeProduct->name;
            if (! isset($productsByName[$name]))
            {
                $productsByName[$name] = [];
            }
            $productsByName[$name][] = $stripeProduct;
        }

        // Process products, preferring those with fewer prices (likely the correct ones)
        foreach ($productsByName as $name => $stripeProducts)
        {
            // If there are duplicates, prefer the one with fewer prices
            $selectedProduct = $this->selectBestProduct($stripeProducts, $stripe);

            if (! $selectedProduct)
            {
                continue;
            }

            $mapped = $this->mapProduct($selectedProduct, $stripe);

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
     * Select the best product when there are duplicates (prefer fewer prices, more recent)
     */
    private function selectBestProduct(array $products, StripeProductService $stripe): ?\Stripe\Product
    {
        if (count($products) === 1)
        {
            return $products[0];
        }

        // Count prices for each product and prefer the one with fewer prices
        $productsWithPriceCount = [];
        foreach ($products as $product)
        {
            $priceCount = 0;
            foreach ($stripe->prices($product->id) as $price)
            {
                $priceCount++;
            }
            $productsWithPriceCount[] = [
                'product' => $product,
                'price_count' => $priceCount,
                'created' => $product->created,
            ];
        }

        // Sort by price count (ascending - fewer prices first), then by created date (descending - more recent first)
        usort($productsWithPriceCount, function ($a, $b)
        {
            if ($a['price_count'] !== $b['price_count'])
            {
                return $a['price_count'] <=> $b['price_count']; // Fewer prices first
            }

            return $b['created'] <=> $a['created']; // More recent first
        });

        return $productsWithPriceCount[0]['product'];
    }

    /**
     * Sync a specific product by Stripe Product ID
     * This will overwrite all local data with Stripe data
     */
    public function syncProduct(string $stripeProductId): void
    {
        try
        {
            $stripeProduct = $this->stripe->retrieve($stripeProductId);
            $mapped = $this->mapProduct($stripeProduct, $this->stripe);

            // Find product by stripe_product or stripe_id
            $product = SubscriptionProduct::where('stripe_product', $stripeProductId)
                ->orWhere('stripe_id', $stripeProductId)
                ->first();

            if ($product)
            {
                // Update existing product with Stripe data, forcing overwrite of all fields
                $this->updateProduct($product, $mapped, forceOverwrite: true);
            } else
            {
                // Create new product if not found
                SubscriptionProduct::create($mapped + ['last_synced_at' => now()]);
            }
        } catch (\Exception $e)
        {
            \Log::error('Failed to sync product from Stripe', [
                'stripe_product_id' => $stripeProductId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map Stripe product to local format
     */
    private function mapProduct(\Stripe\Product $stripeProduct, StripeProductService $stripe): array
    {
        $payload = $stripeProduct->toArray();

        // Get main price (recurring, most recent, or default)
        $mainPrice = $this->getMainPrice($stripeProduct->id, $stripe);

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
            'unit_amount' => $mainPrice?->unit_amount ? ($mainPrice->unit_amount / 100) : null, // Convert from cents to decimal
            'recurring_interval' => $mainPrice?->recurring?->interval ?? null,
            'recurring_interval_count' => $mainPrice?->recurring?->interval_count ?? 1,
            'metadata' => $stripeProduct->metadata ?? [],
            'raw_payload' => $payload,
        ];

        return $mapped;
    }

    /**
     * Get main price for a product (recurring, most recent, or default)
     * Prefers prices with EUR currency and recurring monthly
     */
    private function getMainPrice(string $productId, StripeProductService $stripe): ?\Stripe\Price
    {
        $prices = [];
        foreach ($stripe->prices($productId) as $price)
        {
            $prices[] = $price;
        }

        if (empty($prices))
        {
            return null;
        }

        // Prefer recurring prices with EUR currency
        $recurringEurPrices = array_filter($prices, fn ($p) => $p->recurring !== null && strtolower($p->currency) === 'eur');
        if (! empty($recurringEurPrices))
        {
            // Sort by created date (most recent first)
            usort($recurringEurPrices, fn ($a, $b) => $b->created <=> $a->created);

            return reset($recurringEurPrices);
        }

        // Fallback to any recurring prices
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
     *
     * @param  bool  $forceOverwrite  If true, overwrites all fields including name/description
     */
    private function updateProduct(SubscriptionProduct $product, array $mapped, bool $forceOverwrite = false): void
    {
        // If forceOverwrite is false, check if product was created locally and manually edited
        if (! $forceOverwrite && $product->stripe_id && ! $product->last_synced_at)
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

        // If forceOverwrite is true, always update name/description from Stripe
        // Otherwise, only update if product was precached (no stripe_id)
        if ($forceOverwrite || ! $product->stripe_id)
        {
            $updateData['name'] = $mapped['name'];
            $updateData['description'] = $mapped['description'];
        }

        foreach (['category', 'plan', 'type'] as $field)
        {
            $value = $mapped[$field] ?? null;
            if (is_string($value))
            {
                $value = trim($value);
            }
            if ($value !== null && $value !== '')
            {
                $updateData[$field] = $value;
            }
        }

        $product->fill($updateData);
        $product->save();
    }
}
