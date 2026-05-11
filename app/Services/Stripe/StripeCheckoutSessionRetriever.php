<?php

namespace App\Services\Stripe;

use App\Contracts\CheckoutSessionRetriever;
use App\Services\StripeAccountResolver;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeCheckoutSessionRetriever implements CheckoutSessionRetriever
{
    public function retrieve(string $sessionId, string $category): ?Session
    {
        Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));

        try
        {
            $session = Session::retrieve([
                'id' => $sessionId,
                'expand' => [
                    'line_items.data.price',
                ],
            ]);
            Log::info('Stripe Checkout Session retrieved', array_merge(
                ['category' => $category],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return $session;
        } catch (\Exception $e)
        {
            Log::error('Stripe checkout session retrieve failed', [
                'session_id' => $sessionId,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
