<?php

namespace App\Console;

use App\Console\Commands\SendPendingNotifications;
use App\Console\Commands\UpdateHostMetrics;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Log;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        UpdateHostMetrics::class,
        SendPendingNotifications::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ============================================
        // STRIPE & BILLING
        // ============================================
        $schedule->command('stripe:suspend-overdue')
            ->daily()
            ->at('03:00');

        // ============================================
        // OVH SERVICES
        // ============================================
        $schedule->command('ovh:sync')->daily();

        // ============================================
        // NOTIFICATIONS
        // ============================================
        $schedule->command('notifications:send-pending')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // ============================================
        // TEAM CONFIGURATION MONITORING
        // ============================================
        // Daily monitoring - only failures
        $schedule->command('team:test-configurations --failures-only')
            ->dailyAt('08:00')
            ->name('team-config-monitoring')
            ->description('Monitor team configurations and send individual failure reports to owners')
            ->onFailure(function ()
            {
                Log::error('Team configuration monitoring command failed');
            })
            ->runInBackground();

        // Weekly comprehensive report - Mondays at 9:00 AM
        $schedule->command('team:test-configurations --admin-summary')
            ->weeklyOn(1, '09:00') // Monday at 9:00 AM
            ->name('team-config-weekly-report')
            ->description('Weekly team configuration report with admin summary')
            ->runInBackground();

        // ============================================
        // EMAIL CAMPAIGNS (MAILER MODULE)
        // ============================================
        $schedule->command('campaigns:process-active')
            ->everyMinute()
            ->withoutOverlapping()
            ->name('process-active-campaigns')
            ->description('Create deliveries for active campaigns');

        $schedule->command('campaigns:send-scheduled')
            ->everyMinute()
            ->withoutOverlapping()
            ->name('send-scheduled-deliveries')
            ->description('Send scheduled email deliveries');

        // ============================================
        // EXCHANGE RATES
        // ============================================
        $schedule->command('exchange-rates:fetch')
            ->dailyAt('06:00')
            ->name('fetch-exchange-rates')
            ->description('Fetch daily exchange rates from CurrencyFreaks')
            ->onFailure(function ()
            {
                Log::error('Exchange rates fetch command failed');
            })
            ->runInBackground();
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
