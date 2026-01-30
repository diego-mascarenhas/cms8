<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceService
{
    public function __construct(
        protected Team $team,
    ) {}

    public function isConfigured(): bool
    {
        $url = $this->team->getSetting('woocommerce_url');
        $key = $this->team->getSetting('woocommerce_consumer_key');
        $secret = $this->team->getSetting('woocommerce_consumer_secret');

        return ! empty($url) && ! empty($key) && ! empty($secret);
    }

    protected function baseUrl(): string
    {
        return rtrim($this->team->getSetting('woocommerce_url'), '/');
    }

    protected function apiVersion(): string
    {
        return $this->team->getSetting('woocommerce_api_version', 'wc/v3');
    }

    protected function verifySsl(): bool
    {
        return (bool) $this->team->getSetting('woocommerce_verify_ssl', '1');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(int $page = 1, int $perPage = 50): array
    {
        if (! $this->isConfigured())
        {
            return [];
        }

        $url = $this->baseUrl().'/wp-json/'.$this->apiVersion().'/products';
        $consumerKey = $this->team->getSetting('woocommerce_consumer_key');
        $consumerSecret = $this->team->getSetting('woocommerce_consumer_secret');

        try
        {
            $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->withOptions(['verify' => $this->verifySsl()])
                ->get($url, [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

            if ($response->successful())
            {
                return $response->json() ?? [];
            }

            Log::warning('WooCommerce API products request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Throwable $e)
        {
            Log::error('WooCommerce API products error', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(int $page = 1, int $perPage = 50): array
    {
        if (! $this->isConfigured())
        {
            return [];
        }

        $url = $this->baseUrl().'/wp-json/'.$this->apiVersion().'/orders';
        $consumerKey = $this->team->getSetting('woocommerce_consumer_key');
        $consumerSecret = $this->team->getSetting('woocommerce_consumer_secret');

        try
        {
            $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->withOptions(['verify' => $this->verifySsl()])
                ->get($url, [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

            if ($response->successful())
            {
                return $response->json() ?? [];
            }

            Log::warning('WooCommerce API orders request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Throwable $e)
        {
            Log::error('WooCommerce API orders error', ['message' => $e->getMessage()]);

            return [];
        }
    }

    public function getStoreUrl(): string
    {
        return $this->baseUrl();
    }
}
