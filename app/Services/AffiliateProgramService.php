<?php

namespace App\Services;

use App\Mail\AffiliatePurchaseInvitationMail;
use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Models\ServiceSync;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Support\AffiliateCommission;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AffiliateProgramService
{
    use ConfiguresTeamMail;

    public function __construct(
        private readonly AffiliateReferralLinkBuilder $linkBuilder,
        private readonly TeamStripeCustomerService $stripeCustomers,
    ) {}

    /**
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     referral_code: string|null,
     *     commission_percent: float,
     *     agency_commission_percent: float,
     *     plans: list<array<string, mixed>>,
     *     invitations: list<array<string, mixed>>,
     *     referrals: list<array<string, mixed>>,
     *     commissions_as_referrer: list<array<string, mixed>>,
     *     commissions_as_payer: list<array<string, mixed>>,
     *     totals_as_referrer: array<string, array{paid_cents: int, commission_cents: int}>,
     *     totals_as_payer: array<string, array{paid_cents: int, commission_cents: int}>,
     * }
     */
    public function dashboard(Team $team, ?string $catalog = null): array
    {
        if (! $team->canUseAffiliateProgram())
        {
            return [
                'eligible' => false,
                'reason' => __('Los equipos referidos no pueden usar el programa de afiliados.'),
                'referral_code' => null,
                'commission_percent' => AffiliateCommission::percent(),
                'agency_commission_percent' => AffiliateCommission::agencyPercent(),
                'plans' => [],
                'invitations' => [],
                'referrals' => [],
                'commissions_as_referrer' => [],
                'commissions_as_payer' => [],
                'totals_as_referrer' => [],
                'totals_as_payer' => [],
            ];
        }

        $this->ensureStripeCustomer($team);
        $team->refresh();

        $referralCode = $this->linkBuilder->referralCode($team);

        $commissionsAsReferrer = $team->billingAffiliateCommissionsAsReferrer()
            ->with(['payingTeam.owner'])
            ->latest()
            ->limit(200)
            ->get();

        $commissionsAsPayer = $team->billingAffiliateCommissionsAsPayer()
            ->with(['referrerTeam'])
            ->latest()
            ->limit(200)
            ->get();

        $plans = [];
        foreach ($this->linkBuilder->availablePlans($catalog) as $plan)
        {
            $checkoutUrl = trim((string) ($plan['checkout_url'] ?? ''));
            $plans[] = array_merge($plan, [
                'referral_url' => $referralCode !== null && $checkoutUrl !== ''
                    ? $this->linkBuilder->buildCaptureRedirectLink($checkoutUrl, $referralCode)
                    : null,
            ]);
        }

        $invitations = $team->affiliateInvitations()
            ->with('invitedBy')
            ->latest()
            ->limit(100)
            ->get();

        $referrals = $this->buildReferrals($team, $invitations, $commissionsAsReferrer);
        $totalsAsReferrer = $this->sumCommissionsByCurrency($commissionsAsReferrer);
        if ($totalsAsReferrer === [])
        {
            $totalsAsReferrer = $this->sumReferralCommissions($referrals);
        }

        return [
            'eligible' => true,
            'reason' => null,
            'referral_code' => $referralCode,
            'commission_percent' => AffiliateCommission::percent(),
            'agency_commission_percent' => AffiliateCommission::agencyPercent(),
            'plans' => $plans,
            'invitations' => $invitations->map(fn (AffiliateInvitation $invitation): array => $this->serializeInvitation($invitation))->values()->all(),
            'referrals' => $referrals,
            'commissions_as_referrer' => $commissionsAsReferrer->map(fn (BillingAffiliateCommission $row): array => $this->serializeCommission($row, 'paying'))->values()->all(),
            'commissions_as_payer' => $commissionsAsPayer->map(fn (BillingAffiliateCommission $row): array => $this->serializeCommission($row, 'referrer'))->values()->all(),
            'totals_as_referrer' => $totalsAsReferrer,
            'totals_as_payer' => $this->sumCommissionsByCurrency($commissionsAsPayer),
        ];
    }

    public function ensureStripeCustomer(Team $team): bool
    {
        if (trim((string) ($team->stripe_id ?? '')) !== '')
        {
            return true;
        }

        try
        {
            $customerId = $this->stripeCustomers->getOrCreateStripeCustomerIdForCategory($team, 'mailer');
            $team->refresh();

            return $customerId !== null && trim((string) $team->stripe_id) !== '';
        } catch (\Throwable $e)
        {
            Log::warning('Unable to create Stripe customer for affiliate referrals', [
                'team_id' => $team->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array{invite_name: string, invite_email: string, invite_plan: string, catalog?: string|null}  $data
     * @return array{invitation: array<string, mixed>}
     */
    public function sendInvitation(User $user, Team $team, array $data): array
    {
        if (! $team->canUseAffiliateProgram())
        {
            throw ValidationException::withMessages([
                'invite_email' => __('Los equipos referidos no pueden usar el programa de afiliados.'),
            ]);
        }

        $referralCode = $this->linkBuilder->referralCode($team);

        if ($referralCode === null)
        {
            $this->ensureStripeCustomer($team);
            $team->refresh();
            $referralCode = $this->linkBuilder->referralCode($team);
        }

        if ($referralCode === null)
        {
            throw ValidationException::withMessages([
                'invite_email' => __('No pudimos activar tu código de referido. Activá Stripe e intentalo de nuevo.'),
            ]);
        }

        $planId = (string) $data['invite_plan'];
        $plan = collect($this->linkBuilder->availablePlans($data['catalog'] ?? null))->firstWhere('id', $planId);

        if ($plan === null || trim((string) ($plan['checkout_url'] ?? '')) === '')
        {
            throw ValidationException::withMessages([
                'invite_plan' => __('The selected plan is not available.'),
            ]);
        }

        $checkoutUrl = $this->linkBuilder->buildCaptureRedirectLink(
            $plan['checkout_url'],
            $referralCode,
            (string) $data['invite_email'],
        );

        $planMarketing = $this->linkBuilder->planMarketing($planId, 'es_ES') ?? [
            'name' => $plan['name'],
            'description' => '',
            'features' => [],
            'image_url' => $this->linkBuilder->planImageUrl($planId),
        ];

        $invitation = AffiliateInvitation::query()->create([
            'team_id' => $team->id,
            'invited_by_user_id' => $user->id,
            'invitee_name' => (string) $data['invite_name'],
            'invitee_email' => (string) $data['invite_email'],
            'plan_id' => $planId,
            'plan_name' => $planMarketing['name'],
            'tracking_token' => AffiliateInvitation::generateTrackingToken(),
            'sent_at' => now(),
        ]);

        $this->configureMailForTeam($team);

        Mail::to((string) $data['invite_email'])->send(
            new AffiliatePurchaseInvitationMail(
                $invitation,
                $team,
                $user,
                (string) $data['invite_name'],
                $planMarketing['name'],
                $planMarketing['description'],
                $planMarketing['features'],
                $planMarketing['image_url'],
                $checkoutUrl,
                $this->linkBuilder->pricingPageUrl(),
            ),
        );

        return [
            'invitation' => $this->serializeInvitation($invitation->loadMissing('invitedBy')),
        ];
    }

    /**
     * Link an already-subscribed team to this referrer using the shareable subscription code.
     *
     * @return array<string, mixed>
     */
    public function claimReferral(Team $referrer, string $subscriptionCode): array
    {
        if (! $referrer->canUseAffiliateProgram())
        {
            throw ValidationException::withMessages([
                'subscription_code' => __('Los equipos referidos no pueden usar el programa de afiliados.'),
            ]);
        }

        $this->ensureStripeCustomer($referrer);
        $referrer->refresh();
        $referrerCode = $this->linkBuilder->referralCode($referrer);

        if ($referrerCode === null)
        {
            throw ValidationException::withMessages([
                'subscription_code' => __('Activá tu código de referido e intentalo de nuevo.'),
            ]);
        }

        $target = $this->resolveClaimTarget($subscriptionCode);

        if ($target === null)
        {
            throw ValidationException::withMessages([
                'subscription_code' => __('No encontramos una suscripción con ese código.'),
            ]);
        }

        [$payingTeam, $subscription] = $target;

        if ((int) $payingTeam->id === (int) $referrer->id)
        {
            throw ValidationException::withMessages([
                'subscription_code' => __('No podés incorporar tu propia suscripción.'),
            ]);
        }

        if ($subscription !== null)
        {
            $this->claimSpecificSubscription($payingTeam, $subscription, $referrerCode);
        } else
        {
            $this->claimCustomer($payingTeam, $referrerCode);
        }

        return $this->serializeReferralFromTeam(
            $payingTeam->unsetRelation('subscriptions')->loadMissing('owner'),
            $referrer->billingAffiliateCommissionsAsReferrer()
                ->where('paying_team_id', $payingTeam->id)
                ->get()
                ->all(),
            $referrerCode,
        );
    }

    /**
     * @return array{0: Team, 1: Subscription|null}|null
     */
    private function resolveClaimTarget(string $code): ?array
    {
        $code = trim($code);
        if ($code === '')
        {
            return null;
        }

        if (str_starts_with(strtolower($code), 'cus_'))
        {
            $team = Team::findByStripeCustomerId($code);

            return $team !== null ? [$team, null] : null;
        }

        $subscription = Subscription::query()
            ->where('stripe_id', $code)
            ->first();

        if ($subscription?->team === null)
        {
            return null;
        }

        return [$subscription->team, $subscription];
    }

    private function claimSpecificSubscription(Team $payingTeam, Subscription $subscription, string $referrerCode): void
    {
        $existingSubscriptionReferrer = trim((string) ($subscription->referred_by ?? ''));
        if ($existingSubscriptionReferrer !== '' && strcasecmp($existingSubscriptionReferrer, $referrerCode) !== 0)
        {
            throw ValidationException::withMessages([
                'subscription_code' => __('Esa suscripción ya tiene un referente.'),
            ]);
        }

        if ($existingSubscriptionReferrer === '')
        {
            $subscription->forceFill([
                'referred_by' => $referrerCode,
                'affiliate_commission_percent' => AffiliateCommission::percent(),
            ])->save();
        }

        if (trim((string) ($payingTeam->referred_by ?? '')) === '')
        {
            $payingTeam->forceFill(['referred_by' => $referrerCode])->save();
        }
    }

    private function claimCustomer(Team $payingTeam, string $referrerCode): void
    {
        $existing = trim((string) ($payingTeam->referred_by ?? ''));
        if ($existing !== '' && strcasecmp($existing, $referrerCode) !== 0)
        {
            throw ValidationException::withMessages([
                'subscription_code' => __('Esa suscripción ya tiene un referente.'),
            ]);
        }

        if ($existing === '')
        {
            $payingTeam->forceFill(['referred_by' => $referrerCode])->save();
        }

        $this->stampUnattributedSubscriptions(
            $payingTeam,
            $referrerCode,
            AffiliateCommission::agencyPercent(),
        );
    }

    private function stampUnattributedSubscriptions(Team $payingTeam, string $referrerCode, float $percent): void
    {
        foreach ($payingTeam->subscriptions as $subscription)
        {
            $existing = trim((string) ($subscription->referred_by ?? ''));
            if ($existing !== '')
            {
                continue;
            }

            $subscription->forceFill([
                'referred_by' => $referrerCode,
                'affiliate_commission_percent' => $percent,
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvitation(AffiliateInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'invitee_name' => $invitation->invitee_name,
            'invitee_email' => $invitation->invitee_email,
            'plan_id' => $invitation->plan_id,
            'plan_name' => $invitation->plan_name,
            'sent_at' => $invitation->sent_at?->toIso8601String(),
            'opened_at' => $invitation->opened_at?->toIso8601String(),
            'clicked_at' => $invitation->clicked_at?->toIso8601String(),
            'invited_by' => $invitation->invitedBy?->name,
            'status' => $invitation->statusLabel(),
        ];
    }

    /**
     * @param  'paying'|'referrer'  $counterparty
     * @return array<string, mixed>
     */
    private function serializeCommission(BillingAffiliateCommission $row, string $counterparty): array
    {
        $team = $counterparty === 'paying' ? $row->payingTeam : $row->referrerTeam;

        return [
            'id' => $row->id,
            'stripe_invoice_id' => $row->stripe_invoice_id,
            'currency' => strtoupper((string) $row->currency),
            'amount_paid_cents' => (int) $row->amount_paid_cents,
            'commission_percent' => (float) $row->commission_percent,
            'commission_amount_cents' => (int) $row->commission_amount_cents,
            'created_at' => $row->created_at?->toIso8601String(),
            'counterparty_team' => $team?->name,
        ];
    }

    /**
     * @param  Collection<int, AffiliateInvitation>  $invitations
     * @param  Collection<int, BillingAffiliateCommission>  $commissions
     * @return list<array<string, mixed>>
     */
    private function buildReferrals(Team $team, Collection $invitations, Collection $commissions): array
    {
        $referralCode = $this->linkBuilder->referralCode($team);

        $referredTeams = Team::query()
            ->with(['owner', 'subscriptions'])
            ->when(
                $referralCode !== null,
                fn ($query) => $query->where('referred_by', $referralCode),
                fn ($query) => $query->whereRaw('0 = 1'),
            )
            ->get();

        /** @var array<string, list<BillingAffiliateCommission>> $commissionsByEmail */
        $commissionsByEmail = [];
        foreach ($commissions as $row)
        {
            $email = $this->normalizedEmail($row->payingTeam?->owner?->email);
            if ($email === '')
            {
                continue;
            }

            $commissionsByEmail[$email] ??= [];
            $commissionsByEmail[$email][] = $row;
        }

        /** @var array<string, Team> $teamsByEmail */
        $teamsByEmail = [];
        foreach ($referredTeams as $referred)
        {
            $email = $this->normalizedEmail($referred->owner?->email);
            if ($email === '')
            {
                continue;
            }

            $teamsByEmail[$email] = $referred;
        }

        $usedEmails = [];
        $referrals = [];

        foreach ($invitations as $invitation)
        {
            $email = $this->normalizedEmail($invitation->invitee_email);
            if ($email !== '')
            {
                $usedEmails[$email] = true;
            }

            array_push(
                $referrals,
                ...$this->serializeInvitationReferrals(
                    $invitation,
                    $teamsByEmail[$email] ?? null,
                    $commissionsByEmail[$email] ?? [],
                    $referralCode,
                ),
            );
        }

        foreach ($teamsByEmail as $email => $referred)
        {
            if (isset($usedEmails[$email]))
            {
                continue;
            }

            $usedEmails[$email] = true;
            array_push($referrals, ...$this->serializeTeamReferrals($referred, $commissionsByEmail[$email] ?? [], $referralCode));
        }

        foreach ($commissions as $row)
        {
            $email = $this->normalizedEmail($row->payingTeam?->owner?->email);
            if ($email !== '' && isset($usedEmails[$email]))
            {
                continue;
            }

            if ($email !== '' && $row->payingTeam !== null)
            {
                $usedEmails[$email] = true;
                array_push($referrals, ...$this->serializeTeamReferrals($row->payingTeam, $commissionsByEmail[$email], $referralCode));

                continue;
            }

            $referrals[] = $this->serializeReferralFromCommission($row);
        }

        return $this->sortReferralsByRenewal($referrals);
    }

    /**
     * @param  list<BillingAffiliateCommission>  $commissionRows
     * @return list<array<string, mixed>>
     */
    private function serializeInvitationReferrals(AffiliateInvitation $invitation, ?Team $referred, array $commissionRows, ?string $referrerCode = null): array
    {
        if ($referred !== null)
        {
            $rows = $this->serializeTeamReferrals($referred, $commissionRows, $referrerCode, $invitation);
            if ($rows !== [])
            {
                return $rows;
            }
        }

        $summary = $this->summarizeCommissionRows($commissionRows);
        $contracted = $referred !== null || $commissionRows !== [];

        return [[
            'id' => 'invitation-'.$invitation->id,
            'name' => $invitation->invitee_name,
            'email' => $invitation->invitee_email,
            'plan_name' => $invitation->plan_name,
            'sent_at' => $invitation->sent_at?->toIso8601String(),
            'opened_at' => $invitation->opened_at?->toIso8601String(),
            'clicked_at' => $invitation->clicked_at?->toIso8601String(),
            'contracted' => $contracted,
            'contracted_at' => $summary['first_paid_at'] ?? $referred?->created_at?->toIso8601String(),
            'renews_at' => null,
            'commission_cents' => $summary['commission_cents'],
            'commission_percent' => $summary['commission_percent'],
            'currency' => $summary['currency'],
            'status' => $contracted ? 'Contrató' : $invitation->statusLabel(),
        ]];
    }

    /**
     * @param  list<BillingAffiliateCommission>  $commissionRows
     * @return array<string, mixed>
     */
    private function serializeReferralFromTeam(Team $referred, array $commissionRows, ?string $referrerCode = null): array
    {
        $rows = $this->serializeTeamReferrals($referred, $commissionRows, $referrerCode);

        return $rows[0] ?? $this->serializeTeamFallback($referred, $commissionRows);
    }

    /**
     * @param  list<BillingAffiliateCommission>  $commissionRows
     * @return list<array<string, mixed>>
     */
    private function serializeTeamReferrals(Team $referred, array $commissionRows, ?string $referrerCode = null, ?AffiliateInvitation $invitation = null): array
    {
        $subscriptions = $this->referredSubscriptions($referred, $referrerCode);
        if ($subscriptions->isEmpty())
        {
            return $invitation === null ? [$this->serializeTeamFallback($referred, $commissionRows)] : [];
        }

        $summary = $this->summarizeCommissionRows($commissionRows);
        $billing = $this->subscriptionBillingByStripeId($subscriptions);
        $rows = [];

        foreach ($subscriptions as $subscription)
        {
            $stripeId = (string) $subscription->stripe_id;
            $rowBilling = $billing[$stripeId] ?? ['renews_at' => null, 'amount_cents' => 0, 'currency' => null];
            $percent = $this->commissionPercentForSubscription($subscription, $summary['commission_percent']);
            $currency = $rowBilling['currency'] ?? $summary['currency'];
            $commissionCents = $this->expectedCommissionCents((int) $rowBilling['amount_cents'], $percent);

            $rows[] = [
                'id' => 'subscription-'.$subscription->id,
                'name' => $invitation?->invitee_name ?? $referred->name,
                'email' => $invitation?->invitee_email ?? $referred->owner?->email,
                'plan_name' => $this->serviceNameForType((string) $subscription->type),
                'sent_at' => $invitation?->sent_at?->toIso8601String(),
                'opened_at' => $invitation?->opened_at?->toIso8601String(),
                'clicked_at' => $invitation?->clicked_at?->toIso8601String(),
                'contracted' => true,
                'contracted_at' => $summary['first_paid_at'] ?? $referred->created_at?->toIso8601String(),
                'renews_at' => $rowBilling['renews_at'] ?? null,
                'commission_cents' => $commissionCents,
                'commission_percent' => $percent,
                'currency' => $commissionCents > 0 ? ($currency ?? 'EUR') : $currency,
                'status' => 'Contrató',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<BillingAffiliateCommission>  $commissionRows
     * @return array<string, mixed>
     */
    private function serializeTeamFallback(Team $referred, array $commissionRows): array
    {
        $summary = $this->summarizeCommissionRows($commissionRows);

        return [
            'id' => 'team-'.$referred->id,
            'name' => $referred->name,
            'email' => $referred->owner?->email,
            'plan_name' => null,
            'sent_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
            'contracted' => true,
            'contracted_at' => $summary['first_paid_at'] ?? $referred->created_at?->toIso8601String(),
            'renews_at' => null,
            'commission_cents' => $summary['commission_cents'],
            'commission_percent' => $summary['commission_percent'],
            'currency' => $summary['currency'],
            'status' => 'Contrató',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReferralFromCommission(BillingAffiliateCommission $row): array
    {
        return [
            'id' => 'commission-'.$row->id,
            'name' => $row->payingTeam?->name ?? __('Equipo referido'),
            'email' => $row->payingTeam?->owner?->email,
            'plan_name' => null,
            'sent_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
            'contracted' => true,
            'contracted_at' => $row->created_at?->toIso8601String(),
            'renews_at' => null,
            'commission_cents' => (int) $row->commission_amount_cents,
            'commission_percent' => (float) $row->commission_percent,
            'currency' => strtoupper((string) $row->currency),
            'status' => 'Contrató',
        ];
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function referredSubscriptions(Team $referred, ?string $referrerCode): Collection
    {
        $referred->loadMissing('subscriptions');

        return $referred->subscriptions
            ->filter(function (Subscription $subscription) use ($referrerCode): bool
            {
                $subscriptionReferrer = trim((string) ($subscription->referred_by ?? ''));

                return $referrerCode !== null
                    && $subscriptionReferrer !== ''
                    && strcasecmp($subscriptionReferrer, $referrerCode) === 0;
            })
            ->values();
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return array<string, array{renews_at: string|null, amount_cents: int, currency: string|null}>
     */
    private function subscriptionBillingByStripeId(Collection $subscriptions): array
    {
        $ids = $subscriptions->pluck('stripe_id')->filter()->unique()->values()->all();
        $syncs = collect();

        if ($ids !== [])
        {
            $syncs = ServiceSync::query()
                ->whereIn('stripe_id', $ids)
                ->get(['stripe_id', 'current_period_end', 'amount_total', 'unit_amount', 'price_currency']);
        }

        $byStripeId = $syncs->keyBy(fn (ServiceSync $sync): string => (string) $sync->stripe_id);
        $billing = [];

        foreach ($subscriptions as $subscription)
        {
            $stripeId = (string) $subscription->stripe_id;
            $sync = $byStripeId->get($stripeId);
            $amountCents = $sync instanceof ServiceSync
                ? $this->amountCentsFromSync($sync)
                : 0;

            if ($amountCents <= 0)
            {
                $amountCents = $this->planAmountCents((string) $subscription->type);
            }

            $currency = $sync instanceof ServiceSync
                ? strtoupper(trim((string) ($sync->price_currency ?? '')))
                : '';

            $billing[$stripeId] = [
                'renews_at' => $sync?->current_period_end?->toIso8601String() ?? $this->renewalDateFromSubscription($subscription),
                'amount_cents' => $amountCents,
                'currency' => $currency !== '' ? $currency : null,
            ];
        }

        return $billing;
    }

    private function amountCentsFromSync(ServiceSync $sync): int
    {
        $total = (float) $sync->amount_total;
        if ($total > 0)
        {
            return (int) round($total * 100);
        }

        $unit = (float) $sync->unit_amount;
        if ($unit >= 100)
        {
            return (int) round($unit);
        }

        if ($unit > 0)
        {
            return (int) round($unit * 100);
        }

        return 0;
    }

    private function planAmountCents(string $type): int
    {
        foreach (config('humano_pricing.plans', []) as $plan)
        {
            if (! is_array($plan) || (string) ($plan['id'] ?? '') !== $type)
            {
                continue;
            }

            $amount = $plan['monthly_amount'] ?? '';
            if ($amount === '' || ! is_numeric($amount))
            {
                return 0;
            }

            return (int) round(((float) $amount) * 100);
        }

        return 0;
    }

    private function commissionPercentForSubscription(Subscription $subscription, float $fallback): float
    {
        $stored = $subscription->affiliate_commission_percent;
        if ($stored !== null && (float) $stored > 0)
        {
            return max(0.0, min(100.0, (float) $stored));
        }

        return max(0.0, min(100.0, $fallback));
    }

    private function expectedCommissionCents(int $amountCents, float $percent): int
    {
        if ($amountCents <= 0 || $percent <= 0)
        {
            return 0;
        }

        return (int) round($amountCents * ($percent / 100.0));
    }

    private function renewalDateFromSubscription(Subscription $subscription): ?string
    {
        $data = is_array($subscription->data) ? $subscription->data : [];
        $raw = $data['current_period_end'] ?? $data['renews_at'] ?? null;

        if (is_numeric($raw))
        {
            return Carbon::createFromTimestamp((int) $raw)->toIso8601String();
        }

        if (is_string($raw) && trim($raw) !== '')
        {
            try
            {
                return Carbon::parse($raw)->toIso8601String();
            } catch (\Throwable)
            {
            }
        }

        return $subscription->ends_at?->toIso8601String();
    }

    /**
     * @param  list<array<string, mixed>>  $referrals
     * @return list<array<string, mixed>>
     */
    private function sortReferralsByRenewal(array $referrals): array
    {
        usort($referrals, function (array $left, array $right): int
        {
            $leftDate = $left['renews_at'] ?? null;
            $rightDate = $right['renews_at'] ?? null;

            if ($leftDate === $rightDate)
            {
                return 0;
            }

            if ($leftDate === null)
            {
                return 1;
            }

            if ($rightDate === null)
            {
                return -1;
            }

            return strcmp((string) $leftDate, (string) $rightDate);
        });

        return $referrals;
    }

    private function serviceNameForType(string $type): string
    {
        $type = trim($type);
        if ($type === '')
        {
            return '';
        }

        $marketing = $this->linkBuilder->planMarketing($type);
        $marketingName = is_array($marketing) ? trim((string) ($marketing['name'] ?? '')) : '';
        if ($marketingName !== '')
        {
            return $marketingName;
        }

        $key = "humano_pricing.plans.{$type}.name";
        $translated = __($key);
        if (is_string($translated) && $translated !== $key)
        {
            return $translated;
        }

        return match (strtolower($type))
        {
            'assistant' => 'Assistant',
            'hosting' => 'Hosting',
            'hunter' => 'Hunter',
            'business' => 'Business',
            'mailer' => 'Mailer',
            'ads' => 'Ads',
            'projects' => 'Projects',
            default => Str::title(str_replace('_', ' ', $type)),
        };
    }

    /**
     * @param  list<BillingAffiliateCommission>  $rows
     * @return array{commission_cents: int, commission_percent: float, currency: string|null, first_paid_at: string|null}
     */
    private function summarizeCommissionRows(array $rows): array
    {
        $commissionCents = 0;
        $percent = AffiliateCommission::percent();
        $currency = null;
        $firstPaidAt = null;

        foreach ($rows as $row)
        {
            $commissionCents += (int) $row->commission_amount_cents;
            $percent = (float) $row->commission_percent;
            $currency = strtoupper((string) $row->currency);
            $paidAt = $row->created_at?->toIso8601String();
            if ($paidAt !== null && ($firstPaidAt === null || $paidAt < $firstPaidAt))
            {
                $firstPaidAt = $paidAt;
            }
        }

        return [
            'commission_cents' => $commissionCents,
            'commission_percent' => $percent,
            'currency' => $currency,
            'first_paid_at' => $firstPaidAt,
        ];
    }

    private function normalizedEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * @param  Collection<int, BillingAffiliateCommission>  $rows
     * @return array<string, array{paid_cents: int, commission_cents: int}>
     */
    private function sumCommissionsByCurrency(Collection $rows): array
    {
        $totals = [];

        foreach ($rows as $row)
        {
            $currency = strtoupper((string) $row->currency);
            if (! isset($totals[$currency]))
            {
                $totals[$currency] = ['paid_cents' => 0, 'commission_cents' => 0];
            }
            $totals[$currency]['paid_cents'] += (int) $row->amount_paid_cents;
            $totals[$currency]['commission_cents'] += (int) $row->commission_amount_cents;
        }

        return $totals;
    }

    /**
     * @param  list<array<string, mixed>>  $referrals
     * @return array<string, array{paid_cents: int, commission_cents: int}>
     */
    private function sumReferralCommissions(array $referrals): array
    {
        $totals = [];

        foreach ($referrals as $referral)
        {
            $commissionCents = (int) ($referral['commission_cents'] ?? 0);
            $currency = strtoupper(trim((string) ($referral['currency'] ?? '')));
            if ($commissionCents <= 0 || $currency === '')
            {
                continue;
            }

            if (! isset($totals[$currency]))
            {
                $totals[$currency] = ['paid_cents' => 0, 'commission_cents' => 0];
            }

            $totals[$currency]['commission_cents'] += $commissionCents;
        }

        return $totals;
    }
}
