<?php

namespace App\Services\Stripe;

use Stripe\StripeClient;

class StripeProductService
{
    public function __construct(private readonly StripeClient $client) {}

    /**
     * Get all products from Stripe
     *
     * @return \Generator<\Stripe\Product>
     */
    public function products(array $params = []): \Generator
    {
        $params = array_merge([
            'limit' => 100,
            'active' => true,
            'expand' => ['data.default_price'],
        ], $params);

        $collection = $this->client->products->all($params);

        foreach ($collection->autoPagingIterator() as $product)
        {
            yield $product;
        }
    }

    /**
     * Retrieve a product from Stripe
     */
    public function retrieve(string $productId): \Stripe\Product
    {
        return $this->client->products->retrieve($productId, [
            'expand' => ['default_price'],
        ]);
    }

    /**
     * Create a product in Stripe
     */
    public function create(array $data): \Stripe\Product
    {
        return $this->client->products->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'active' => $data['active'] ?? true,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Update a product in Stripe
     */
    public function update(string $productId, array $data): \Stripe\Product
    {
        $updateData = [];

        if (isset($data['name']))
        {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['description']))
        {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['active']))
        {
            $updateData['active'] = $data['active'];
        }

        if (isset($data['metadata']))
        {
            $updateData['metadata'] = $data['metadata'];
        }

        return $this->client->products->update($productId, $updateData);
    }

    /**
     * Create a price for a product in Stripe
     */
    public function createPrice(string $productId, array $data): \Stripe\Price
    {
        $priceData = [
            'product' => $productId,
            'currency' => $data['currency'] ?? 'usd',
            'unit_amount' => $data['unit_amount'] ?? 0,
        ];

        if (isset($data['recurring']) && $data['recurring'])
        {
            $priceData['recurring'] = $data['recurring'];
        }

        return $this->client->prices->create($priceData);
    }

    /**
     * Get all prices for a product
     *
     * @return \Generator<\Stripe\Price>
     */
    public function prices(string $productId, array $params = []): \Generator
    {
        $params = array_merge([
            'product' => $productId,
            'limit' => 100,
            'active' => true,
        ], $params);

        $collection = $this->client->prices->all($params);

        foreach ($collection->autoPagingIterator() as $price)
        {
            yield $price;
        }
    }
}
