<?php

namespace App\Services\Hosting;

use App\Models\Team;
use App\Services\StripeAccountResolver;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Stripe;
use Stripe\Subscription;
use Throwable;

class StripeHostingPlanGateway
{
    /**
     * @return list<array{
     *     price_id: string,
     *     product_name: string,
     *     plan: string,
     *     currency: string,
     *     interval: string,
     *     interval_count: int,
     *     unit_amount: int,
     *     whm_package: string|null
     * }>
     */
    public function catalog(Team $team): array
    {
        $secret = $this->secret($team);
        if ($secret === null)
        {
            return [];
        }

        Stripe::setApiKey($secret);
        $plans = [];
        $startingAfter = null;

        do
        {
            $params = [
                'active' => true,
                'limit' => 100,
                'expand' => ['data.product'],
            ];
            if ($startingAfter !== null)
            {
                $params['starting_after'] = $startingAfter;
            }

            $page = Price::all($params);
            foreach ($page->data as $price)
            {
                $row = $this->mapPrice($price);
                if ($row !== null)
                {
                    $plans[] = $row;
                }
            }

            $startingAfter = ($page->has_more && $page->data !== [])
                ? (string) $page->data[array_key_last($page->data)]->id
                : null;
        } while ($startingAfter !== null && count($plans) < 400);

        return $plans;
    }

    /**
     * @return array{item_id: string, price_id: string, currency: string, interval: string, interval_count: int}|null
     */
    public function currentItem(Team $team, string $subscriptionId): ?array
    {
        $secret = $this->secret($team);
        if ($secret === null || $subscriptionId === '')
        {
            return null;
        }

        Stripe::setApiKey($secret);

        try
        {
            $subscription = Subscription::retrieve($subscriptionId);
        } catch (ApiErrorException|Throwable)
        {
            return null;
        }

        $item = $subscription->items->data[0] ?? null;
        $price = $item?->price;
        $recurring = $price?->recurring;
        if ($item === null || $price === null || $recurring === null)
        {
            return null;
        }

        return [
            'item_id' => (string) $item->id,
            'price_id' => (string) $price->id,
            'currency' => strtolower((string) $price->currency),
            'interval' => (string) $recurring->interval,
            'interval_count' => (int) ($recurring->interval_count ?: 1),
        ];
    }

    /**
     * @param  array<string, string>  $metadata
     */
    public function replaceMetadata(Team $team, string $subscriptionId, array $metadata): bool
    {
        $secret = $this->secret($team);
        if ($secret === null || $subscriptionId === '')
        {
            return false;
        }

        Stripe::setApiKey($secret);

        try
        {
            Subscription::update($subscriptionId, [
                'metadata' => $metadata,
            ]);
        } catch (ApiErrorException|Throwable $exception)
        {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        return true;
    }

    public function swap(Team $team, string $subscriptionId, string $itemId, string $priceId): void
    {
        $secret = $this->secret($team);
        if ($secret === null)
        {
            throw new RuntimeException('Stripe no está configurado en el equipo.');
        }

        Stripe::setApiKey($secret);

        try
        {
            Subscription::update($subscriptionId, [
                'items' => [[
                    'id' => $itemId,
                    'price' => $priceId,
                ]],
                'proration_behavior' => 'create_prorations',
                'cancel_at_period_end' => false,
            ]);
        } catch (ApiErrorException|Throwable $exception)
        {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array{
     *     price_id: string,
     *     product_name: string,
     *     plan: string,
     *     currency: string,
     *     interval: string,
     *     interval_count: int,
     *     unit_amount: int,
     *     whm_package: string|null
     * }|null
     */
    private function mapPrice(Price $price): ?array
    {
        $product = $price->product;
        if (! is_object($product) || empty($product->active))
        {
            return null;
        }

        $metadata = [];
        if (isset($product->metadata))
        {
            $metadata = $product->metadata instanceof \Stripe\StripeObject
                ? $product->metadata->toArray()
                : (array) $product->metadata;
        }

        $plan = trim((string) ($metadata['plan'] ?? ''));
        $recurring = $price->recurring;
        if ($plan === '' || $recurring === null || ! $price->active)
        {
            return null;
        }

        $package = null;
        foreach (['whm_package', 'pkg', 'cpanel_plan', 'package'] as $key)
        {
            $candidate = trim((string) ($metadata[$key] ?? ''));
            if ($candidate !== '')
            {
                $package = $candidate;
                break;
            }
        }

        return [
            'price_id' => (string) $price->id,
            'product_name' => (string) ($product->name ?? $plan),
            'plan' => $plan,
            'currency' => strtolower((string) $price->currency),
            'interval' => (string) $recurring->interval,
            'interval_count' => (int) ($recurring->interval_count ?: 1),
            'unit_amount' => (int) ($price->unit_amount ?? 0),
            'whm_package' => $package,
        ];
    }

    private function secret(Team $team): ?string
    {
        $secret = trim(StripeAccountResolver::secretForCategory('hosting'));

        return $secret !== '' ? $secret : null;
    }
}
