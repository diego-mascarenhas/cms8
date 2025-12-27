<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSubscriptionUserId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:fix-subscription-user-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix subscription user_id to match current team owner';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Fixing subscription user_ids...');
        $this->newLine();

        try
        {
            // Get all subscriptions with mismatched user_id
            $subscriptions = DB::table('subscriptions')
                ->join('teams', 'subscriptions.team_id', '=', 'teams.id')
                ->select(
                    'subscriptions.id as subscription_id',
                    'subscriptions.user_id as old_user_id',
                    'subscriptions.team_id',
                    'teams.user_id as correct_user_id',
                    'teams.name as team_name',
                )
                ->whereColumn('subscriptions.user_id', '!=', 'teams.user_id')
                ->get();

            if ($subscriptions->isEmpty())
            {
                $this->info('✅ No subscriptions need fixing!');

                return Command::SUCCESS;
            }

            $this->info("Found {$subscriptions->count()} subscription(s) with incorrect user_id:");
            $this->newLine();

            $fixed = 0;
            foreach ($subscriptions as $sub)
            {
                $this->line("Team: {$sub->team_name} (ID: {$sub->team_id})");
                $this->line("  Subscription ID: {$sub->subscription_id}");
                $this->line("  Old user_id: {$sub->old_user_id}");
                $this->line("  New user_id: {$sub->correct_user_id}");

                // Update the user_id
                DB::table('subscriptions')
                    ->where('id', $sub->subscription_id)
                    ->update(['user_id' => $sub->correct_user_id]);

                $this->info('  ✅ Fixed!');
                $this->newLine();

                $fixed++;
            }

            $this->info("✅ Fixed {$fixed} subscription(s)!");

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
