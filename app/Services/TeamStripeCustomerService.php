<?php

namespace App\Services;

use App\Models\Team;

class TeamStripeCustomerService
{
    /**
     * Legacy setting key prefix. New writes always go to teams.stripe_id.
     */
    public const SETTING_PREFIX = 'stripe_id_';

    /**
     * Get or create the single Stripe customer for the team.
     * Category is ignored: every product uses teams.stripe_id on the Cashier account.
     */
    public function getOrCreateStripeCustomerIdForCategory(Team $team, string $category): ?string
    {
        if ($team->stripe_id)
        {
            return $team->stripe_id;
        }

        $email = $team->owner?->email ?? auth()->user()?->email;
        if (! $email)
        {
            return null;
        }

        $team->createAsStripeCustomer([
            'email' => $email,
            'metadata' => [
                'team_id' => (string) $team->id,
            ],
        ]);

        return $team->stripe_id;
    }

    /**
     * Return the team's Stripe customer ID without creating one.
     */
    public function getStripeCustomerIdForCategory(Team $team, string $category): ?string
    {
        return $team->stripe_id;
    }

    /**
     * Persist a known Stripe customer ID on the team.
     */
    public function persistStripeCustomerIdForCategory(Team $team, string $category, string $customerId): void
    {
        $customerId = trim($customerId);
        if ($customerId === '')
        {
            return;
        }

        if ($team->stripe_id !== $customerId)
        {
            $team->forceFill(['stripe_id' => $customerId])->save();
        }
    }

    /**
     * Drop a stale local customer ID so the next getOrCreate can make a new one.
     */
    public function forgetPersistedCustomerId(Team $team): void
    {
        if ($team->stripe_id === null)
        {
            return;
        }

        $team->forceFill(['stripe_id' => null])->save();
    }
}
