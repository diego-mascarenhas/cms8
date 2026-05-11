<?php

namespace App\Services;

use App\Models\BillingAffiliateCommission;
use App\Models\Enterprise;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class AffiliateCommissionRecorder
{
    /**
     * When a Stripe invoice is paid, attribute commission to the referrer team using
     * enterprises.referred_by: same-team referrer is stored as the referrer enterprise id (string);
     * legacy / external values may still be the referrer enterprise public code (e.g. customer id).
     * Commission % comes from the referrer team's affiliate_commission_percent setting.
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

        $customerId = (string) ($invoice['customer'] ?? '');
        if ($customerId === '')
        {
            return;
        }

        $payingEnterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $payingTeam->id)
            ->where('type_id', 1)
            ->where('code', $customerId)
            ->first();

        if (! $payingEnterprise)
        {
            return;
        }

        $referralRaw = trim((string) ($payingEnterprise->referred_by ?? ''));
        if ($referralRaw === '' || strcasecmp($referralRaw, $customerId) === 0)
        {
            return;
        }

        $referrerEnterprise = null;
        if (ctype_digit($referralRaw))
        {
            $referrerEnterprise = Enterprise::withoutGlobalScopes()
                ->where('type_id', 1)
                ->where('id', (int) $referralRaw)
                ->first();
        }
        if (! $referrerEnterprise)
        {
            $referrerEnterprise = Enterprise::withoutGlobalScopes()
                ->where('type_id', 1)
                ->where('code', $referralRaw)
                ->orderBy('id')
                ->first();
        }

        if (! $referrerEnterprise)
        {
            return;
        }

        $referrerTeam = Team::query()->find($referrerEnterprise->team_id);
        if (! $referrerTeam || (int) $referrerTeam->id === (int) $payingTeam->id)
        {
            return;
        }

        $percent = $this->resolveCommissionPercent($referrerTeam);
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
                'paying_enterprise_id' => $payingEnterprise->id,
                'referrer_enterprise_id' => $referrerEnterprise->id,
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

    private function resolveCommissionPercent(Team $referrerTeam): float
    {
        $raw = $referrerTeam->getSetting('affiliate_commission_percent', '0');
        if ($raw === '' || $raw === null)
        {
            return 0.0;
        }

        return max(0.0, min(100.0, (float) $raw));
    }
}
