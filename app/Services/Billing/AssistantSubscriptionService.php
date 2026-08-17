<?php

namespace App\Services\Billing;

use App\Models\Team;
use App\Services\HumanoPricingPlanResolver;
use App\Services\StripeAccountResolver;
use App\Services\TeamCheckoutSessionSubscriptionSyncer;
use App\Services\TeamStripeCustomerService;
use App\Support\StripeErrorMessage;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;

class AssistantSubscriptionService
{
    public function __construct(
        private TeamStripeCustomerService $customerService,
        private TeamCheckoutSessionSubscriptionSyncer $subscriptionSyncer,
        private HumanoPricingPlanResolver $planResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Team $team): array
    {
        $plan = $this->assistantPlanConfig();
        $subscription = $this->findAssistantSubscription($team);
        $stripe = $this->fetchPlatformPaymentData($team);

        $active = $subscription !== null && $this->subscriptionIsActive($subscription);

        return [
            'plan' => [
                'id' => 'assistant',
                'name' => __('humano_pricing.plans.assistant.name'),
                'description' => __('humano_pricing.plans.assistant.description'),
                'monthly_amount' => (string) ($plan['monthly_amount'] ?? ''),
                'yearly_amount' => (string) ($plan['yearly_amount'] ?? ''),
                'currency' => 'EUR',
                'checkout_available' => (bool) ($plan['checkout_available'] ?? false),
            ],
            'subscription' => $subscription ? [
                'active' => $active,
                'status' => (string) $subscription->stripe_status,
                'interval' => $this->intervalForPrice((string) $subscription->stripe_price),
                'stripe_price' => (string) $subscription->stripe_price,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
            'payment_method' => $stripe['payment_method'],
            'invoices' => $stripe['invoices'],
            'can_checkout' => (bool) ($plan['checkout_available'] ?? false) && ! $active,
        ];
    }

    /**
     * @return array{success: bool, message?: string, url?: string}
     */
    public function createCheckout(Team $team, string $interval, string $successUrl, string $cancelUrl): array
    {
        $plan = $this->assistantPlanConfig();
        if (! ($plan['checkout_available'] ?? false))
        {
            return [
                'success' => false,
                'message' => __('El plan Assistant no está disponible para contratar.'),
            ];
        }

        $subscription = $this->findAssistantSubscription($team);
        if ($subscription && $this->subscriptionIsActive($subscription))
        {
            return [
                'success' => false,
                'message' => __('Este equipo ya tiene el plan Assistant activo.'),
            ];
        }

        $priceId = $interval === 'yearly'
            ? trim((string) ($plan['stripe_price_yearly_id'] ?? ''))
            : trim((string) ($plan['stripe_price_monthly_id'] ?? ''));

        if ($priceId === '')
        {
            return [
                'success' => false,
                'message' => __('No hay un precio de Stripe configurado para este intervalo.'),
            ];
        }

        $customerId = $this->customerService->getOrCreateStripeCustomerIdForCategory($team, '');
        if (! $customerId)
        {
            return [
                'success' => false,
                'message' => __('No se pudo crear el cliente de facturación en Stripe.'),
            ];
        }

        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory(''));

            $session = \Stripe\Checkout\Session::create([
                'customer' => $customerId,
                'mode' => 'subscription',
                'locale' => 'es',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => $this->urlWithSessionId($successUrl),
                'cancel_url' => $cancelUrl,
                'allow_promotion_codes' => true,
                'client_reference_id' => (string) $team->id,
                'subscription_data' => [
                    'metadata' => [
                        'team_id' => (string) $team->id,
                        'subscription_type' => 'assistant',
                    ],
                ],
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan' => 'assistant',
                ],
            ]);

            if (! $session->url)
            {
                return [
                    'success' => false,
                    'message' => __('Stripe no devolvió una URL de checkout.'),
                ];
            }

            return [
                'success' => true,
                'url' => $session->url,
            ];
        } catch (\Exception $e)
        {
            Log::error('Assistant checkout session failed', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('Error al crear la sesión de pago: :error', [
                    'error' => StripeErrorMessage::display($e),
                ]),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public function completeCheckout(Team $team, string $sessionId, int $actingUserId): array
    {
        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory(''));
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
        } catch (\Exception $e)
        {
            Log::error('Assistant checkout session retrieve failed', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('No se pudo recuperar la sesión de Stripe.'),
            ];
        }

        $sessionTeamId = (string) ($session->metadata->team_id ?? $session->client_reference_id ?? '');
        if ($sessionTeamId !== '' && $sessionTeamId !== (string) $team->id)
        {
            return [
                'success' => false,
                'message' => __('La sesión de pago no pertenece a este equipo.'),
            ];
        }

        if ($session->mode === 'subscription' && ! in_array($session->status, ['complete', 'open'], true))
        {
            return [
                'success' => false,
                'message' => __('El pago todavía no está completo.'),
            ];
        }

        try
        {
            $this->subscriptionSyncer->sync($team, $session, '', $actingUserId, false);
        } catch (\Exception $e)
        {
            Log::error('Assistant checkout sync failed', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('El pago se recibió, pero no se pudo sincronizar la suscripción.'),
            ];
        }

        return [
            'success' => true,
            'data' => $this->summary($team->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantPlanConfig(): array
    {
        foreach ($this->planResolver->plansForDisplay() as $plan)
        {
            if (($plan['id'] ?? '') === 'assistant')
            {
                return $plan;
            }
        }

        return [];
    }

    private function findAssistantSubscription(Team $team): ?Subscription
    {
        $plan = $this->assistantPlanConfig();
        $priceIds = array_values(array_filter([
            trim((string) ($plan['stripe_price_monthly_id'] ?? '')),
            trim((string) ($plan['stripe_price_yearly_id'] ?? '')),
        ]));

        return $team->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->where(function ($query) use ($priceIds)
            {
                $query->where('type', 'assistant');
                if ($priceIds !== [])
                {
                    $query->orWhereIn('stripe_price', $priceIds);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function subscriptionIsActive(Subscription $subscription): bool
    {
        if (method_exists($subscription, 'active'))
        {
            return $subscription->active();
        }

        return in_array($subscription->stripe_status, ['active', 'trialing'], true)
            && $subscription->ends_at === null;
    }

    private function intervalForPrice(string $priceId): ?string
    {
        $plan = $this->assistantPlanConfig();
        if ($priceId !== '' && $priceId === trim((string) ($plan['stripe_price_yearly_id'] ?? '')))
        {
            return 'yearly';
        }
        if ($priceId !== '' && $priceId === trim((string) ($plan['stripe_price_monthly_id'] ?? '')))
        {
            return 'monthly';
        }

        return null;
    }

    /**
     * @return array{payment_method: ?array<string, mixed>, invoices: list<array<string, mixed>>}
     */
    private function fetchPlatformPaymentData(Team $team): array
    {
        $empty = [
            'payment_method' => null,
            'invoices' => [],
        ];

        $customerId = $this->customerService->getStripeCustomerIdForCategory($team, '');
        if (! $customerId)
        {
            return $empty;
        }

        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory(''));
            $customer = \Stripe\Customer::retrieve($customerId);
            $customerTeamId = $customer->metadata->team_id ?? null;
            if ($customerTeamId && (int) $customerTeamId !== (int) $team->id)
            {
                return $empty;
            }

            $invoices = \Stripe\Invoice::all([
                'customer' => $customerId,
                'limit' => 12,
            ]);

            $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $customerId,
                'type' => 'card',
            ]);

            $defaultId = $customer->invoice_settings->default_payment_method ?? null;
            $card = null;
            foreach ($paymentMethods->data as $method)
            {
                if ($defaultId && $method->id === $defaultId)
                {
                    $card = $method;
                    break;
                }
            }
            $card ??= $paymentMethods->data[0] ?? null;

            return [
                'payment_method' => $card && $card->card ? [
                    'brand' => (string) $card->card->brand,
                    'last4' => (string) $card->card->last4,
                    'exp_month' => (int) $card->card->exp_month,
                    'exp_year' => (int) $card->card->exp_year,
                ] : null,
                'invoices' => collect($invoices->data)->map(function ($invoice): array
                {
                    return [
                        'id' => (string) $invoice->id,
                        'number' => $invoice->number ? (string) $invoice->number : null,
                        'status' => (string) $invoice->status,
                        'amount_paid' => (int) $invoice->amount_paid,
                        'amount_due' => (int) $invoice->amount_due,
                        'currency' => strtoupper((string) $invoice->currency),
                        'created_at' => isset($invoice->created)
                            ? date('c', (int) $invoice->created)
                            : null,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url
                            ? (string) $invoice->hosted_invoice_url
                            : null,
                    ];
                })->values()->all(),
            ];
        } catch (\Exception $e)
        {
            Log::warning('Could not load Assistant Stripe payment data', array_merge([
                'team_id' => $team->id,
            ], StripeErrorMessage::logContext($e)));

            return $empty;
        }
    }

    private function urlWithSessionId(string $url): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'session_id={CHECKOUT_SESSION_ID}';
    }
}
