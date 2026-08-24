<?php

namespace App\Services\Billing;

use App\Models\AgentConversationMessage;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Services\HumanoPricingPlanResolver;
use App\Services\StripeAccountResolver;
use App\Services\TeamApiUsageStatsService;
use App\Services\TeamCheckoutSessionSubscriptionSyncer;
use App\Services\TeamStripeCustomerService;
use App\Services\TeamWhatsAppUsageStatsService;
use App\Support\HumanoPricingCatalog;
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
    public function summary(Team $team, string $catalog = 'assistant'): array
    {
        $catalog = $this->normalizeCatalog($catalog);
        $plans = $this->catalogPlanConfigs($catalog);
        $currentConfig = $plans[0] ?? [];
        $subscription = $this->findCatalogSubscription($team, $plans);
        if ($subscription)
        {
            $matched = $this->planConfigForPrice((string) $subscription->stripe_price, $plans);
            if ($matched !== [])
            {
                $currentConfig = $matched;
            }
        }
        $stripe = $this->fetchPlatformPaymentData($team, $subscription);
        $customerMissing = (bool) ($stripe['customer_missing'] ?? false);

        $active = ! $customerMissing
            && $subscription !== null
            && $this->subscriptionIsActive($subscription);
        $canCheckout = $this->catalogHasCheckout($plans) && ! $active;

        return [
            'plan' => $this->planPayload($currentConfig),
            'plans' => array_map(fn (array $plan): array => $this->planPayload($plan), $plans),
            'subscription' => $subscription ? [
                'active' => $active,
                'status' => (string) $subscription->stripe_status,
                'interval' => $this->intervalForPrice((string) $subscription->stripe_price),
                'stripe_price' => (string) $subscription->stripe_price,
                'plan_id' => (string) ($currentConfig['id'] ?? ''),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'current_period_start' => $stripe['current_period_start'],
                'current_period_end' => $stripe['current_period_end'],
            ] : null,
            'payment_method' => $stripe['payment_method'],
            'payment_methods' => $stripe['payment_methods'],
            'invoices' => $stripe['invoices'],
            'can_checkout' => $canCheckout,
            'subscription_code' => $this->shareableSubscriptionCode($team, $subscription),
            'token_usage' => $this->tokenUsagePayload($team, $stripe),
            'whatsapp_usage' => $this->whatsappUsagePayload($team, $stripe),
        ];
    }

    /**
     * @return array{success: bool, message?: string, url?: string}
     */
    public function createCheckout(
        Team $team,
        string $interval,
        string $successUrl,
        string $cancelUrl,
        string $planId = 'assistant',
    ): array {
        $planId = $this->normalizePlanId($planId);
        $plan = $this->planConfigById($planId);
        if ($plan === [] || ! ($plan['checkout_available'] ?? false))
        {
            return [
                'success' => false,
                'message' => __('El plan no está disponible para contratar.'),
            ];
        }

        $catalog = $this->catalogForPlanId($planId);
        $subscription = $this->findCatalogSubscription($team, $this->catalogPlanConfigs($catalog));
        if ($subscription && $this->subscriptionIsActive($subscription))
        {
            return [
                'success' => false,
                'message' => __('Este equipo ya tiene un plan activo.'),
            ];
        }

        $yearlyPriceId = trim((string) ($plan['stripe_price_yearly_id'] ?? ''));
        $monthlyPriceId = trim((string) ($plan['stripe_price_monthly_id'] ?? ''));
        $priceId = $interval === 'yearly' && $yearlyPriceId !== ''
            ? $yearlyPriceId
            : $monthlyPriceId;

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
                        'subscription_type' => (string) ($plan['subscription_type'] ?? $planId),
                    ],
                ],
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan' => $planId,
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

            if (StripeErrorMessage::isMissingCustomer($e))
            {
                $this->customerService->forgetPersistedCustomerId($team);

                return $this->needsBillingResponse();
            }

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
    public function completeCheckout(Team $team, string $sessionId, int $actingUserId, string $catalog = 'assistant'): array
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

            return $this->completePaymentMethodSetup($team, $session, $catalog);
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

        $sessionPlan = (string) ($session->metadata->plan ?? '');
        $resolvedCatalog = $this->normalizeCatalog(
            $catalog !== 'assistant' ? $catalog : $this->catalogForPlanId($sessionPlan),
        );

        return [
            'success' => true,
            'data' => $this->summary($team->fresh(), $resolvedCatalog),
        ];
    }

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public function cancel(Team $team, string $reason, ?string $comment = null, string $catalog = 'assistant'): array
    {
        $catalog = $this->normalizeCatalog($catalog);
        $subscription = $this->findCatalogSubscription($team, $this->catalogPlanConfigs($catalog));
        if (! $subscription || ! $this->subscriptionIsActive($subscription))
        {
            return [
                'success' => false,
                'message' => __('No hay una suscripción activa.'),
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
                'data' => $this->summary($team->fresh(), $catalog),
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
    public function resume(Team $team, string $catalog = 'assistant'): array
    {
        $catalog = $this->normalizeCatalog($catalog);
        $subscription = $this->findCatalogSubscription($team, $this->catalogPlanConfigs($catalog));
        if (! $subscription || ! $this->subscriptionIsActive($subscription))
        {
            return [
                'success' => false,
                'message' => __('No hay una suscripción activa.'),
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
                'data' => $this->summary($team->fresh(), $catalog),
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

            if (StripeErrorMessage::isMissingCustomer($e))
            {
                $this->customerService->forgetPersistedCustomerId($team);

                return $this->needsBillingResponse();
            }

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
    private function completePaymentMethodSetup(Team $team, object $session, string $catalog = 'assistant'): array
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

            $catalog = $this->normalizeCatalog($catalog);
            $subscription = $this->findCatalogSubscription($team, $this->catalogPlanConfigs($catalog));
            if ($subscription?->stripe_id)
            {
                \Stripe\Subscription::update($subscription->stripe_id, [
                    'default_payment_method' => $paymentMethodId,
                ]);
            }

            return [
                'success' => true,
                'data' => $this->summary($team->fresh(), $catalog),
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
        [$from, $to] = $this->tokenUsagePeriod($team, $stripe);
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
        $rate = TeamApiUsageStatsService::sellRatePerMillion();
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

    /**
     * @param  array<string, mixed>  $stripe
     * @return array<string, mixed>
     */
    private function whatsappUsagePayload(Team $team, array $stripe): array
    {
        [$from, $to] = $this->tokenUsagePeriod($team, $stripe);
        $stats = TeamWhatsAppUsageStatsService::forTeam($team, $from, $to);

        return [
            'messages_sent' => (int) $stats['messages_sent'],
            'our_amount_cents' => (int) $stats['our_amount_cents'],
            'reference_amount_cents' => (int) $stats['reference_amount_cents'],
            'saved_amount_cents' => (int) $stats['saved_amount_cents'],
            'average_savings' => (float) $stats['average_savings'],
            'our_rate' => (float) $stats['our_rate'],
            'reference_rate' => (float) $stats['reference_rate'],
            'currency' => (string) $stats['currency'],
            'period_start' => $from->toIso8601String(),
            'period_end' => $to->toIso8601String(),
            'period_messages_sent' => (int) $stats['messages_sent'],
            'amount_due_cents' => (int) $stats['our_amount_cents'],
        ];
    }

    /**
     * Paid Assistant cycle when it exists. Otherwise from the first real use
     * (tokens, chat, WhatsApp) — not the 48h trial stamp, not another product's invoice.
     *
     * @param  array<string, mixed>  $stripe
     * @return array{0: Carbon, 1: Carbon}
     */
    private function tokenUsagePeriod(Team $team, array $stripe): array
    {
        if (! empty($stripe['current_period_start']))
        {
            $from = Carbon::parse($stripe['current_period_start']);
            $to = ! empty($stripe['current_period_end'])
                ? Carbon::parse($stripe['current_period_end'])
                : now();

            return [$from, $to];
        }

        return [$this->assistantUsageStartedAt($team), now()];
    }

    private function assistantUsageStartedAt(Team $team): Carbon
    {
        $firstUsage = $this->firstAssistantUsageAt($team);
        if ($firstUsage)
        {
            return $firstUsage;
        }

        $stored = $this->parseTimestamp($team->getSetting(self::trialStartedSettingKey('assistant')));
        if ($stored)
        {
            return $stored;
        }

        $hours = (int) config('humano_pricing.app_trials.assistant', 0);
        if ($hours > 0 && $team->created_at && $team->created_at->copy()->addHours($hours)->isFuture())
        {
            return $team->created_at->copy();
        }

        $subscription = $this->findAssistantSubscription($team);
        if ($subscription?->created_at)
        {
            return $subscription->created_at->copy();
        }

        return $team->created_at?->copy() ?? now();
    }

    private function firstAssistantUsageAt(Team $team): ?Carbon
    {
        $times = [];

        $tokenAt = TokenUsageLog::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->min('created_at');
        if ($tokenAt)
        {
            $times[] = Carbon::parse($tokenAt);
        }

        $chatAt = AgentConversationMessage::query()
            ->where('role', 'assistant')
            ->whereHas('conversation', function ($query) use ($team): void
            {
                $query->where('team_id', $team->id);
            })
            ->min('created_at');
        if ($chatAt)
        {
            $times[] = Carbon::parse($chatAt);
        }

        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        if ($teamNumber !== '')
        {
            $whatsappAt = Conversation::query()
                ->where('channel', 'whatsapp')
                ->where('direction', 'outbound')
                ->where(function ($query) use ($teamNumber): void
                {
                    $query->where('from', $teamNumber)
                        ->orWhere('from', 'like', $teamNumber.':%');
                })
                ->min('created_at');
            if ($whatsappAt)
            {
                $times[] = Carbon::parse($whatsappAt);
            }
        }

        if ($times === [])
        {
            return null;
        }

        usort($times, fn (Carbon $a, Carbon $b): int => $a->timestamp <=> $b->timestamp);

        return $times[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantPlanConfig(): array
    {
        return $this->planConfigById('assistant');
    }

    /**
     * @return array<string, mixed>
     */
    private function planConfigById(string $planId): array
    {
        foreach ($this->planResolver->plansForDisplay() as $plan)
        {
            if (($plan['id'] ?? '') === $planId)
            {
                return $plan;
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogPlanConfigs(string $catalog): array
    {
        return $this->planResolver->plansForCatalog($this->normalizeCatalog($catalog));
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function catalogHasCheckout(array $plans): bool
    {
        foreach ($plans as $plan)
        {
            if ($plan['checkout_available'] ?? false)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function planPayload(array $plan): array
    {
        $id = (string) ($plan['id'] ?? '');
        $name = trim((string) ($plan['name'] ?? ''));
        $description = trim((string) ($plan['description'] ?? ''));
        if ($name === '' && $id !== '')
        {
            $name = (string) __('humano_pricing.plans.'.$id.'.name');
        }
        if ($description === '' && $id !== '')
        {
            $description = (string) __('humano_pricing.plans.'.$id.'.description');
        }

        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'monthly_amount' => (string) ($plan['monthly_amount'] ?? ''),
            'yearly_amount' => (string) ($plan['yearly_amount'] ?? ''),
            'currency' => 'EUR',
            'checkout_available' => (bool) ($plan['checkout_available'] ?? false),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @return array<string, mixed>
     */
    private function planConfigForPrice(string $priceId, array $plans): array
    {
        $priceId = trim($priceId);
        if ($priceId === '')
        {
            return [];
        }

        foreach ($plans as $plan)
        {
            $monthly = trim((string) ($plan['stripe_price_monthly_id'] ?? ''));
            $yearly = trim((string) ($plan['stripe_price_yearly_id'] ?? ''));
            if ($priceId === $monthly || $priceId === $yearly)
            {
                return $plan;
            }
        }

        return [];
    }

    private function normalizeCatalog(string $catalog): string
    {
        return HumanoPricingCatalog::normalize($catalog) ?? 'assistant';
    }

    private function normalizePlanId(string $planId): string
    {
        $planId = strtolower(trim($planId));
        $planId = match ($planId)
        {
            'basic' => 'mailer_basic',
            'foundation' => 'mailer_foundation',
            'scale' => 'mailer_scale',
            default => $planId,
        };

        $ids = collect(config('humano_pricing.plans', []))
            ->pluck('id')
            ->map(fn (mixed $id): string => strtolower(trim((string) $id)))
            ->all();

        return in_array($planId, $ids, true) ? $planId : 'assistant';
    }

    private function catalogForPlanId(string $planId): string
    {
        $plan = $this->planConfigById($this->normalizePlanId($planId));

        return $this->normalizeCatalog((string) ($plan['catalog'] ?? 'assistant'));
    }

    public function isInEffectForTeam(Team $team): bool
    {
        return $this->catalogHasActiveSubscription($team, 'assistant');
    }

    public function teamHasPaidAccessForAi(Team $team): bool
    {
        return $this->accessForCatalog($team, 'assistant')['active'];
    }

    /**
     * Single per-app gate: complimentary team whitelist, that catalog's paid
     * subscription, or that catalog's trial. Hosting or another product never
     * unlocks a different catalog.
     *
     * @return array{active: bool, status: 'paid'|'trial'|'expired', trial_ends_at: ?string, locked_reason: string|null}
     */
    public function accessForCatalog(Team $team, string $catalog = 'assistant'): array
    {
        $catalog = $this->normalizeCatalog($catalog);
        $trialEnds = $this->trialEndsAt($team, $catalog);

        if ($catalog === 'assistant' && ! config('humano_pricing.require_paid_plan_for_ai', true))
        {
            return $this->accessPayload(true, 'paid', $trialEnds);
        }

        if ($this->teamHasComplimentaryAccess($team))
        {
            return $this->accessPayload(true, 'paid', $trialEnds);
        }

        if ($this->catalogHasActiveSubscription($team, $catalog))
        {
            return $this->accessPayload(true, 'paid', $trialEnds);
        }

        if ($trialEnds && $trialEnds->isFuture())
        {
            return $this->accessPayload(true, 'trial', $trialEnds);
        }

        return $this->accessPayload(false, 'expired', $trialEnds);
    }

    /**
     * @return array<string, array{active: bool, status: 'paid'|'trial'|'expired', trial_ends_at: ?string, locked_reason: string|null}>
     */
    public function appsPayload(Team $team): array
    {
        return [
            'assistant' => $this->accessForCatalog($team, 'assistant'),
            'mailer' => $this->accessForCatalog($team, 'mailer'),
            'platform' => $this->accessForCatalog($team, 'platform'),
        ];
    }

    private function findAssistantSubscription(Team $team): ?Subscription
    {
        return $this->findCatalogSubscription($team, $this->catalogPlanConfigs('assistant'));
    }

    private function catalogHasActiveSubscription(Team $team, string $catalog): bool
    {
        $subscription = $this->findCatalogSubscription($team, $this->catalogPlanConfigs($catalog));

        return $subscription !== null && $this->subscriptionIsActive($subscription);
    }

    private function teamHasComplimentaryAccess(Team $team): bool
    {
        $ids = config('humano_pricing.plan_access_team_ids', []);
        if (! is_array($ids) || $ids === [])
        {
            return false;
        }

        return in_array((int) $team->id, array_map('intval', $ids), true);
    }

    public static function trialStartedSettingKey(string $catalog): string
    {
        return 'app_trial_started_'.$catalog;
    }

    private function trialEndsAt(Team $team, string $catalog): ?Carbon
    {
        $hours = (int) config('humano_pricing.app_trials.'.$catalog, 0);
        if ($hours <= 0)
        {
            return null;
        }

        $started = $this->trialStartedAt($team, $catalog, $hours);

        return $started?->copy()->addHours($hours);
    }

    private function trialStartedAt(Team $team, string $catalog, int $hours): ?Carbon
    {
        $key = self::trialStartedSettingKey($catalog);
        $stored = $this->parseTimestamp($team->getSetting($key));
        if ($stored)
        {
            return $stored;
        }

        if ($team->created_at && $team->created_at->copy()->addHours($hours)->isFuture())
        {
            return $team->created_at->copy();
        }

        $started = now();
        $team->setSetting($key, $started->toIso8601String(), [
            'type' => 'string',
            'group' => 'billing',
        ]);

        if ($team->relationLoaded('settings'))
        {
            $team->unsetRelation('settings');
        }

        return $started;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '')
        {
            return null;
        }

        try
        {
            return Carbon::parse($value);
        } catch (\Throwable)
        {
            return null;
        }
    }

    /**
     * @return array{active: bool, status: 'paid'|'trial'|'expired', trial_ends_at: ?string, locked_reason: string|null}
     */
    private function accessPayload(bool $active, string $status, ?Carbon $trialEnds): array
    {
        return [
            'active' => $active,
            'status' => $status,
            'trial_ends_at' => $trialEnds?->toIso8601String(),
            'locked_reason' => $active ? null : 'plan',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function shareableSubscriptionCode(Team $team, ?Subscription $subscription): ?string
    {
        $subscriptionId = trim((string) ($subscription?->stripe_id ?? ''));
        if ($subscriptionId !== '')
        {
            return $subscriptionId;
        }

        $customerId = trim((string) ($team->stripe_id ?? ''));

        return $customerId !== '' ? $customerId : null;
    }

    private function findCatalogSubscription(Team $team, array $plans): ?Subscription
    {
        $types = [];
        $priceIds = [];
        foreach ($plans as $plan)
        {
            $id = trim((string) ($plan['id'] ?? ''));
            if ($id !== '')
            {
                $types[] = $id;
            }
            $subscriptionType = trim((string) ($plan['subscription_type'] ?? ''));
            if ($subscriptionType !== '')
            {
                $types[] = $subscriptionType;
            }
            foreach (['stripe_price_monthly_id', 'stripe_price_yearly_id'] as $key)
            {
                $priceId = trim((string) ($plan[$key] ?? ''));
                if ($priceId !== '')
                {
                    $priceIds[] = $priceId;
                }
            }
        }

        $types = array_values(array_unique($types));
        $priceIds = array_values(array_unique($priceIds));

        return $team->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->where(function ($query) use ($types, $priceIds)
            {
                if ($types !== [])
                {
                    $query->whereIn('type', $types);
                }
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
        $priceId = trim($priceId);
        if ($priceId === '')
        {
            return null;
        }

        foreach ($this->planResolver->plansForDisplay() as $plan)
        {
            if ($priceId === trim((string) ($plan['stripe_price_yearly_id'] ?? '')))
            {
                return 'yearly';
            }
            if ($priceId === trim((string) ($plan['stripe_price_monthly_id'] ?? '')))
            {
                return 'monthly';
            }
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
     * @return array{success: false, code: string, message: string}
     */
    private function needsBillingResponse(): array
    {
        return [
            'success' => false,
            'code' => 'needs_billing',
            'message' => __('Completá los datos de facturación para crear el cliente en Stripe. Después contratá el plan; la tarjeta se pide ahí.'),
        ];
    }

    /**
     * @return array{payment_method: ?array<string, mixed>, payment_methods: list<array<string, mixed>>, invoices: list<array<string, mixed>>, current_period_start: ?string, current_period_end: ?string, customer_missing: bool}
     */
    private function fetchPlatformPaymentData(Team $team, ?Subscription $subscription = null): array
    {
        $empty = [
            'payment_method' => null,
            'payment_methods' => [],
            'invoices' => [],
            'current_period_start' => null,
            'current_period_end' => null,
            'customer_missing' => false,
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
            if ($customer->deleted ?? false)
            {
                $this->customerService->forgetPersistedCustomerId($team);

                return array_merge($empty, ['customer_missing' => true]);
            }
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

            return [
                'customer_missing' => false,
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

            if (StripeErrorMessage::isMissingCustomer($e))
            {
                $this->customerService->forgetPersistedCustomerId($team);

                return array_merge($empty, ['customer_missing' => true]);
            }

            return $empty;
        }
    }

    private function urlWithSessionId(string $url): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'session_id={CHECKOUT_SESSION_ID}';
    }
}
