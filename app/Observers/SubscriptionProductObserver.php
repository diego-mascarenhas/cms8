<?php

namespace App\Observers;

use App\Models\SubscriptionProduct;
use App\Services\Stripe\StripeProductService;
use Illuminate\Support\Facades\Log;

class SubscriptionProductObserver
{
    public function __construct(
        private readonly StripeProductService $stripeService,
    ) {}

    /**
     * Handle the SubscriptionProduct "created" event.
     */
    public function created(SubscriptionProduct $subscriptionProduct): void
    {
        // Skip if running in console (seeders, migrations, etc.)
        if (app()->runningInConsole())
        {
            return;
        }

        // Skip if already has stripe_id (synced from Stripe)
        if ($subscriptionProduct->stripe_id)
        {
            return;
        }

        try
        {
            // Create product in Stripe
            $stripeProduct = $this->stripeService->create([
                'name' => $subscriptionProduct->name,
                'description' => $subscriptionProduct->description,
                'active' => $subscriptionProduct->active,
                'metadata' => $this->buildMetadata($subscriptionProduct),
            ]);

            // Create price in Stripe
            $stripePrice = $this->stripeService->createPrice($stripeProduct->id, [
                'currency' => $subscriptionProduct->currency ?? 'usd',
                'unit_amount' => (int) ($subscriptionProduct->unit_amount ?? 0),
                'recurring' => $subscriptionProduct->recurring_interval ? [
                    'interval' => $subscriptionProduct->recurring_interval,
                    'interval_count' => $subscriptionProduct->recurring_interval_count ?? 1,
                ] : null,
            ]);

            // Update local record with Stripe IDs
            $subscriptionProduct->update([
                'stripe_id' => $stripeProduct->id,
                'stripe_product' => $stripeProduct->id,
                'stripe_price' => $stripePrice->id,
            ]);
        } catch (\Exception $e)
        {
            Log::error('Failed to create product in Stripe', [
                'product_id' => $subscriptionProduct->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the SubscriptionProduct "updated" event.
     */
    public function updated(SubscriptionProduct $subscriptionProduct): void
    {
        // Skip if running in console (seeders, migrations, etc.)
        if (app()->runningInConsole())
        {
            return;
        }

        // Skip if no stripe_id (not synced yet)
        if (! $subscriptionProduct->stripe_id)
        {
            return;
        }

        // Skip if only last_synced_at changed (to avoid infinite loop)
        if ($subscriptionProduct->wasChanged('last_synced_at'))
        {
            return;
        }

        try
        {
            // Update product in Stripe
            $this->stripeService->update($subscriptionProduct->stripe_id, [
                'name' => $subscriptionProduct->name,
                'description' => $subscriptionProduct->description,
                'active' => $subscriptionProduct->active,
                'metadata' => $this->buildMetadata($subscriptionProduct),
            ]);

            // If price changed, create new price (Stripe doesn't allow updating prices)
            if ($subscriptionProduct->wasChanged(['unit_amount', 'currency', 'recurring_interval', 'recurring_interval_count']))
            {
                $stripePrice = $this->stripeService->createPrice($subscriptionProduct->stripe_id, [
                    'currency' => $subscriptionProduct->currency ?? 'usd',
                    'unit_amount' => (int) ($subscriptionProduct->unit_amount ?? 0),
                    'recurring' => $subscriptionProduct->recurring_interval ? [
                        'interval' => $subscriptionProduct->recurring_interval,
                        'interval_count' => $subscriptionProduct->recurring_interval_count ?? 1,
                    ] : null,
                ]);

                $subscriptionProduct->update([
                    'stripe_price' => $stripePrice->id,
                ]);
            }
        } catch (\Exception $e)
        {
            Log::error('Failed to update product in Stripe', [
                'product_id' => $subscriptionProduct->id,
                'stripe_id' => $subscriptionProduct->stripe_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the SubscriptionProduct "deleted" event.
     */
    public function deleted(SubscriptionProduct $subscriptionProduct): void
    {
        // Don't delete from Stripe, just deactivate
        if (! $subscriptionProduct->stripe_id)
        {
            return;
        }

        try
        {
            $this->stripeService->update($subscriptionProduct->stripe_id, [
                'active' => false,
            ]);
        } catch (\Exception $e)
        {
            Log::error('Failed to deactivate product in Stripe', [
                'product_id' => $subscriptionProduct->id,
                'stripe_id' => $subscriptionProduct->stripe_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build metadata array for Stripe
     */
    private function buildMetadata(SubscriptionProduct $product): array
    {
        $metadata = [];

        if ($product->category)
        {
            $metadata['category'] = $product->category;
        }

        if ($product->plan)
        {
            $metadata['plan'] = $product->plan;
        }

        if ($product->type)
        {
            $metadata['type'] = $product->type;
        }

        return $metadata;
    }
}
