<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;

class ClearSeedingActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity:clear-seeding 
                           {--minutes=5 : Clear activities from the last X minutes}
                           {--all : Clear all activities}
                           {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear activity log entries generated during seeding operations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            return $this->clearAllActivities();
        }

        $minutes = $this->option('minutes');
        $this->clearRecentActivities($minutes);
    }

    /**
     * Clear all activities
     */
    protected function clearAllActivities()
    {
        $count = Activity::count();
        
        if ($count === 0) {
            $this->info('No activities found to delete.');
            return;
        }

        if (!$this->option('force') && !$this->confirm("Are you sure you want to delete ALL {$count} activity records?")) {
            $this->info('Operation cancelled.');
            return;
        }

        Activity::query()->delete();
        $this->info("Successfully deleted all {$count} activity records.");
    }

    /**
     * Clear activities from the last X minutes
     */
    protected function clearRecentActivities($minutes)
    {
        $since = Carbon::now()->subMinutes($minutes);
        
        $query = Activity::where('created_at', '>=', $since);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No activities found in the last {$minutes} minutes.");
            return;
        }

        if (!$this->option('force') && !$this->confirm("Delete {$count} activity records from the last {$minutes} minutes?")) {
            $this->info('Operation cancelled.');
            return;
        }

        $query->delete();
        $this->info("Successfully deleted {$count} activity records from the last {$minutes} minutes.");
    }
} 