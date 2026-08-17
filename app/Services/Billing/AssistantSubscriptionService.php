<?php

namespace App\Services\Billing;

use App\Models\Team;
use App\Services\HumanoPricingPlanResolver;
use App\Services\StripeAccountResolver;
use App\Services\TeamApiUsageStatsService;
use App\Services\TeamCheckoutSessionSubscriptionSyncer;
use App\Services\TeamStripeCustomerService;
use App\Support\StripeErrorMessage;
use Carbon\Carbon;
use Illuminate\Support\Arr;
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
        $stripe = $this->fetchPlatformPaymentData($team, $subscription);

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
                'current_period_start' => $stripe['current_period_start'],
                'current_period_end' => $stripe['current_period_end'],
            ] : null,
            'payment_method' => $stripe['payment_method'],
            'payment_methods' => $stripe['payment_methods'],
            'invoices' => $stripe['invoices'],
            'can_checkout' => (bool) ($plan['checkout_available'] ?? false) && ! $active,
            'token_usage' => $this->tokenUsagePayload($team, $stripe),
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

        if ($session->mode === 'setup')
        {
            if ($session->status !== 'complete')
            {
                return [
                    'success' => false,
                    'message' => __('Todavía no se guardó el medio de pago.'),
                ];
            }

            return $this->completePaymentMethodSetup($team, $session);
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
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public function cancel(Team $team, string $reason, ?string $comment = null): array
    {
        $subscription = $this->findAssistantSubscription($team);
        if (! $subscription || ! $this->subscriptionIsActive($subscription))
        {
            return [
                'success' => false,
                'message' => __('No hay una suscripción Assistant activa.'),
            ];
        }

        if ($subscription->ends_at)
        {
            return [
                'success' => false,
                'message' => __('La suscripción ya está programada para cancelarse.'),
            ];
        }

        if (! $subscription->stripe_id)
        {
            return [
                'success' => false,
                'message' => __('No se encontró la suscripción en Stripe.'),
            ];
        }

        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory(''));
            $cancellationDetails = ['feedback' => $reason];
            $trimmedComment = is_string($comment) ? trim($comment) : '';
            if ($trimmedComment !== '')
            {
                $cancellationDetails['comment'] = $trimmedComment;
            }

            \Stripe\Subscription::update($subscription->stripe_id, [
                'cancel_at_period_end' => true,
                'cancellation_details' => $cancellationDetails,
            ]);

            Log::info('Assistant subscription cancel requested', [
                'team_id' => $team->id,
                'subscription_id' => $subscription->id,
                'reason' => $reason,
            ]);

            $stripeSubscription = \Stripe\Subscription::retrieve([
                'id' => $subscription->stripe_id,
                'expand' => ['items.data'],
            ]);
            [, $periodEnd] = self::periodTimestampsFromStripeSubscription($stripeSubscription);
            if ($periodEnd)
            {
                $subscription->ends_at = Carbon::createFromTimestamp($periodEnd);
                $subscription->save();
            }

            return [
                'success' => true,
                'data' => $this->summary($team->fresh()),
            ];
        } catch (\Exception $e)
        {
            Log::error('Assistant subscription cancel failed', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('Error al cancelar la suscripción: :error', [
                    'error' => StripeErrorMessage::display($e),
                ]),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public function resume(Team $team): array
    {
        $subscription = $this->findAssistantSubscription($team);
        if (! $subscription || ! $this->subscriptionIsActive($subscription))
        {
            return [
                'success' => false,
                'message' => __('No hay una suscripción Assistant activa.'),
            ];
        }

        if (! $subscription->ends_at)
        {
            return [
                'success' => false,
                'message' => __('La suscripción no está programada para cancelarse.'),
            ];
        }

        if (! $subscription->stripe_id)
        {
            return [
                'success' => false,
                'message' => __('No se encontró la suscripción en Stripe.'),
            ];
        }

        try
        {
            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory(''));
            \Stripe\Subscription::update($subscription->stripe_id, [
                'cancel_at_period_end' => false,
            ]);

            $subscription->ends_at = null;
            $subscription->save();

            return [
                'success' => true,
                'data' => $this->summary($team->fresh()),
            ];
        } catch (\Exception $e)
        {
            Log::error('Assistant subscription resume failed', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('Error al reanudar la suscripción: :error', [
                    'error' => StripeErrorMessage::display($e),
                ]),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, url?: string}
     */
    public function createPaymentMethodUpdate(Team $team, string $successUrl, string $cancelUrl): array
    {
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
                'mode' => 'setup',
                'locale' => 'es',
                'currency' => 'eur',
                'success_url' => $this->urlWithSessionId($successUrl),
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $team->id,
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'purpose' => 'payment_method',
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
            Log::error('Assistant payment method session failed', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('Error al abrir el cambio de medio de pago: :error', [
                    'error' => StripeErrorMessage::display($e),
                ]),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    private function completePaymentMethodSetup(Team $team, object $session): array
    {
        try
        {
            $setupIntentRef = $session->setup_intent ?? null;
            $setupIntentId = is_string($setupIntentRef)
                ? $setupIntentRef
                : (is_object($setupIntentRef) ? ($setupIntentRef->id ?? null) : null);

            if (! $setupIntentId)
            {
                return [
                    'success' => false,
                    'message' => __('Stripe no devolvió el medio de pago.'),
                ];
            }

            $setupIntent = \Stripe\SetupIntent::retrieve($setupIntentId);
            $paymentMethodRef = $setupIntent->payment_method ?? null;
            $paymentMethodId = is_string($paymentMethodRef)
                ? $paymentMethodRef
                : (is_object($paymentMethodRef) ? ($paymentMethodRef->id ?? null) : null);

            if (! $paymentMethodId)
            {
                return [
                    'success' => false,
                    'message' => __('Todavía no se guardó el medio de pago.'),
                ];
            }

            $customerId = $this->customerService->getStripeCustomerIdForCategory($team, '');
            if ($customerId)
            {
                \Stripe\Customer::update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]);
            }

            $subscription = $this->findAssistantSubscription($team);
            if ($subscription?->stripe_id)
            {
                \Stripe\Subscription::update($subscription->stripe_id, [
                    'default_payment_method' => $paymentMethodId,
                ]);
            }

            return [
                'success' => true,
                'data' => $this->summary($team->fresh()),
            ];
        } catch (\Exception $e)
        {
            Log::error('Assistant payment method setup failed', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('El medio de pago se recibió, pero no se pudo guardar como predeterminado.'),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $stripe
     * @return array<string, mixed>
     */
    private function tokenUsagePayload(Team $team, array $stripe): array
    {
        [$from, $to] = $this->tokenUsagePeriod($stripe);
        $stats = TeamApiUsageStatsService::forTeam((int) $team->id, $from, $to);
        $byModule = [];

        foreach ($stats['byModule'] as $row)
        {
            $byModule[] = [
                'module_name' => (string) ($row['module_name'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
                'tokens_used' => (int) ($row['tokens_used'] ?? 0),
                'tokens_saved' => (int) ($row['tokens_saved'] ?? 0),
            ];
        }

        $tokensUsed = (int) $stats['totalTokensUsed'];
        $rate = $this->tokenSellRatePerMillion();
        $currency = strtoupper((string) config('humano_pricing.token_billing.currency', 'EUR'));

        return [
            'total_calls' => (int) $stats['totalCalls'],
            'total_tokens_saved' => (int) $stats['totalTokensSaved'],
            'average_savings' => (float) $stats['averageSavings'],
            'total_tokens_used' => $tokensUsed,
            'total_tokens_without_toon' => (int) $stats['totalTokensWithoutToon'],
            'by_module' => $byModule,
            'period_start' => $from->toIso8601String(),
            'period_end' => $to->toIso8601String(),
            'amount_due_cents' => (int) round(($tokensUsed / 1_000_000) * $rate * 100),
            'currency' => $currency,
            'rate_per_million' => $rate,
        ];
    }

    private function tokenSellRatePerMillion(): float
    {
        $cost = (float) config('humano_pricing.token_billing.amount_per_million', 6);
        $markup = max(0, (float) config('humano_pricing.token_billing.markup_percent', 50));

        return round($cost * (1 + ($markup / 100)), 4);
    }

    /**
     * @param  array<string, mixed>  $stripe
     * @return array{0: Carbon, 1: Carbon}
     */
    private function tokenUsagePeriod(array $stripe): array
    {
        $from = ! empty($stripe['current_period_start'])
            ? Carbon::parse($stripe['current_period_start'])
            : now()->startOfMonth();
        $to = ! empty($stripe['current_period_end'])
            ? Carbon::parse($stripe['current_period_end'])
            : now()->endOfMonth();

        return [$from, $to];
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

    public function isInEffectForTeam(Team $team): bool
    {
        $subscription = $this->findAssistantSubscription($team);

        return $subscription !== null && $this->subscriptionIsActive($subscription);
    }

    public function teamHasPaidAccessForAi(Team $team): bool
    {
        if ($this->isInEffectForTeam($team))
        {
            return true;
        }

        foreach ($team->subscriptions()->where('stripe_status', '!=', 'canceled')->get() as $subscription)
        {
            if ($this->subscriptionIsActive($subscription))
            {
                return true;
            }
        }

        return false;
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
     * @param  object|array<string, mixed>  $stripeSubscription
     * @return array{0: ?int, 1: ?int}
     */
    public static function periodTimestampsFromStripeSubscription(object|array $stripeSubscription): array
    {
        $payload = is_array($stripeSubscription)
            ? $stripeSubscription
            : json_decode(json_encode($stripeSubscription), true);

        if (! is_array($payload))
        {
            return [null, null];
        }

        $item = Arr::get($payload, 'items.data.0', []);
        $start = Arr::get($payload, 'current_period_start')
            ?? Arr::get($item, 'current_period_start')
            ?? Arr::get($payload, 'billing_cycle_anchor');
        $end = Arr::get($payload, 'current_period_end')
            ?? Arr::get($item, 'current_period_end');

        return [
            $start !== null && $start !== '' ? (int) $start : null,
            $end !== null && $end !== '' ? (int) $end : null,
        ];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function periodFromLocalSubscription(?Subscription $subscription): array
    {
        if (! $subscription?->stripe_id)
        {
            return [null, null];
        }

        try
        {
            $stripeSubscription = \Stripe\Subscription::retrieve([
                'id' => $subscription->stripe_id,
                'expand' => ['items.data'],
            ]);

            return self::periodTimestampsFromStripeSubscription($stripeSubscription);
        } catch (\Exception $e)
        {
            Log::warning('Could not load Assistant Stripe subscription period', array_merge([
                'stripe_subscription_id' => $subscription->stripe_id,
            ], StripeErrorMessage::logContext($e)));

            return [null, null];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapCardPaymentMethod(object $method, bool $isDefault): ?array
    {
        if (! $method->card)
        {
            return null;
        }

        return [
            'brand' => (string) $method->card->brand,
            'last4' => (string) $method->card->last4,
            'exp_month' => (int) $method->card->exp_month,
            'exp_year' => (int) $method->card->exp_year,
            'is_default' => $isDefault,
        ];
    }

    /**
     * @return array{payment_method: ?array<string, mixed>, payment_methods: list<array<string, mixed>>, invoices: list<array<string, mixed>>, current_period_start: ?string, current_period_end: ?string}
     */
    private function fetchPlatformPaymentData(Team $team, ?Subscription $subscription = null): array
    {
        $empty = [
            'payment_method' => null,
            'payment_methods' => [],
            'invoices' => [],
            'current_period_start' => null,
            'current_period_end' => null,
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
            $cards = collect($paymentMethods->data)
                ->map(fn ($method) => $this->mapCardPaymentMethod(
                    $method,
                    $defaultId !== null && $method->id === $defaultId,
                ))
                ->filter()
                ->values()
                ->all();
            $card = collect($cards)->firstWhere('is_default') ?? ($cards[0] ?? null);
            [$periodStart, $periodEnd] = $this->periodFromLocalSubscription($subscription);
            if (! $periodStart || ! $periodEnd)
            {
                $latestInvoice = $invoices->data[0] ?? null;
                $periodStart ??= $latestInvoice?->period_start
                    ?? $latestInvoice?->lines?->data[0]?->period?->start
                    ?? null;
                $periodEnd ??= $latestInvoice?->period_end
                    ?? $latestInvoice?->lines?->data[0]?->period?->end
                    ?? null;
            }

            return [
                'current_period_start' => $periodStart ? date('c', (int) $periodStart) : null,
                'current_period_end' => $periodEnd ? date('c', (int) $periodEnd) : null,
                'payment_method' => $card,
                'payment_methods' => $cards,
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
                        'invoice_pdf' => $invoice->invoice_pdf
                            ? (string) $invoice->invoice_pdf
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
