<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\UpdateHostMetrics;
use App\Console\Commands\WhmServerTest;
use App\Console\Commands\WhmDomainSync;
use App\Console\Commands\UpdateDomainInfo;

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
        $schedule->command('inspire')
            ->hourly()
            ->appendOutputTo(storage_path('logs/inspire.log'));

        $schedule->command('inspire')
            ->dailyAt('07:00')
            ->emailOutputTo('diego.mascarenhas@icloud.com');

        $schedule->command('app:register-application')->dailyAt('23:59');

        $schedule->command('emails:get')
                ->everyMinute()
                ->withoutOverlapping();

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

        // $schedule->command('fetch:bruler-data')
        //     ->hourly()
        //     ->when(function ()
        //     {
        //         return !empty(env('BRULER_API_KEY'));
        //     });

        // $schedule->command('db:seed', [
        //     '--class' => 'ImportDataSeeder',
        // ])->dailyAt('07:00')
        //     ->timezone('Europe/Madrid')
        //     ->onOneServer()
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->before(function ()
        //     {
        //         Log::info('Starting the ImportDataSeeder task.');
        //     })
        //     ->after(function ()
        //     {
        //         Log::info('Finished the ImportDataSeeder task.');
        //     });

        // $schedule->job(new \App\Jobs\SendBalanceEmail())->monthlyOn(1, '00:00');

        $schedule->command('stripe:suspend-overdue')
                ->daily()
                ->at('03:00');

        $schedule->job(new WhmServerTest())->everyFiveMinutes();
        
        $schedule->job(new WhmDomainSync)->twiceDaily(6, 18);

        $schedule->job(new UpdateDomainInfo)->daily();
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
