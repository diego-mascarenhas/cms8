<?php

namespace App\Services;

use App\Models\Team;
use Stripe\Customer;
use Stripe\Stripe;

class TeamStripeCustomerService
{
    /**
     * Setting key prefix for per-category Stripe customer IDs (e.g. stripe_id_mentoring).
     */
    public const SETTING_PREFIX = 'stripe_id_';

    /**
     * Get or create the Stripe customer ID for the team in the Stripe account used by the given category.
     * When the category uses the default Cashier account, returns team->stripe_id (and ensures it exists).
     * When the category has its own account, uses team setting stripe_id_{category} or creates a new customer.
     */
    public function getOrCreateStripeCustomerIdForCategory(Team $team, string $category): ?string
    {
        $category = StripeAccountResolver::normalizeCategory($category);
        $hasDedicatedAccount = ! empty(config("stripe_accounts.{$category}.secret"));

        if (! $hasDedicatedAccount)
        {
            if (! $team->stripe_id)
            {
                $team->createAsStripeCustomer([
                    'email' => $team->owner?->email ?? auth()->user()?->email,
                ]);
            }

            return $team->stripe_id;
        }

        $settingKey = self::SETTING_PREFIX.$category;
        $customerId = $team->getSetting($settingKey);

        if ($customerId)
        {
            return $customerId;
        }

        $email = $team->owner?->email ?? auth()->user()?->email;
        if (! $email)
        {
            return null;
        }

        Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));
        $customer = Customer::create([
            'email' => $email,
            'metadata' => [
                'team_id' => (string) $team->id,
            ],
        ]);
        $team->setSetting($settingKey, $customer->id);

        return $customer->id;
    }

    /**
     * Return the Stripe customer ID for the team and category, without creating one.
     * For default account returns team->stripe_id; for dedicated account returns team setting.
     */
    public function getStripeCustomerIdForCategory(Team $team, string $category): ?string
    {
        $category = StripeAccountResolver::normalizeCategory($category);
        $hasDedicatedAccount = ! empty(config("stripe_accounts.{$category}.secret"));

        if (! $hasDedicatedAccount)
        {
            return $team->stripe_id;
        }

        return $team->getSetting(self::SETTING_PREFIX.$category);
    }

    /**
     * Persist a known Stripe customer ID for the team/category without creating a new customer.
     * Useful when checkout succeeds but local linkage is still missing.
     */
    public function persistStripeCustomerIdForCategory(Team $team, string $category, string $customerId): void
    {
        $category = StripeAccountResolver::normalizeCategory($category);
        $customerId = trim($customerId);
        if ($customerId === '')
        {
            return;
        }

        $hasDedicatedAccount = ! empty(config("stripe_accounts.{$category}.secret"));
        if (! $hasDedicatedAccount)
        {
            if ($team->stripe_id !== $customerId)
            {
                $team->forceFill(['stripe_id' => $customerId])->save();
            }

            return;
        }

        $settingKey = self::SETTING_PREFIX.$category;
        if ($team->getSetting($settingKey) !== $customerId)
        {
            $team->setSetting($settingKey, $customerId, [
                'group' => 'billing',
                'type' => 'string',
                'is_encrypted' => false,
            ]);
        }
    }
}
