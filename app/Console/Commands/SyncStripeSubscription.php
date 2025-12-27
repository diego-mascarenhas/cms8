<?php

namespace App\Console\Commands;

use App\Enums\EmailPlan;
use App\Models\Team;
use Illuminate\Console\Command;
use Stripe\Stripe;

class SyncStripeSubscription extends Command
{
    protected $signature = 'stripe:sync-subscription {team_id?}';

    protected $description = 'Sync Stripe subscriptions to local database';

    public function handle()
    {
        Stripe::setApiKey(config('cashier.secret'));

        $teamId = $this->argument('team_id');

        if ($teamId)
        {
            $teams = Team::where('id', $teamId)->get();
        } else
        {
            $teams = Team::whereNotNull('stripe_id')->get();
        }

        if ($teams->isEmpty())
        {
            $this->error('No teams found with Stripe ID');

            return 1;
        }

        foreach ($teams as $team)
        {
            $this->info("Processing team: {$team->name} (ID: {$team->id})");

            if (! $team->stripe_id)
            {
                $this->warn("Team {$team->name} has no Stripe ID, skipping...");

                continue;
            }

            try
            {
                // Get subscriptions from Stripe
                $subscriptions = \Stripe\Subscription::all([
                    'customer' => $team->stripe_id,
                    'status' => 'all',
                    'expand' => ['data.items.data.price'],
                ]);

                if (empty($subscriptions->data))
                {
                    $this->warn("No subscriptions found for team {$team->name}");

                    continue;
                }

                foreach ($subscriptions->data as $stripeSubscription)
                {
                    // Check if subscription exists locally
                    $localSubscription = $team->subscriptions()
                        ->where('stripe_id', $stripeSubscription->id)
                        ->first();

                    if ($localSubscription)
                    {
                        // Update existing subscription
                        $localSubscription->update([
                            'stripe_status' => $stripeSubscription->status,
                            'stripe_price' => $stripeSubscription->items->data[0]->price->id,
                            'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
                            'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                        ]);

                        $this->info("Updated subscription: {$stripeSubscription->id}");
                    } else
                    {
                        // Create new subscription record
                        $team->subscriptions()->create([
                            'user_id' => $team->owner->id ?? $team->user_id,
                            'type' => 'default',
                            'stripe_id' => $stripeSubscription->id,
                            'stripe_status' => $stripeSubscription->status,
                            'stripe_price' => $stripeSubscription->items->data[0]->price->id,
                            'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
                            'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                            'ends_at' => null,
                        ]);

                        $this->info("Created subscription: {$stripeSubscription->id}");
                    }

                    // Update team's email plan if subscription is active
                    if ($stripeSubscription->status === 'active')
                    {
                        // Get product ID from price
                        $priceId = $stripeSubscription->items->data[0]->price->id;
                        $productId = is_string($stripeSubscription->items->data[0]->price->product)
                            ? $stripeSubscription->items->data[0]->price->product
                            : $stripeSubscription->items->data[0]->price->product->id;

                        // Map product ID to EmailPlan
                        $plan = $this->getEmailPlanFromProductId($productId);

                        // If not found by product, try by price ID
                        if (! $plan)
                        {
                            $plan = $this->getEmailPlanFromPriceId($priceId);
                        }

                        if ($plan)
                        {
                            $team->assignEmailPlan($plan, null);
                            $this->info("Assigned plan: {$plan->value} to team {$team->name}");
                        } else
                        {
                            $this->warn("Could not determine plan for product: {$productId}, price: {$priceId}");
                        }
                    }
                }
            } catch (\Exception $e)
            {
                $this->error("Error processing team {$team->name}: {$e->getMessage()}");

                continue;
            }
        }

        $this->info('Sync completed!');

        return 0;
    }

    private function getEmailPlanFromProductId($productId)
    {
        foreach (EmailPlan::getAll() as $plan)
        {
            if ($plan->isPaid() && $plan->getStripeProductId() === $productId)
            {
                return $plan;
            }
        }

        return null;
    }

    private function getEmailPlanFromPriceId($priceId)
    {
        foreach (EmailPlan::getAll() as $plan)
        {
            if ($plan->isPaid() && $plan->getStripePriceId() === $priceId)
            {
                return $plan;
            }
        }

        return null;
    }
}
