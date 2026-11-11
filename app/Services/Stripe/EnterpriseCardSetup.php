<?php

namespace App\Services\Stripe;

use App\Models\Enterprise;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

class EnterpriseCardSetup
{
    public function __construct(
        private CustomerPaymentMethodReader $cards,
    ) {}

    /**
     * @return array{client_secret: string, publishable_key: string}
     */
    public function begin(Enterprise $enterprise): array
    {
        [$stripe, $publishable, $customerId] = $this->client($enterprise);

        try
        {
            $intent = $stripe->setupIntents->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
            ]);
        } catch (Throwable $exception)
        {
            report($exception);

            throw new RuntimeException('No se pudo preparar la tarjeta en Stripe.');
        }

        $secret = trim((string) $intent->client_secret);

        if ($secret === '')
        {
            throw new RuntimeException('No se pudo preparar la tarjeta en Stripe.');
        }

        return [
            'client_secret' => $secret,
            'publishable_key' => $publishable,
        ];
    }

    /**
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    public function complete(Enterprise $enterprise, string $setupIntentId): ?array
    {
        [$stripe, , $customerId] = $this->client($enterprise);
        $setupIntentId = trim($setupIntentId);

        if (! str_starts_with($setupIntentId, 'seti_'))
        {
            throw new RuntimeException('La confirmación de la tarjeta no es válida.');
        }

        try
        {
            $intent = $stripe->setupIntents->retrieve($setupIntentId);
            $intentCustomer = is_string($intent->customer) ? $intent->customer : ($intent->customer->id ?? '');
            $paymentMethod = $intent->payment_method ?? null;
            $paymentMethodId = is_string($paymentMethod) ? $paymentMethod : (is_object($paymentMethod) ? ($paymentMethod->id ?? '') : '');

            if ($intent->status !== 'succeeded' || $intentCustomer !== $customerId || $paymentMethodId === '')
            {
                throw new RuntimeException('La tarjeta no quedó guardada.');
            }

            $stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        } catch (RuntimeException $exception)
        {
            throw $exception;
        } catch (Throwable $exception)
        {
            report($exception);

            throw new RuntimeException('No se pudo guardar la tarjeta en Stripe.');
        }

        $teamId = $enterprise->team?->id;

        if ($teamId !== null)
        {
            $this->cards->forget($teamId, $customerId);
        }

        return $this->cards->forCustomer($enterprise->team, $customerId);
    }

    public function useDefault(Enterprise $enterprise, string $paymentMethodId): void
    {
        $paymentMethodId = trim($paymentMethodId);

        if (! str_starts_with($paymentMethodId, 'pm_'))
        {
            throw new RuntimeException('La tarjeta no es válida.');
        }

        [$stripe, , $customerId] = $this->client($enterprise);

        try
        {
            $method = $stripe->paymentMethods->retrieve($paymentMethodId);
            $owner = is_string($method->customer) ? $method->customer : (is_object($method->customer) ? ($method->customer->id ?? '') : '');

            if ($owner !== $customerId)
            {
                throw new RuntimeException('La tarjeta no pertenece a esta empresa.');
            }

            $stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        } catch (RuntimeException $exception)
        {
            throw $exception;
        } catch (Throwable $exception)
        {
            report($exception);

            throw new RuntimeException('No se pudo elegir la tarjeta.');
        }

        $teamId = $enterprise->team?->id;

        if ($teamId !== null)
        {
            $this->cards->forget($teamId, $customerId);
        }
    }

    /**
     * @return array{0: StripeClient, 1: string, 2: string}
     */
    private function client(Enterprise $enterprise): array
    {
        $team = $enterprise->team;
        $secret = trim((string) $team?->getSetting('stripe_secret'));
        $publishable = trim((string) $team?->getSetting('stripe_public'));

        if ($secret === '' || $publishable === '')
        {
            throw new RuntimeException('Stripe no está configurado para el equipo.');
        }

        $stripe = new StripeClient($secret);
        $customerId = $enterprise->getStripeCustomerId();

        if (! is_string($customerId) || ! str_starts_with($customerId, 'cus_'))
        {
            try
            {
                $customer = $stripe->customers->create([
                    'name' => (string) $enterprise->name,
                    'metadata' => [
                        'enterprise_id' => (string) $enterprise->id,
                    ],
                ]);
            } catch (Throwable $exception)
            {
                report($exception);

                throw new RuntimeException('No se pudo registrar la empresa en Stripe.');
            }

            $customerId = (string) $customer->id;
            $enterprise->setStripeCustomerId($customerId);
            $enterprise->save();
        }

        return [$stripe, $publishable, $customerId];
    }
}
