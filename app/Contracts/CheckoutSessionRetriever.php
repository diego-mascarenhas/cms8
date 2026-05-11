<?php

namespace App\Contracts;

use Stripe\Checkout\Session;

interface CheckoutSessionRetriever
{
    public function retrieve(string $sessionId, string $category): ?Session;
}
