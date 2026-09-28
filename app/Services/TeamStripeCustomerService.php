<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

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

        $ownerTeam = Team::query()
            ->where('stripe_id', $customerId)
            ->where('id', '!=', $team->id)
            ->first();

        if ($ownerTeam)
        {
            Log::warning('Refusing to attach Stripe customer already owned by another team', [
                'team_id' => $team->id,
                'owner_team_id' => $ownerTeam->id,
                'stripe_id' => $customerId,
            ]);

            return;
        }

        if ($team->stripe_id !== $customerId)
        {
            $team->forceFill(['stripe_id' => $customerId])->save();
        }
    }

    /**
     * Stripe customer id for this email, when one already exists.
     */
    public function findCustomerIdByEmail(string $email): ?string
    {
        $email = trim($email);

        if ($email === '')
        {
            return null;
        }

        foreach ($this->stripeCustomersWithEmail($email) as $customer)
        {
            if (! empty($customer->deleted))
            {
                continue;
            }

            if (strcasecmp(trim((string) ($customer->email ?? '')), $email) !== 0)
            {
                continue;
            }

            $customerId = trim((string) ($customer->id ?? ''));

            if ($customerId !== '')
            {
                return $customerId;
            }
        }

        return null;
    }

    /**
     * Create a Stripe customer for the team and store its id.
     */
    public function createStripeCustomer(Team $team, string $email, string $name): string
    {
        $team->createAsStripeCustomer([
            'email' => $email,
            'name' => $name,
            'metadata' => [
                'team_id' => (string) $team->id,
            ],
        ]);

        $customerId = trim((string) $team->stripe_id);

        if ($customerId === '')
        {
            throw new \RuntimeException('Stripe customer was not created.');
        }

        return $customerId;
    }

    /**
     * @return iterable<int, object>
     */
    protected function stripeCustomersWithEmail(string $email): iterable
    {
        return Cashier::stripe()->customers->all([
            'email' => $email,
            'limit' => 10,
        ])->data;
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
