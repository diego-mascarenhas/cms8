<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Stripe\Customer;
use Stripe\Stripe;

class SyncStripeCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:sync-customers
                            {--create : Create teams for customers that don\'t exist locally}
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Stripe customers with local teams';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Stripe::setApiKey(config('cashier.secret'));

        $this->info('🚀 Starting Stripe customer synchronization...');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $shouldCreate = $this->option('create');

        if ($isDryRun)
        {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try
        {
            // Get all Stripe customers
            $this->info('📡 Fetching customers from Stripe...');
            $customers = Customer::all(['limit' => 100]);

            $stats = [
                'total' => 0,
                'updated' => 0,
                'created' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];

            foreach ($customers->autoPagingIterator() as $customer)
            {
                $stats['total']++;

                $this->info("Processing customer: {$customer->id} ({$customer->email})");

                // Try to find the team by owner email
                $team = $this->findTeamByEmail($customer->email);

                if ($team)
                {
                    // Team exists - update stripe_id if needed
                    if ($team->stripe_id && $team->stripe_id !== $customer->id)
                    {
                        $this->warn("  ⚠️  Team already has different stripe_id: {$team->stripe_id}");
                        $stats['skipped']++;

                        continue;
                    }

                    if (! $team->stripe_id)
                    {
                        if (! $isDryRun)
                        {
                            $team->stripe_id = $customer->id;
                            $team->save();
                        }

                        $this->line("  ✅ Updated team '{$team->name}' with stripe_id: {$customer->id}");
                        $stats['updated']++;
                    } else
                    {
                        $this->line('  ℹ️  Team already synced');
                        $stats['skipped']++;
                    }
                } else
                {
                    // Team doesn't exist
                    if ($shouldCreate)
                    {
                        if (! $isDryRun)
                        {
                            $team = $this->createTeamFromCustomer($customer);
                        }

                        if ($team || $isDryRun)
                        {
                            $this->line("  ✅ Would create team for: {$customer->email}");
                            $stats['created']++;
                        } else
                        {
                            $this->error("  ❌ Failed to create team for: {$customer->email}");
                            $stats['errors']++;
                        }
                    } else
                    {
                        $this->warn("  ⚠️  No team found for email: {$customer->email}");
                        $this->line('     Use --create flag to create teams automatically');
                        $stats['skipped']++;
                    }
                }

                $this->newLine();
            }

            // Show summary
            $this->newLine();
            $this->info('📊 Synchronization Summary:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Customers', $stats['total']],
                    ['Updated', $stats['updated']],
                    ['Created', $stats['created']],
                    ['Skipped', $stats['skipped']],
                    ['Errors', $stats['errors']],
                ],
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
     * Find a team by owner email
     */
    protected function findTeamByEmail(string $email): ?Team
    {
        if (! $email)
        {
            return null;
        }

        // Find user by email
        $user = User::where('email', $email)->first();

        if (! $user)
        {
            return null;
        }

        // Get user's first team (or personal team)
        return $user->allTeams()->first();
    }

    /**
     * Create a team from Stripe customer
     */
    protected function createTeamFromCustomer(Customer $customer): ?Team
    {
        if (! $customer->email)
        {
            return null;
        }

        try
        {
            // Find or create user
            $user = User::firstOrCreate(
                ['email' => $customer->email],
                [
                    'name' => $customer->name ?? explode('@', $customer->email)[0],
                    'phone' => $customer->phone,
                    'password' => bcrypt('Simplicity!'),
                    'email_verified_at' => now(),
                ],
            );

            // Create team if user doesn't have one
            if ($user->allTeams()->count() === 0)
            {
                $team = Team::create([
                    'user_id' => $user->id,
                    'name' => $customer->name ?? "{$user->name}'s Team",
                    'personal_team' => false,
                    'stripe_id' => $customer->id,
                ]);

                // Attach user to team
                $user->teams()->attach($team, ['role' => 'admin']);

                // Set as current team
                $user->current_team_id = $team->id;
                $user->save();

                return $team;
            }

            return $user->allTeams()->first();
        } catch (\Exception $e)
        {
            $this->error("Error creating team: {$e->getMessage()}");

            return null;
        }
    }
}
