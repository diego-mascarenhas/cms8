<?php

namespace App\Console;

use App\Console\Commands\SendPendingNotifications;
use App\Console\Commands\UpdateHostMetrics;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        UpdateHostMetrics::class,
        SendPendingNotifications::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        // Scheduler definitions live in routes/console.php (Laravel 12 bootstrap mode).
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
