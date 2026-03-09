<?php

namespace App\Traits;

use App\Enums\ProspectPlan;
use Carbon\Carbon;

trait HasProspectLimits
{
    /**
     * Initialize prospect limits for a new team (optional boot).
     */
    public function initializeProspectLimits(): void
    {
        if (! $this->getSetting('prospect_plan'))
        {
            $this->assignProspectPlan(ProspectPlan::FREE, null);
        }
    }

    /**
     * Get remaining prospect credits (monthly remaining + purchased balance).
     */
    public function getRemainingProspectCredits(): int
    {
        if (! $this->getSetting('prospect_plan'))
        {
            $this->assignProspectPlan(ProspectPlan::FREE, null);
        }

        $this->resetProspectMonthlyLimitsIfNeeded();

        $monthlyLimit = (int) $this->getSetting('prospect_monthly_limit', 0);
        $monthlyUsed = (int) $this->getSetting('prospect_monthly_used', 0);
        $purchased = (int) $this->getSetting('prospect_credits_purchased', 0);

        $monthlyRemaining = max(0, $monthlyLimit - $monthlyUsed);

        return $monthlyRemaining + $purchased;
    }

    /**
     * Check if the team can import prospects (has enough credits).
     */
    public function canImportProspects(int $credits = 1): bool
    {
        return $this->getRemainingProspectCredits() >= $credits;
    }

    /**
     * Decrement prospect credits: use monthly allowance first, then purchased balance.
     */
    public function decrementProspectCredits(int $credits): bool
    {
        if (! $this->canImportProspects($credits))
        {
            return false;
        }

        $this->resetProspectMonthlyLimitsIfNeeded();

        $monthlyLimit = (int) $this->getSetting('prospect_monthly_limit', 0);
        $monthlyUsed = (int) $this->getSetting('prospect_monthly_used', 0);
        $purchased = (int) $this->getSetting('prospect_credits_purchased', 0);

        $monthlyRemaining = max(0, $monthlyLimit - $monthlyUsed);

        $fromMonthly = min($credits, $monthlyRemaining);
        $fromPurchased = $credits - $fromMonthly;

        if ($fromMonthly > 0)
        {
            $this->setSetting('prospect_monthly_used', $monthlyUsed + $fromMonthly, ['type' => 'integer', 'group' => 'prospect']);
        }

        if ($fromPurchased > 0)
        {
            $this->setSetting('prospect_credits_purchased', max(0, $purchased - $fromPurchased), ['type' => 'integer', 'group' => 'prospect']);
        }

        return true;
    }

    /**
     * Reset monthly prospect usage if the reset date has passed.
     */
    public function resetProspectMonthlyLimitsIfNeeded(): void
    {
        $resetAt = $this->getSetting('prospect_monthly_reset_at');
        if (! $resetAt || now()->gte(Carbon::parse($resetAt)))
        {
            $this->setSetting('prospect_monthly_used', 0, ['type' => 'integer', 'group' => 'prospect']);
            $this->setSetting('prospect_monthly_reset_at', now()->addMonth()->toISOString(), ['type' => 'string', 'group' => 'prospect']);
        }
    }

    /**
     * Assign a prospect plan to the team.
     */
    public function assignProspectPlan(ProspectPlan $plan, ?int $assignedByUserId = null): void
    {
        $this->setSetting('prospect_plan', $plan->value, ['type' => 'string', 'group' => 'prospect']);
        $this->setSetting('prospect_monthly_limit', $plan->getMonthlyCredits(), ['type' => 'integer', 'group' => 'prospect']);
        $this->setSetting('prospect_monthly_used', 0, ['type' => 'integer', 'group' => 'prospect']);
        $this->setSetting('prospect_monthly_reset_at', now()->addMonth()->toISOString(), ['type' => 'string', 'group' => 'prospect']);

        if ($assignedByUserId !== null)
        {
            $this->setSetting('prospect_plan_assigned_by', $assignedByUserId, ['type' => 'integer', 'group' => 'prospect']);
        }

        $this->setSetting('prospect_plan_assigned_at', now()->toISOString(), ['type' => 'string', 'group' => 'prospect']);
    }

    /**
     * Add credits from a one-time purchase (on-demand).
     */
    public function addProspectCreditsFromPurchase(int $credits): void
    {
        $current = (int) $this->getSetting('prospect_credits_purchased', 0);
        $this->setSetting('prospect_credits_purchased', $current + $credits, ['type' => 'integer', 'group' => 'prospect']);
    }

    /**
     * Get the current prospect plan.
     */
    public function getProspectPlan(): ProspectPlan
    {
        $value = $this->getSetting('prospect_plan', 'free');

        return ProspectPlan::tryFrom($value) ?? ProspectPlan::FREE;
    }
}
