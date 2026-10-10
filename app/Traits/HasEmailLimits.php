<?php

namespace App\Traits;

use App\Enums\EmailPlan;
use App\Support\MailerPaygPricing;
use Carbon\Carbon;

trait HasEmailLimits
{
    /**
     * Initialize email limits for a new team
     */
    public function initializeEmailLimits()
    {
        if (! $this->getSetting('email_plan'))
        {
            $this->assignEmailPlan(EmailPlan::FREE, null);
        }
    }

    /**
     * Check if the team can send a specific number of emails
     */
    public function canSendEmails(int $count = 1): bool
    {
        $this->resetLimitsIfNeeded();

        if ($this->contacts()->count() > $this->getContactLimit())
        {
            return false;
        }

        if ($this->allowsMailerOverage())
        {
            return true;
        }

        return $this->hasMailerSendQuota($count);
    }

    public function allowsMailerOverage(): bool
    {
        return $this->getEmailPlan()->isPaid();
    }

    public function getMailerOverageEmails(): int
    {
        if (! $this->allowsMailerOverage())
        {
            return 0;
        }

        $used = $this->getActualEmailUsage()['monthly_used'];
        $limit = (int) $this->getSetting('email_monthly_limit', $this->getEmailPlan()->getMonthlyLimit());

        return max(0, $used - $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMailerUsageSummary(): array
    {
        $this->resetLimitsIfNeeded();
        $remaining = $this->getRemainingEmails();
        $overage = $this->getMailerOverageEmails();

        $plan = $this->getEmailPlan();

        return [
            'plan_id' => $plan->value,
            'plan_name' => $plan->getDisplayName(),
            'subscribers_used' => $this->contacts()->count(),
            'subscribers_limit' => $this->getContactLimit(),
            'emails_used' => $remaining['monthly_used'],
            'emails_included' => $remaining['monthly_limit'],
            'daily_used' => $remaining['daily_used'],
            'daily_limit' => $remaining['daily_limit'],
            'overage_emails' => $overage,
            'allows_overage' => $this->allowsMailerOverage(),
            'amount_due_cents' => MailerPaygPricing::overageDueCents($overage, $this),
            'price_per_email' => MailerPaygPricing::pricePerEmail($this),
            'currency' => MailerPaygPricing::currency(),
            'custom_sender_allowed' => $this->allowsCustomMessageSender(),
        ];
    }

    public function hasMailerSendQuota(int $count = 1): bool
    {
        $this->resetLimitsIfNeeded();

        if ($this->hasMonthlyEmailsAvailable($count) && $this->hasDailyEmailsAvailable($count))
        {
            return true;
        }

        return $this->getPurchasedMailerCredits() >= $count
            && $this->hasDailyEmailsAvailable($count);
    }

    public function getPurchasedMailerCredits(): int
    {
        return max(0, (int) $this->getSetting('mailer_credits_purchased', 0));
    }

    public function addMailerCreditsFromPurchase(int $credits): void
    {
        if ($credits < 1)
        {
            return;
        }

        $current = $this->getPurchasedMailerCredits();
        $this->setSetting('mailer_credits_purchased', $current + $credits, ['type' => 'integer', 'group' => 'email']);
        $this->unsetRelation('settings');

        if ($this->getEmailPlan() === EmailPlan::FREE)
        {
            $this->assignEmailPlan(EmailPlan::PAYG, null);
        }
    }

    public function consumePurchasedMailerCredits(int $credits): bool
    {
        if ($credits < 1)
        {
            return true;
        }

        $current = $this->getPurchasedMailerCredits();
        if ($current < $credits)
        {
            return false;
        }

        $this->setSetting('mailer_credits_purchased', $current - $credits, ['type' => 'integer', 'group' => 'email']);
        $this->unsetRelation('settings');

        return true;
    }

    public function getSendableEmailCount(): int
    {
        $this->resetLimitsIfNeeded();

        $remaining = $this->getRemainingEmails();
        $fromPlan = 0;
        if ($this->hasDailyEmailsAvailable(1))
        {
            $fromPlan = $remaining['monthly_remaining'];
        }

        return $fromPlan + $this->getPurchasedMailerCredits();
    }

    /**
     * Record a successfully sent campaign email against monthly quota or prepaid credits.
     */
    public function recordSuccessfulMailerSend(int $count = 1): bool
    {
        return $this->incrementEmailUsage($count);
    }

    /**
     * Check monthly email availability
     */
    public function hasMonthlyEmailsAvailable(int $emailsToSend = 1): bool
    {
        $this->resetLimitsIfNeeded();
        $monthlyUsed = (int) $this->getSetting('email_monthly_used', 0);
        $monthlyLimit = (int) $this->getSetting('email_monthly_limit', 10000);

        $remaining = $monthlyLimit - $monthlyUsed;

        return $remaining >= $emailsToSend;
    }

    /**
     * Check daily email availability
     */
    public function hasDailyEmailsAvailable(int $emailsToSend = 1): bool
    {
        $this->resetLimitsIfNeeded();
        $dailyLimit = (int) $this->getSetting('email_daily_limit', 500);

        // SCALE plan has no daily limit (null or very high limit)
        if ($dailyLimit <= 0 || $dailyLimit >= 999999)
        {
            return true;
        }

        $dailyUsed = (int) $this->getSetting('email_daily_used', 0);
        $remaining = $dailyLimit - $dailyUsed;

        return $remaining >= $emailsToSend;
    }

    /**
     * Check contact limit
     */
    public function canAddContacts(int $contactsToAdd = 1): bool
    {
        $currentContacts = $this->contacts()->count();
        $contactLimit = $this->getContactLimit();

        return ($currentContacts + $contactsToAdd) <= $contactLimit;
    }

    /**
     * Increment email usage
     */
    public function incrementEmailUsage(int $count = 1): bool
    {
        if (! $this->canSendEmails($count))
        {
            return false;
        }

        // Increment monthly usage
        $monthlyUsed = (int) $this->getSetting('email_monthly_used', 0);
        $this->setSetting('email_monthly_used', $monthlyUsed + $count, ['type' => 'integer', 'group' => 'email']);

        // Increment daily usage
        $dailyUsed = (int) $this->getSetting('email_daily_used', 0);
        $this->setSetting('email_daily_used', $dailyUsed + $count, ['type' => 'integer', 'group' => 'email']);

        return true;
    }

    /**
     * Get remaining email counts
     */
    public function getRemainingEmails(): array
    {
        $this->resetLimitsIfNeeded();

        $monthlyLimit = (int) $this->getSetting('email_monthly_limit', 10000);
        $dailyLimit = (int) $this->getSetting('email_daily_limit', 500);

        // Get actual usage from database instead of settings
        $actualUsage = $this->getActualEmailUsage();
        $monthlyUsed = $actualUsage['monthly_used'];
        $dailyUsed = $actualUsage['daily_used'];

        $dailyRemaining = ($dailyLimit <= 0 || $dailyLimit >= 999999)
            ? null // No daily limit for SCALE plan
            : max(0, $dailyLimit - $dailyUsed);

        return [
            'monthly_limit' => $monthlyLimit,
            'monthly_used' => $monthlyUsed,
            'monthly_remaining' => max(0, $monthlyLimit - $monthlyUsed),
            'daily_limit' => ($dailyLimit >= 999999) ? null : $dailyLimit,
            'daily_used' => $dailyUsed,
            'daily_remaining' => $dailyRemaining,
        ];
    }

    /**
     * Check if team is over any limits
     */
    public function isOverLimits(): array
    {
        $remaining = $this->getRemainingEmails();
        $contactsCount = $this->contacts()->count();
        $contactLimit = $this->getContactLimit();

        $overMonthly = $remaining['monthly_remaining'] <= 0;
        $overDaily = $remaining['daily_remaining'] !== null && $remaining['daily_remaining'] <= 0;
        $overContacts = $contactsCount > $contactLimit;

        return [
            'over_monthly' => $overMonthly,
            'over_daily' => $overDaily,
            'over_contacts' => $overContacts,
            'can_send' => ! $overMonthly && ! $overDaily && ! $overContacts,
        ];
    }

    /**
     * Reset monthly limits if needed
     */
    public function resetMonthlyLimits(): void
    {
        $this->setSetting('email_monthly_used', 0, ['type' => 'integer', 'group' => 'email']);
        $this->setSetting('email_monthly_reset_at', now()->addMonth()->toISOString(), ['type' => 'string', 'group' => 'email']);
    }

    /**
     * Reset daily limits if needed
     */
    public function resetDailyLimits(): void
    {
        $this->setSetting('email_daily_used', 0, ['type' => 'integer', 'group' => 'email']);
        $this->setSetting('email_daily_reset_date', now()->toDateString(), ['type' => 'string', 'group' => 'email']);
    }

    /**
     * Reset limits if the reset dates have passed
     */
    private function resetLimitsIfNeeded(): void
    {
        $now = now();

        // Reset monthly limits if needed
        $monthlyResetAt = $this->getSetting('email_monthly_reset_at');
        if (! $monthlyResetAt || $now->gte(Carbon::parse($monthlyResetAt)))
        {
            $this->resetMonthlyLimits();
        }

        // Reset daily limits if needed
        $dailyResetDate = $this->getSetting('email_daily_reset_date');
        if (! $dailyResetDate || $now->toDateString() !== $dailyResetDate)
        {
            $this->resetDailyLimits();
        }
    }

    /**
     * Assign an email plan to the team
     */
    public function assignEmailPlan(EmailPlan $plan, ?int $assignedByUserId = null): void
    {
        // Verify admin user if provided
        if ($assignedByUserId)
        {
            $assignedBy = \App\Models\User::find($assignedByUserId);
            if (! $assignedBy || ! $assignedBy->hasRole('admin'))
            {
                throw new \Exception('Only admin users can assign email plans');
            }
        }

        $this->setSetting('email_plan', $plan->value, ['type' => 'string', 'group' => 'email']);
        $this->setSetting('email_monthly_limit', $plan->getMonthlyLimit(), ['type' => 'integer', 'group' => 'email']);
        $this->setSetting('email_daily_limit', $plan->getDailyLimit(), ['type' => 'integer', 'group' => 'email']);
        $this->setSetting('contact_limit', $plan->getContactLimit(), ['type' => 'integer', 'group' => 'email']);

        if ($assignedByUserId)
        {
            $this->setSetting('email_plan_assigned_by', $assignedByUserId, ['type' => 'integer', 'group' => 'email']);
        }

        $this->setSetting('email_plan_assigned_at', now()->toISOString(), ['type' => 'string', 'group' => 'email']);

        // Reset usage counters
        $this->setSetting('email_monthly_used', 0, ['type' => 'integer', 'group' => 'email']);
        $this->setSetting('email_daily_used', 0, ['type' => 'integer', 'group' => 'email']);
        $this->setSetting('email_monthly_reset_at', now()->addMonth()->toISOString(), ['type' => 'string', 'group' => 'email']);
        $this->setSetting('email_daily_reset_date', now()->toDateString(), ['type' => 'string', 'group' => 'email']);
    }

    /**
     * Get the current email plan
     */
    public function getEmailPlan(): EmailPlan
    {
        $planValue = $this->getSetting('email_plan', 'free');

        return EmailPlan::from($planValue);
    }

    /**
     * Teams on the complimentary list skip checkout and see every plan feature.
     */
    public function hasComplimentaryPlanAccess(): bool
    {
        $ids = config('humano_pricing.plan_access_team_ids', []);
        if (! is_array($ids) || $ids === [])
        {
            return false;
        }

        return in_array((int) $this->id, array_map('intval', $ids), true);
    }

    /**
     * Per-message From is a Foundation and Scale feature, and is always on for complimentary teams.
     */
    public function allowsCustomMessageSender(): bool
    {
        if ($this->hasComplimentaryPlanAccess())
        {
            return true;
        }

        return $this->getEmailPlan()->allowsCustomMessageSender();
    }

    /**
     * Scale submits the whole campaign at once. Complimentary teams get that pace too.
     */
    public function sendsMailerWithoutSpacing(): bool
    {
        if ($this->hasComplimentaryPlanAccess())
        {
            return true;
        }

        return $this->getEmailPlan() === EmailPlan::SCALE;
    }

    public function mailerSendSpacingSeconds(): float
    {
        if ($this->sendsMailerWithoutSpacing())
        {
            return max(0, (float) config('services.email.delay.fast_spacing_seconds', 0));
        }

        return $this->getEmailPlan()->sendSpacingSeconds();
    }

    /**
     * Scale sends known recipients immediately. Addresses with no prior delivery use the Foundation gap.
     */
    public function mailerSpacingSecondsForContact(bool $previouslyReached): float
    {
        if ($this->sendsMailerWithoutSpacing() && ! $previouslyReached)
        {
            return EmailPlan::FOUNDATION->sendSpacingSeconds();
        }

        return $this->mailerSendSpacingSeconds();
    }

    public function mailerSendPaceText(): string
    {
        if ($this->sendsMailerWithoutSpacing())
        {
            $seconds = (int) round(EmailPlan::FOUNDATION->sendSpacingSeconds());

            return 'Inmediata para quienes ya recibieron o abrieron un correo. Cada '.$seconds.' segundos para el resto.';
        }

        $seconds = (int) round($this->mailerSendSpacingSeconds());
        if ($seconds < 1)
        {
            return 'Inmediata';
        }

        return 'Cada '.$seconds.' segundos';
    }

    public function mailerSendJitterSeconds(): int
    {
        return 0;
    }

    public function mailerMinHoursOverride(): ?int
    {
        $value = $this->getSetting('mailer_min_hours_between_emails');
        if ($value === null || $value === '')
        {
            return null;
        }

        return max(0, (int) $value);
    }

    public function mailerMinHoursBetweenEmails(): int
    {
        return $this->mailerMinHoursOverride()
            ?? (int) config('mailer.campaigns.default_min_hours_between_emails', 48);
    }

    public function mailerTracksOpens(): bool
    {
        return $this->mailerPreference('mailer_enable_open_tracking', true);
    }

    public function mailerTracksClicks(): bool
    {
        return $this->mailerPreference('mailer_enable_click_tracking', true);
    }

    public function mailerShowsUnsubscribe(): bool
    {
        return $this->mailerPreference('mailer_show_unsubscribe', true);
    }

    public function hasMailerPreference(string $key): bool
    {
        $value = $this->getSetting($key);

        return $value !== null && $value !== '';
    }

    private function mailerPreference(string $key, bool $default): bool
    {
        if (! $this->hasMailerPreference($key))
        {
            return $default;
        }

        return (bool) $this->getSetting($key);
    }

    public function mailerCreateBatch(): int
    {
        if ($this->sendsMailerWithoutSpacing())
        {
            return (int) config('services.email.processing.fast_deliveries_per_campaign_run', 1000);
        }

        return $this->getEmailPlan()->sendCreateBatch();
    }

    public function mailerDispatchBatch(): int
    {
        if ($this->sendsMailerWithoutSpacing())
        {
            return (int) config('services.email.processing.fast_deliveries_per_send_run', 1000);
        }

        return $this->getEmailPlan()->sendDispatchBatch();
    }

    /**
     * Get contact limit
     */
    public function getContactLimit(): int
    {
        return (int) $this->getSetting('contact_limit', 10000);
    }

    /**
     * Get email plan configuration details
     */
    public function getEmailPlanConfig(): array
    {
        $plan = $this->getEmailPlan();
        $remaining = $this->getRemainingEmails();

        $assignedAt = $this->getSetting('email_plan_assigned_at');
        $assignedByUserId = $this->getSetting('email_plan_assigned_by');
        $assignedBy = $assignedByUserId ? \App\Models\User::find($assignedByUserId) : null;

        return array_merge($plan->getConfig(), $remaining, [
            'purchased_credits' => $this->getPurchasedMailerCredits(),
            'sendable_remaining' => $this->getSendableEmailCount(),
            'assigned_at' => $assignedAt ? Carbon::parse($assignedAt) : null,
            'assigned_by' => $assignedBy?->name,
        ]);
    }

    /**
     * Get details about who and when assigned the plan
     */
    public function getPlanDetails(): array
    {
        $assignedByUserId = $this->getSetting('email_plan_assigned_by');
        $assignedBy = $assignedByUserId ? \App\Models\User::find($assignedByUserId) : null;
        $assignedAt = $this->getSetting('email_plan_assigned_at');
        $monthlyResetAt = $this->getSetting('email_monthly_reset_at');
        $dailyResetDate = $this->getSetting('email_daily_reset_date');

        return [
            'plan' => $this->getEmailPlan(),
            'assigned_by' => $assignedBy,
            'assigned_at' => $assignedAt ? Carbon::parse($assignedAt) : null,
            'monthly_reset_at' => $monthlyResetAt ? Carbon::parse($monthlyResetAt) : null,
            'daily_reset_date' => $dailyResetDate,
        ];
    }

    /**
     * Get actual email usage from message_deliveries table (REAL data)
     */
    public function getActualEmailUsage(): array
    {
        $now = now();

        // Monthly usage from actual sent emails (only past sent_at, not scheduled)
        $monthlyUsed = $this->messageDeliveries()
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', $now) // Only count emails already sent
            ->where('sent_at', '>=', $now->copy()->startOfMonth())
            ->count();

        // Daily usage from actual sent emails (only past sent_at, not scheduled)
        $dailyUsed = $this->messageDeliveries()
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', $now) // Only count emails already sent
            ->where('sent_at', '>=', $now->copy()->startOfDay())
            ->count();

        return [
            'monthly_used' => $monthlyUsed,
            'daily_used' => $dailyUsed,
        ];
    }

    /**
     * Sync settings usage with actual database usage
     */
    public function syncEmailUsage(): void
    {
        $actual = $this->getActualEmailUsage();

        $this->setSetting('email_monthly_used', $actual['monthly_used'], ['type' => 'integer', 'group' => 'email']);
        $this->setSetting('email_daily_used', $actual['daily_used'], ['type' => 'integer', 'group' => 'email']);
    }
}
