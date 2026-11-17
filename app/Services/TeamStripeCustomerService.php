<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Throwable;

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
     * When teams.stripe_id is empty, recover it from an existing Cashier subscription.
     */
    public function getStripeCustomerIdForCategory(Team $team, string $category): ?string
    {
        $existing = trim((string) ($team->stripe_id ?? ''));
        if ($existing !== '' && str_starts_with($existing, 'cus_'))
        {
            return $existing;
        }

        return $this->recoverStripeCustomerIdFromSubscriptions($team);
    }

    /**
     * Re-attach teams.stripe_id from a Cashier subscription's Stripe customer.
     */
    public function recoverStripeCustomerIdFromSubscriptions(Team $team): ?string
    {
        $subscriptionId = $team->subscriptions()
            ->whereNotNull('stripe_id')
            ->orderByDesc('created_at')
            ->value('stripe_id');

        $subscriptionId = is_string($subscriptionId) ? trim($subscriptionId) : '';
        if ($subscriptionId === '' || ! str_starts_with($subscriptionId, 'sub_'))
        {
            return null;
        }

        try
        {
            $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscriptionId);
            $customerId = $stripeSubscription->customer ?? null;
            if (is_object($customerId))
            {
                $customerId = $customerId->id ?? null;
            }

            $customerId = is_string($customerId) ? trim($customerId) : '';
            if ($customerId === '' || ! str_starts_with($customerId, 'cus_'))
            {
                return null;
            }

            $this->persistStripeCustomerIdForCategory($team, '', $customerId);
            $team->refresh();

            return $team->stripe_id;
        } catch (Throwable $e)
        {
            Log::warning('Could not recover team Stripe customer from subscription', [
                'team_id' => $team->id,
                'subscription_id' => $subscriptionId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
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
