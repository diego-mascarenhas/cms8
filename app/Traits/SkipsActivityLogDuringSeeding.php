<?php

namespace App\Traits;

trait SkipsActivityLogDuringSeeding
{
    /**
     * Determine if activity logging should be skipped
     */
    protected function shouldSkipActivityLog(): bool
    {
        // Skip logging if explicitly disabled via environment variable
        if (config('activitylog.enabled') === false) {
            return true;
        }

        // Skip logging if we're running in console and it's a seeding operation
        if (app()->runningInConsole()) {
            $command = $_SERVER['argv'][1] ?? '';
            
            // Skip for db:seed and db:seed --class commands
            if (str_contains($command, 'db:seed')) {
                return true;
            }
            
            // Skip for migrate:fresh --seed
            if (str_contains($command, 'migrate:fresh') && in_array('--seed', $_SERVER['argv'])) {
                return true;
            }

            // Skip for migrate:refresh --seed
            if (str_contains($command, 'migrate:refresh') && in_array('--seed', $_SERVER['argv'])) {
                return true;
            }
        }

        return false;
    }
} 