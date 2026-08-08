<?php

namespace App\Services;

use App\Mail\AffiliatePurchaseInvitationMail;
use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Models\Team;
use App\Models\User;
use App\Support\AffiliateCommission;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
     *     plans: list<array<string, mixed>>,
     *     invitations: list<array<string, mixed>>,
     *     commissions_as_referrer: list<array<string, mixed>>,
     *     commissions_as_payer: list<array<string, mixed>>,
     *     totals_as_referrer: array<string, array{paid_cents: int, commission_cents: int}>,
     *     totals_as_payer: array<string, array{paid_cents: int, commission_cents: int}>,
     * }
     */
    public function dashboard(Team $team): array
    {
        if (! $team->canUseAffiliateProgram())
        {
            return [
                'eligible' => false,
                'reason' => __('Los equipos referidos no pueden usar el programa de afiliados.'),
                'referral_code' => null,
                'commission_percent' => AffiliateCommission::percent(),
                'plans' => [],
                'invitations' => [],
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
            ->with(['payingTeam'])
            ->latest()
            ->limit(200)
            ->get();

        $commissionsAsPayer = $team->billingAffiliateCommissionsAsPayer()
            ->with(['referrerTeam'])
            ->latest()
            ->limit(200)
            ->get();

        $plans = [];
        foreach ($this->linkBuilder->availablePlans() as $plan)
        {
            $plans[] = array_merge($plan, [
                'referral_url' => $referralCode !== null
                    ? $this->linkBuilder->buildCaptureRedirectLink($plan['checkout_url'], $referralCode)
                    : null,
            ]);
        }

        $invitations = $team->affiliateInvitations()
            ->with('invitedBy')
            ->latest()
            ->limit(100)
            ->get();

        return [
            'eligible' => true,
            'reason' => null,
            'referral_code' => $referralCode,
            'commission_percent' => AffiliateCommission::percent(),
            'plans' => $plans,
            'invitations' => $invitations->map(fn (AffiliateInvitation $invitation): array => $this->serializeInvitation($invitation))->values()->all(),
            'commissions_as_referrer' => $commissionsAsReferrer->map(fn (BillingAffiliateCommission $row): array => $this->serializeCommission($row, 'paying'))->values()->all(),
            'commissions_as_payer' => $commissionsAsPayer->map(fn (BillingAffiliateCommission $row): array => $this->serializeCommission($row, 'referrer'))->values()->all(),
            'totals_as_referrer' => $this->sumCommissionsByCurrency($commissionsAsReferrer),
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
     * @param  array{invite_name: string, invite_email: string, invite_plan: string}  $data
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
        $plan = collect($this->linkBuilder->availablePlans())->firstWhere('id', $planId);

        if ($plan === null)
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
}
