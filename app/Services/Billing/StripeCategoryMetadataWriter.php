<?php

namespace App\Services\Billing;

use App\Models\ServiceSync;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Subscription;
use Throwable;

class StripeCategoryMetadataWriter
{
    public function write(ServiceSync $sync, ?string $categoryName): void
    {
        $team = Team::query()->find($sync->team_id);
        $secret = $team?->getSetting('stripe_secret');

        if (! is_string($secret) || $secret === '')
        {
            return;
        }

        Stripe::setApiKey($secret);
        $metadata = ['category' => $categoryName ?? ''];

        try
        {
            Subscription::update((string) $sync->stripe_id, [
                'metadata' => $metadata,
            ]);

            $productId = $this->productId($sync);
            if ($productId !== null)
            {
                Product::update($productId, [
                    'metadata' => $metadata,
                ]);
            }
        } catch (ApiErrorException|Throwable $e)
        {
            Log::warning('Stripe category metadata update failed', [
                'subscription' => $sync->stripe_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function productId(ServiceSync $sync): ?string
    {
        $payload = is_array($sync->raw_payload) ? $sync->raw_payload : [];
        $product = Arr::get($payload, 'items.data.0.price.product')
            ?? Arr::get($payload, 'plan.product');

        if (is_array($product))
        {
            $product = $product['id'] ?? null;
        }

        $productId = trim((string) $product);

        return $productId !== '' ? $productId : null;
    }
}
