<?php

namespace App\Services;

use App\Models\BillingAffiliateCommission;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class AffiliateCommissionRecorder
{
    /**
     * When a Stripe invoice is paid, attribute commission to the referrer team using
     * {@see Team::$referred_by} (Stripe customer id of the referrer team).
     * Commission % comes from {@see config('humano_pricing.affiliate_commission_percent')}.
     *
     * @param  array<string, mixed>  $invoice  Stripe invoice object from webhook payload
     */
    public function recordFromInvoice(Team $payingTeam, array $invoice): void
    {
        $invoiceId = $invoice['id'] ?? null;
        if (! $invoiceId || BillingAffiliateCommission::query()->where('stripe_invoice_id', $invoiceId)->exists())
        {
            return;
        }

        $referralStripeId = trim((string) ($payingTeam->referred_by ?? ''));
        if ($referralStripeId === '')
        {
            return;
        }

        $payingStripeId = trim((string) ($payingTeam->stripe_id ?? ''));
        if ($payingStripeId !== '' && strcasecmp($referralStripeId, $payingStripeId) === 0)
        {
            return;
        }

        $referrerTeam = Team::findByStripeCustomerId($referralStripeId);
        if (! $referrerTeam || (int) $referrerTeam->id === (int) $payingTeam->id)
        {
            return;
        }

        $percent = $this->resolveCommissionPercent();
        if ($percent <= 0)
        {
            return;
        }

        $amountPaid = (int) ($invoice['amount_paid'] ?? 0);
        if ($amountPaid <= 0)
        {
            return;
        }

        $currency = strtoupper((string) ($invoice['currency'] ?? 'usd'));
        $commissionCents = (int) round($amountPaid * ($percent / 100.0));
        if ($commissionCents <= 0)
        {
            return;
        }

        try
        {
            BillingAffiliateCommission::query()->create([
                'paying_team_id' => $payingTeam->id,
                'referrer_team_id' => $referrerTeam->id,
                'paying_enterprise_id' => null,
                'referrer_enterprise_id' => null,
                'stripe_invoice_id' => $invoiceId,
                'amount_paid_cents' => $amountPaid,
                'currency' => $currency,
                'commission_percent' => $percent,
                'commission_amount_cents' => $commissionCents,
            ]);
        } catch (\Throwable $e)
        {
            Log::warning('Affiliate commission record failed', [
                'paying_team_id' => $payingTeam->id,
                'stripe_invoice_id' => $invoiceId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCommissionPercent(): float
    {
        $raw = config('humano_pricing.affiliate_commission_percent', 0);

        return max(0.0, min(100.0, (float) $raw));
    }
}
