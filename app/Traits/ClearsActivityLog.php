<?php

namespace App\Traits;

use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

trait ClearsActivityLog
{
    /**
     * Clear all activity log entries
     */
    protected function clearAllActivities(): void
    {
        $count = Activity::count();

        if ($count > 0)
        {
            Activity::query()->delete();
            $this->command->info("🧹 Cleared {$count} activity log entries.");
        }
    }

    /**
     * Clear activity log entries from the last X minutes
     */
    protected function clearRecentActivities(int $minutes = 5): void
    {
        $since = Carbon::now()->subMinutes($minutes);

        $query = Activity::where('created_at', '>=', $since);
        $count = $query->count();

        if ($count > 0)
        {
            $query->delete();
            $this->command->info("🧹 Cleared {$count} activity log entries from the last {$minutes} minutes.");
        }
    }

    /**
     * Clear activities for specific models only
     */
    protected function clearActivitiesForModels(array $modelClasses): void
    {
        $count = 0;

        foreach ($modelClasses as $modelClass)
        {
            $deleted = Activity::where('subject_type', $modelClass)->delete();
            $count += $deleted;
        }

        if ($count > 0)
        {
            $models = implode(', ', array_map(function ($class)
            {
                return class_basename($class);
            }, $modelClasses));

            $this->command->info("🧹 Cleared {$count} activity log entries for models: {$models}");
        }
    }

    /**
     * Clear activities caused by specific users (useful when seeding test users)
     */
    protected function clearActivitiesForUsers(array $userIds): void
    {
        $count = Activity::whereIn('causer_id', $userIds)->delete();

        if ($count > 0)
        {
            $users = implode(', ', $userIds);
            $this->command->info("🧹 Cleared {$count} activity log entries for users: {$users}");
        }
    }
}
