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
            return Session::retrieve($sessionId);
        } catch (\Exception $e)
        {
            Log::warning('Stripe checkout session retrieve failed', [
                'session_id' => $sessionId,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
