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

                // Skip customers without email
                if (! $customer->email)
                {
                    $this->warn('  ⚠️  Customer has no email, skipping...');
                    $stats['skipped']++;
                    $this->newLine();

                    continue;
                }

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
    protected function findTeamByEmail(?string $email): ?Team
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
     * Clean phone number to contain only digits
     */
    protected function cleanPhoneNumber(?string $phone): ?int
    {
        if (! $phone)
        {
            return null;
        }

        // Remove all non-numeric characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // Return as integer or null if empty
        return $cleaned ? (int) $cleaned : null;
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
            // Find or create user first
            $user = User::firstOrCreate(
                ['email' => $customer->email],
                [
                    'name' => $customer->name ?? explode('@', $customer->email)[0],
                    'phone' => $this->cleanPhoneNumber($customer->phone),
                    'password' => bcrypt('Simplicity!'),
                    'email_verified_at' => now(),
                ],
            );

            // Assign admin role to the user if they don't have it
            if (! $user->hasRole('admin'))
            {
                $user->assignRole('admin');
                $this->line("  ✅ Assigned admin role to user: {$user->email}");
            }

            // Check if a team already exists with this stripe_id
            $existingTeam = Team::where('stripe_id', $customer->id)->first();

            if ($existingTeam)
            {
                // Team exists, ensure the user is the owner
                if ($existingTeam->user_id !== $user->id)
                {
                    $existingTeam->user_id = $user->id;
                    $existingTeam->save();
                    $this->line("  ✅ Updated team owner to user: {$user->email}");
                }

                // Ensure user is attached to the team
                if (! $user->teams->contains($existingTeam->id))
                {
                    $user->teams()->attach($existingTeam, ['role' => 'admin']);
                    $this->line('  ✅ Attached user to existing team');
                }

                // Set as current team if user doesn't have one
                if (! $user->current_team_id)
                {
                    $user->current_team_id = $existingTeam->id;
                    $user->save();
                }

                $this->line("  ℹ️  Team already exists with stripe_id: {$customer->id}");

                return $existingTeam;
            }

            // Check if user already has a team
            $existingUserTeam = $user->allTeams()->first();

            if ($existingUserTeam)
            {
                // User has a team, update it with stripe_id if it doesn't have one
                if (! $existingUserTeam->stripe_id)
                {
                    $existingUserTeam->stripe_id = $customer->id;
                    $existingUserTeam->save();
                    $this->line('  ✅ Updated existing team with stripe_id');
                }

                return $existingUserTeam;
            }

            // Create new team only if user has no teams
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
        } catch (\Exception $e)
        {
            $this->error("Error creating team: {$e->getMessage()}");

            return null;
        }
    }
}
