<?php

namespace App\Console\Commands;

use App\Enums\EmailPlan;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Console\Command;
use Stripe\Customer;
use Stripe\Stripe;

class UpdateUsersFromStripe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:update-users
                            {--dry-run : Show what would be updated without making changes}
                            {--sync-subscriptions : Also sync subscriptions from Stripe}
                            {--password=Simplicity! : Default password for users without one}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing users and subscriptions with data from Stripe customers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Stripe::setApiKey(config('cashier.secret'));

        $this->info('🔄 Updating users from Stripe customers...');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $syncSubscriptions = $this->option('sync-subscriptions');
        $defaultPassword = $this->option('password');

        if ($isDryRun)
        {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try
        {
            // Get all teams with stripe_id
            $teams = Team::whereNotNull('stripe_id')->with('owner')->get();

            $stats = [
                'total' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'subscriptions_synced' => 0,
                'subscriptions_created' => 0,
            ];

            foreach ($teams as $team)
            {
                $stats['total']++;

                $this->info("Processing team: {$team->name} ({$team->stripe_id})");

                try
                {
                    $customer = Customer::retrieve($team->stripe_id);
                    $user = $team->owner;

                    if (! $user)
                    {
                        $this->warn('  ⚠️  No owner found for this team');
                        $stats['skipped']++;

                        continue;
                    }

                    $changes = [];

                    // Check phone
                    if ($customer->phone && $user->phone !== $customer->phone)
                    {
                        $changes['phone'] = [
                            'from' => $user->phone ?? 'NULL',
                            'to' => $customer->phone,
                        ];
                    }

                    // Check name
                    if ($customer->name && $user->name !== $customer->name)
                    {
                        $changes['name'] = [
                            'from' => $user->name,
                            'to' => $customer->name,
                        ];
                    }

                    if (empty($changes))
                    {
                        $this->line('  ℹ️  No user changes needed');
                        $stats['skipped']++;
                    } else
                    {
                        if (! $isDryRun)
                        {
                            if (isset($changes['phone']))
                            {
                                $user->phone = $customer->phone;
                            }
                            if (isset($changes['name']))
                            {
                                $user->name = $customer->name;
                            }
                            $user->save();
                        }

                        $this->line('  ✅ User updated:');
                        foreach ($changes as $field => $change)
                        {
                            $this->line("     - {$field}: {$change['from']} → {$change['to']}");
                        }

                        $stats['updated']++;
                    }

                    // Sync subscriptions if flag is enabled
                    if ($syncSubscriptions)
                    {
                        $this->syncSubscriptions($team, $isDryRun, $stats);
                    }
                } catch (\Exception $e)
                {
                    $this->error("  ❌ Error: {$e->getMessage()}");
                    $stats['errors']++;
                }

                $this->newLine();
            }

            // Show summary
            $this->info('📊 Update Summary:');

            $summaryData = [
                ['Total Teams', $stats['total']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
            ];

            if ($syncSubscriptions)
            {
                $summaryData[] = ['Subscriptions Synced', $stats['subscriptions_synced']];
                $summaryData[] = ['Subscriptions Created', $stats['subscriptions_created']];
            }

            $this->table(
                ['Metric', 'Count'],
                $summaryData,
            );

            if ($isDryRun)
            {
                $this->newLine();
                $this->warn('⚠️  This was a DRY RUN - No changes were made');
                $this->info('💡 Run without --dry-run to apply changes');
            }

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Sync subscriptions from Stripe for a team
     */
    protected function syncSubscriptions(Team $team, bool $isDryRun, array &$stats): void
    {
        try
        {
            // Fetch subscriptions from Stripe
            $stripeSubscriptions = \Stripe\Subscription::all([
                'customer' => $team->stripe_id,
                'limit' => 100,
                'expand' => ['data.items.data.price'],
            ]);

            if (empty($stripeSubscriptions->data))
            {
                $this->line('  ℹ️  No subscriptions found in Stripe');

                return;
            }

            foreach ($stripeSubscriptions->data as $stripeSubscription)
            {
                // Check if subscription already exists locally
                $localSubscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

                if ($localSubscription)
                {
                    // Update existing subscription
                    if (! $isDryRun)
                    {
                        $localSubscription->update([
                            'stripe_status' => $stripeSubscription->status,
                            'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                            'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
                            'ends_at' => $stripeSubscription->cancel_at_period_end
                                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                                : null,
                        ]);
                    }

                    $this->line("  🔄 Subscription updated: {$stripeSubscription->id} ({$stripeSubscription->status})");
                    $stats['subscriptions_synced']++;
                } else
                {
                    // Create new subscription
                    if (! $isDryRun)
                    {
                        Subscription::create([
                            'user_id' => $team->user_id,
                            'team_id' => $team->id,
                            'type' => 'default',
                            'stripe_id' => $stripeSubscription->id,
                            'stripe_status' => $stripeSubscription->status,
                            'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                            'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
                            'trial_ends_at' => $stripeSubscription->trial_end
                                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end)
                                : null,
                            'ends_at' => $stripeSubscription->cancel_at_period_end
                                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                                : null,
                        ]);

                        // Update team's email plan based on subscription
                        $this->updateTeamEmailPlan($team, $stripeSubscription);
                    }

                    $this->line("  ✨ Subscription created: {$stripeSubscription->id} ({$stripeSubscription->status})");
                    $stats['subscriptions_created']++;
                }
            }
        } catch (\Exception $e)
        {
            $this->error("  ❌ Error syncing subscriptions: {$e->getMessage()}");
        }
    }

    /**
     * Update team's email plan based on Stripe subscription
     *
     * @param  \Stripe\Subscription  $stripeSubscription
     */
    protected function updateTeamEmailPlan(Team $team, $stripeSubscription): void
    {
        try
        {
            // Get the product ID from the subscription
            $priceId = $stripeSubscription->items->data[0]->price->id ?? null;

            if (! $priceId)
            {
                return;
            }

            // Map price ID to email plan using EmailPlan enum
            $emailPlan = null;
            foreach (EmailPlan::cases() as $plan)
            {
                if ($plan->getStripePriceId() === $priceId)
                {
                    $emailPlan = $plan;
                    break;
                }
            }

            // Default to FREE if no match
            if (! $emailPlan)
            {
                $emailPlan = EmailPlan::FREE;
            }

            // Update team's email plan if active
            if (in_array($stripeSubscription->status, ['active', 'trialing']))
            {
                $team->assignEmailPlan($emailPlan, null);

                $this->line("  📧 Email plan updated: {$emailPlan->value}");
            }
        } catch (\Exception $e)
        {
            $this->error("  ⚠️  Could not update email plan: {$e->getMessage()}");
        }
    }
}
