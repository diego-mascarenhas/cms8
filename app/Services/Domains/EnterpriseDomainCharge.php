<?php

namespace App\Services\Domains;

use App\Models\Enterprise;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

class EnterpriseDomainCharge
{
    /**
     * Charge the enterprise default card for the annual domain registration.
     *
     * @param  array<string, string>  $metadata
     * @return array{payment_intent_id: string, amount_cents: int, currency: string}
     */
    public function charge(Enterprise $enterprise, int $amountCents, string $currency, string $description, array $metadata = []): array
    {
        if ($amountCents < 50)
        {
            throw new RuntimeException('El importe del dominio no es válido.');
        }

        [$stripe, $customerId, $paymentMethodId] = $this->client($enterprise);
        $currency = strtolower(trim($currency)) ?: 'usd';

        try
        {
            $intent = $stripe->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'off_session' => true,
                'confirm' => true,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        } catch (Throwable $exception)
        {
            report($exception);

            throw new RuntimeException('No se pudo cobrar la tarjeta del cliente por el dominio.');
        }

        $status = (string) ($intent->status ?? '');
        $intentId = trim((string) ($intent->id ?? ''));

        if ($status !== 'succeeded' || $intentId === '')
        {
            throw new RuntimeException('No se pudo cobrar la tarjeta del cliente por el dominio.');
        }

        return [
            'payment_intent_id' => $intentId,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
        ];
    }

    public function refund(string $paymentIntentId, Enterprise $enterprise): void
    {
        $paymentIntentId = trim($paymentIntentId);

        if ($paymentIntentId === '' || ! str_starts_with($paymentIntentId, 'pi_'))
        {
            return;
        }

        try
        {
            [$stripe] = $this->client($enterprise);
            $stripe->refunds->create([
                'payment_intent' => $paymentIntentId,
            ]);
        } catch (Throwable $exception)
        {
            report($exception);
        }
    }

    /**
     * @return array{0: StripeClient, 1: string, 2: string}
     */
    private function client(Enterprise $enterprise): array
    {
        $team = $enterprise->team;
        $secret = trim((string) $team?->getSetting('stripe_secret'));

        if ($secret === '')
        {
            throw new RuntimeException('Stripe no está configurado para el equipo.');
        }

        $customerId = $enterprise->getStripeCustomerId();

        if (! is_string($customerId) || ! str_starts_with($customerId, 'cus_'))
        {
            throw new RuntimeException('La empresa no tiene cliente de Stripe. Guardá una tarjeta antes de registrar el dominio.');
        }

        $stripe = new StripeClient($secret);

        try
        {
            $customer = $stripe->customers->retrieve($customerId, [
                'expand' => ['invoice_settings.default_payment_method'],
            ]);
        } catch (Throwable $exception)
        {
            report($exception);

            throw new RuntimeException('No se pudo leer la tarjeta del cliente en Stripe.');
        }

        $default = $customer->invoice_settings->default_payment_method ?? null;
        $paymentMethodId = is_string($default)
            ? $default
            : (is_object($default) ? trim((string) ($default->id ?? '')) : '');

        if ($paymentMethodId === '' || ! str_starts_with($paymentMethodId, 'pm_'))
        {
            throw new RuntimeException('La empresa no tiene una tarjeta por defecto. Guardá una tarjeta antes de registrar el dominio.');
        }

        return [$stripe, $customerId, $paymentMethodId];
    }
}
