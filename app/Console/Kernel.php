<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\UpdateHostMetrics;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        UpdateHostMetrics::class,
    ];
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('app:register-application')->dailyAt('23:59');
        $schedule->command('update:host-metrics')->everyFiveMinutes();
        $schedule->command('update:update:vm-metrics')->twiceDaily(1, 13);
        $schedule->command('update:whm-service-status')->twiceDaily(2, 14);

        $schedule->command('fetch:bruler-data')
             ->everySixHours()
             ->when(function () {
                 return !empty(env('BRULER_API_KEY'));
             });
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
