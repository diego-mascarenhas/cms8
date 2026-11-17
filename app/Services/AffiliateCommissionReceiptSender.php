<?php

namespace App\Services;

use App\Mail\AffiliateCommissionReceiptMail;
use App\Models\BillingAffiliateCommission;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AffiliateCommissionReceiptSender
{
    public function send(
        BillingAffiliateCommission $commission,
        Team $referrerTeam,
        Team $payingTeam,
        ?Subscription $subscription = null,
    ): void {
        $email = $this->resolveRecipientEmail($referrerTeam);
        if ($email === null)
        {
            Log::info('Affiliate commission receipt skipped: no recipient email', [
                'commission_id' => $commission->id,
                'referrer_team_id' => $referrerTeam->id,
            ]);

            return;
        }

        $serviceLabel = $this->serviceLabel($subscription);
        $receiptNumber = $this->receiptNumber($commission);

        try
        {
            Mail::to($email)->send(new AffiliateCommissionReceiptMail(
                $commission,
                $referrerTeam,
                $payingTeam,
                $serviceLabel,
                $receiptNumber,
            ));
        } catch (\Throwable $e)
        {
            Log::warning('Affiliate commission receipt email failed', [
                'commission_id' => $commission->id,
                'referrer_team_id' => $referrerTeam->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function resolveRecipientEmail(Team $referrerTeam): ?string
    {
        $email = $referrerTeam->getHostingContactEmail();
        if ($email !== null && $email !== '')
        {
            return $email;
        }

        $ownerEmail = trim((string) ($referrerTeam->owner?->email ?? ''));

        return $ownerEmail !== '' && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)
            ? $ownerEmail
            : null;
    }

    private function serviceLabel(?Subscription $subscription): string
    {
        if ($subscription === null)
        {
            return __('Subscription');
        }

        $type = strtolower(trim((string) ($subscription->type ?? '')));
        if ($type === '')
        {
            return __('Subscription');
        }

        $plan = app(HumanoPricingPlanResolver::class)->planForSubscription($subscription);
        $planName = trim((string) ($plan['name'] ?? ''));
        if ($planName !== '')
        {
            return $planName;
        }

        return match ($type)
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

    private function receiptNumber(BillingAffiliateCommission $commission): string
    {
        return 'AFF-'.str_pad((string) $commission->id, 6, '0', STR_PAD_LEFT);
    }
}
