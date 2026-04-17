<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateSubscriptionsType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:update-type';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all subscriptions from type "default" to "mailer"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating subscriptions type from "default" to "mailer"...');

        // Get count before update
        $count = app('db')->table('subscriptions')
            ->where('type', 'default')
            ->count();

        if ($count === 0)
        {
            $this->info('No subscriptions found with type "default".');

            return Command::SUCCESS;
        }

        $this->info("Found {$count} subscription(s) with type 'default'.");

        // Update subscriptions
        $updated = app('db')->table('subscriptions')
            ->where('type', 'default')
            ->update(['type' => 'mailer']);

        $this->newLine();
        $this->info("✅ Successfully updated {$updated} subscription(s) to type 'mailer'.");

        return Command::SUCCESS;
    }
}
