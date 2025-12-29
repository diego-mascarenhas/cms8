<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Subscription;

class StripeService
{
    // Cache de productos para evitar llamadas repetidas
    private static $productCache = [];

    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function getCustomerByEmail($email)
    {
        try
        {
            $customers = Customer::all([
                'email' => $email,
                'limit' => 1,
                'expand' => ['data.tax_ids'],
            ]);

            if (count($customers->data) > 0)
            {
                return $customers->data[0];
            }

            return null;
        } catch (\Exception $e)
        {
            Log::error("Error al buscar customer por email: {$e->getMessage()}");

            throw $e;
        }
    }

    public function getCustomerInvoices($customerId, $limit = 10)
    {
        try
        {
            return Invoice::all([
                'customer' => $customerId,
                'limit' => $limit,
            ]);
        } catch (\Exception $e)
        {
            Log::error("Error al obtener facturas: {$e->getMessage()}");

            throw $e;
        }
    }

    public function getCustomerSubscriptions($customerId, $loadProducts = true)
    {
        try
        {
            $subscriptions = Subscription::all([
                'customer' => $customerId,
                'limit' => 100,
                'expand' => ['data.items.data.price'],
            ]);

            // Cargar products manualmente solo si se solicita
            if ($loadProducts)
            {
                foreach ($subscriptions->data as $subscription)
                {
                    if (isset($subscription->items->data))
                    {
                        foreach ($subscription->items->data as $item)
                        {
                            if (isset($item->price) && is_string($item->price->product))
                            {
                                $item->price->product = $this->getProduct($item->price->product);
                            }
                        }
                    }
                }
            }

            return $subscriptions;
        } catch (\Exception $e)
        {
            Log::error("Error al obtener suscripciones: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Obtener producto con caché en memoria
     */
    private function getProduct($productId)
    {
        if (isset(self::$productCache[$productId]))
        {
            return self::$productCache[$productId];
        }

        try
        {
            $product = Product::retrieve($productId);
            self::$productCache[$productId] = $product;

            return $product;
        } catch (\Exception $e)
        {
            Log::warning("No se pudo cargar producto: {$productId}");

            return (object) ['id' => $productId, 'name' => 'Producto sin nombre'];
        }
    }

    public function getCustomerDataByEmail($email, $useCache = true, $invoiceLimit = 10)
    {
        $cacheKey = "stripe_customer_data_{$email}";

        if ($useCache && Cache::has($cacheKey))
        {
            Log::info('Usando datos de Stripe desde caché', ['email' => $email]);

            return Cache::get($cacheKey);
        }

        try
        {
            $customer = $this->getCustomerByEmail($email);

            if (! $customer)
            {
                return null;
            }

            $data = [
                'customer' => $customer,
                'invoices' => $this->getCustomerInvoices($customer->id, $invoiceLimit),
                'subscriptions' => $this->getCustomerSubscriptions($customer->id, true),
            ];

            // Cachear por 5 minutos
            if ($useCache)
            {
                Cache::put($cacheKey, $data, now()->addMinutes(5));
                Log::info('Datos de Stripe cacheados', ['email' => $email, 'ttl' => '5 min']);
            }

            return $data;
        } catch (\Exception $e)
        {
            Log::error("Error al obtener datos del customer: {$e->getMessage()}");

            throw $e;
        }
    }
}
