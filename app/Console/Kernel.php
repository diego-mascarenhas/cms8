<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\UpdateHostMetrics;

use Log;

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
        $schedule->command('inspire')
            ->hourly()
            ->appendOutputTo(storage_path('logs/inspire.log'));

        $schedule->command('inspire')
            ->dailyAt('07:00')
            ->emailOutputTo('diego.mascarenhas@icloud.com');

        $schedule->command('app:register-application')->dailyAt('23:59');

        $schedule->command('update:host-metrics')
            ->everyFiveMinutes()
            ->when(function ()
            {
                return !empty(env('VCENTER_HOST'));
            });

        $schedule->command('update:vm-metrics')
            ->twiceDaily(1, 13)
            ->when(function ()
            {
                return !empty(env('VCENTER_HOST'));
            });

        $schedule->command('update:whm-service-status')
            ->twiceDaily(2, 14)
            ->when(function ()
            {
                return !empty(env('WHM_SERVERS'));
            });

        $schedule->command('fetch:bruler-data')
            ->hourly()
            ->when(function ()
            {
                return !empty(env('BRULER_API_KEY'));
            });

        $schedule->command('db:seed', [
            '--class' => 'ImportDataSeeder',
        ])->dailyAt('12:30')
            ->timezone('Europe/Madrid')
            ->onOneServer()
            ->withoutOverlapping()
            ->runInBackground()
            ->before(function ()
            {
                Log::info('Starting the ImportDataSeeder task.');
            })
            ->after(function ()
            {
                Log::info('Finished the ImportDataSeeder task.');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
