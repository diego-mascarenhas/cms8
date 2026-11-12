<?php

namespace App\Services\Domains;

use App\Models\Enterprise;
use App\Models\ServiceSync;
use Carbon\Carbon;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

class EnterpriseDomainCharge
{
    /**
     * Create an annual Stripe subscription for the domain and charge the enterprise card now.
     *
     * @param  array<string, string>  $metadata
     * @return array{subscription_id: string, service_sync_id: int, amount_cents: int, currency: string, payment_intent_id: string|null}
     */
    public function subscribe(Enterprise $enterprise, int $amountCents, string $currency, string $description, array $metadata = []): array
    {
        if ($amountCents < 50)
        {
            throw new RuntimeException('El importe del dominio no es válido.');
        }

        [$stripe, $customerId, $paymentMethodId] = $this->client($enterprise);
        $currency = strtolower(trim($currency)) ?: 'usd';
        $referredBy = trim((string) ($enterprise->referred_by ?? ''));

        if ($referredBy !== '')
        {
            $metadata['referred_by'] = $referredBy;
        }

        try
        {
            $subscription = $stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amountCents,
                        'recurring' => [
                            'interval' => 'year',
                        ],
                        'product_data' => [
                            'name' => $description,
                            'metadata' => [
                                'type' => 'domain',
                                'domain' => (string) ($metadata['domain'] ?? ''),
                            ],
                        ],
                    ],
                ]],
                'default_payment_method' => $paymentMethodId,
                'off_session' => true,
                'payment_behavior' => 'error_if_incomplete',
                'metadata' => array_merge([
                    'type' => 'domain',
                    'category' => 'domain',
                ], $metadata),
                'expand' => ['latest_invoice.payment_intent'],
            ]);
        } catch (Throwable $exception)
        {
            report($exception);

            throw new RuntimeException('No se pudo cobrar la tarjeta del cliente por el dominio.');
        }

        $subscriptionId = trim((string) ($subscription->id ?? ''));
        $status = strtolower(trim((string) ($subscription->status ?? '')));

        if ($subscriptionId === '' || ! str_starts_with($subscriptionId, 'sub_') || ! in_array($status, ['active', 'trialing'], true))
        {
            throw new RuntimeException('No se pudo cobrar la tarjeta del cliente por el dominio.');
        }

        $paymentIntentId = null;
        $latestInvoice = $subscription->latest_invoice ?? null;

        if (is_object($latestInvoice))
        {
            $paymentIntent = $latestInvoice->payment_intent ?? null;
            $paymentIntentId = is_string($paymentIntent)
                ? $paymentIntent
                : (is_object($paymentIntent) ? trim((string) ($paymentIntent->id ?? '')) : null);
        }

        $sync = $this->storeServiceSync(
            $enterprise,
            $subscriptionId,
            $customerId,
            $description,
            $amountCents,
            $currency,
            $subscription,
            $metadata,
            $referredBy,
        );

        return [
            'subscription_id' => $subscriptionId,
            'service_sync_id' => $sync->id,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : null,
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
     * @param  array<string, string>  $metadata
     */
    private function storeServiceSync(
        Enterprise $enterprise,
        string $subscriptionId,
        string $customerId,
        string $description,
        int $amountCents,
        string $currency,
        object $subscription,
        array $metadata,
        string $referredBy,
    ): ServiceSync {
        $amount = round($amountCents / 100, 2);
        $periodStart = isset($subscription->current_period_start)
            ? Carbon::createFromTimestamp((int) $subscription->current_period_start)
            : now();
        $periodEnd = isset($subscription->current_period_end)
            ? Carbon::createFromTimestamp((int) $subscription->current_period_end)
            : now()->addYear();

        $data = [
            'type' => 'domain',
            'category' => 'domain',
            'domain' => $metadata['domain'] ?? null,
            'metadata' => $metadata,
        ];

        if ($referredBy !== '')
        {
            $data['referred_by'] = $referredBy;
        }

        return ServiceSync::query()->updateOrCreate(
            [
                'stripe_id' => $subscriptionId,
                'team_id' => $enterprise->team_id,
            ],
            [
                'provider' => 'stripe',
                'type' => 'sell',
                'customer_id' => $customerId,
                'customer_email' => $enterprise->email,
                'customer_name' => $enterprise->name,
                'status' => (string) ($subscription->status ?? 'active'),
                'collection_method' => 'charge_automatically',
                'plan_name' => $description,
                'plan_interval' => 'year',
                'plan_interval_count' => 1,
                'quantity' => 1,
                'price_currency' => strtoupper($currency),
                'unit_amount' => $amount,
                'amount_subtotal' => $amount,
                'amount_total' => $amount,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
                'last_synced_at' => now(),
                'data' => $data,
                'raw_payload' => [
                    'id' => $subscriptionId,
                    'status' => $subscription->status ?? 'active',
                    'metadata' => $metadata,
                ],
            ],
        );
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
