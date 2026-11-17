<?php

namespace App\Services\Domains;

use App\Services\StripeAccountResolver;
use RuntimeException;
use Stripe\StripeClient;

class DomainCheckoutSessions
{
    public function createCustomer(array $payload): string
    {
        $customer = $this->client()->customers->create($payload);
        $id = trim((string) ($customer->id ?? ''));

        if (! str_starts_with($id, 'cus_'))
        {
            throw new RuntimeException('No se pudo crear el cliente en Stripe.');
        }

        return $id;
    }

    public function create(array $payload): object
    {
        return $this->client()->checkout->sessions->create($payload);
    }

    public function retrieve(string $sessionId): object
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, [
            'expand' => ['subscription'],
        ]);
    }

    protected function client(): StripeClient
    {
        $secret = trim(StripeAccountResolver::secretForCategory('domain'));

        if ($secret === '')
        {
            throw new RuntimeException('Stripe no está configurado.');
        }

        return new StripeClient($secret);
    }
}
