<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Stripe\Customer;
use Stripe\Stripe;

class SyncStripeCustomerNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:sync-customer-names {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync individual_name from Stripe customers to users table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Stripe::setApiKey(config('cashier.secret'));

        $isDryRun = $this->option('dry-run');

        if ($isDryRun)
        {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('🔄 Syncing Stripe customer names to users...');
        $this->newLine();

        try
        {
            // Get all teams with stripe_id and owner
            $teams = Team::with('owner')
                ->whereNotNull('stripe_id')
                ->get();

            if ($teams->isEmpty())
            {
                $this->warn('⚠️  No teams with Stripe ID found.');

                return Command::SUCCESS;
            }

            $this->info("Found {$teams->count()} teams with Stripe ID");
            $this->newLine();

            $updated = 0;
            $skipped = 0;
            $errors = 0;

            $progressBar = $this->output->createProgressBar($teams->count());
            $progressBar->start();

            foreach ($teams as $team)
            {
                try
                {
                    // Fetch customer from Stripe
                    $customer = Customer::retrieve($team->stripe_id);

                    if (! $customer)
                    {
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    $owner = $team->owner;

                    if (! $owner)
                    {
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    // Use individual_name if exists, otherwise fallback to business name
                    $newName = $customer->individual_name ?? $customer->name;

                    if (! $newName)
                    {
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    // Check if name is different
                    if ($owner->name !== $newName)
                    {
                        if (! $isDryRun)
                        {
                            $owner->update([
                                'name' => $newName,
                            ]);
                        }

                        $this->newLine();
                        $this->line("✅ {$owner->email}:");
                        $this->line("   Old: {$owner->name}");
                        $this->line("   New: {$newName}");
                        $this->line('   Source: '.($customer->individual_name ? 'Contact Name' : 'Business Name'));
                        $updated++;
                    } else
                    {
                        $skipped++;
                    }
                } catch (\Exception $e)
                {
                    $this->newLine();
                    $this->error("❌ Error for team {$team->name}: {$e->getMessage()}");
                    $errors++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Summary
            $this->info('📊 Summary:');
            $this->line("✅ Updated: {$updated}");
            $this->line("⏭️  Skipped: {$skipped}");
            $this->line("❌ Errors: {$errors}");

            if ($isDryRun && $updated > 0)
            {
                $this->newLine();
                $this->warn('💡 Run without --dry-run to apply changes');
            }

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
