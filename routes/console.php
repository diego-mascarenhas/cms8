<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function ()
{
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Laravel 12 bootstrap mode (bootstrap/app.php -> withRouting(commands: ...))
| resolves schedules from this file.
|
*/
Schedule::command('stripe:suspend-overdue')
    ->daily()
    ->at('03:00');

Schedule::command('subscriptions:sync')
    ->hourly()
    ->name('stripe-subscriptions-sync')
    ->description('Sync Stripe subscriptions and stage latest invoices')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('invoice-syncs:import-stripe --limit=1000 --fallback-email --link-code-on-email-match')
    ->hourlyAt(5)
    ->name('stripe-invoice-syncs-import')
    ->description('Import staged Stripe invoices into core invoices')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('ovh:sync')->daily();

Schedule::command('notifications:send-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('team:test-configurations --failures-only')
    ->dailyAt('08:00')
    ->name('team-config-monitoring')
    ->description('Monitor team configurations and send individual failure reports to owners')
    ->onFailure(function ()
    {
        Log::error('Team configuration monitoring command failed');
    })
    ->runInBackground();

Schedule::command('team:test-configurations --admin-summary')
    ->weeklyOn(1, '09:00')
    ->name('team-config-weekly-report')
    ->description('Weekly team configuration report with admin summary')
    ->runInBackground();

Schedule::command('campaigns:process-active')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('process-active-campaigns')
    ->description('Create deliveries for active campaigns');

Schedule::command('campaigns:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('send-scheduled-deliveries')
    ->description('Send scheduled email deliveries (~20/min)');

Schedule::command('wordpress:sync')
    ->hourly()
    ->name('wordpress-sync')
    ->description('Sync WordPress content for assistant context')
    ->runInBackground();

Schedule::command('exchange-rates:fetch')
    ->dailyAt('06:00')
    ->name('fetch-exchange-rates')
    ->description('Fetch daily exchange rates from CurrencyFreaks')
    ->onFailure(function ()
    {
        Log::error('Exchange rates fetch command failed');
    })
    ->runInBackground();

Schedule::command('mailboxes:sync')
    ->everyFiveMinutes()
    ->name('mailboxes-sync')
    ->description('Sync emails from team mailboxes into the database');

Schedule::command('google:sync-data')
    ->everyTenMinutes()
    ->name('google-sync-data')
    ->description('Queue Google contacts and calendar incremental sync jobs')
    ->withoutOverlapping()
    ->runInBackground();
