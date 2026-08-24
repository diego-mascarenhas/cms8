<?php

namespace App\Services;

use App\Models\BillingAffiliateCommission;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\AffiliateCommission;
use Illuminate\Support\Facades\Log;

class AffiliateCommissionRecorder
{
    /**
     * When a Stripe invoice is paid, commission only the subscription that was referred
     * ({@see Subscription::$referred_by}), not the paying team's full account.
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

        $stripeSubscriptionId = $this->invoiceSubscriptionId($invoice);
        if ($stripeSubscriptionId === null)
        {
            return;
        }

        $subscription = Subscription::query()
            ->where('team_id', $payingTeam->id)
            ->where('stripe_id', $stripeSubscriptionId)
            ->first();

        if ($subscription === null)
        {
            return;
        }

        $referralStripeId = trim((string) ($subscription->referred_by ?? ''));
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

        $percent = $this->resolveCommissionPercent($subscription);
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
                'stripe_subscription_id' => $stripeSubscriptionId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function invoiceSubscriptionId(array $invoice): ?string
    {
        $subscription = $invoice['subscription'] ?? null;
        if (is_string($subscription) && str_starts_with($subscription, 'sub_'))
        {
            return $subscription;
        }

        if (is_array($subscription))
        {
            $id = trim((string) ($subscription['id'] ?? ''));

            return $id !== '' ? $id : null;
        }

        return null;
    }

    private function resolveCommissionPercent(Subscription $subscription): float
    {
        $stored = $subscription->affiliate_commission_percent;
        if ($stored !== null && (float) $stored > 0)
        {
            return max(0.0, min(100.0, (float) $stored));
        }

        return AffiliateCommission::percent();
    }
}
