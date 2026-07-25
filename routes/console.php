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

Schedule::command('stripe:sync-service-syncs')
    ->hourly()
    ->name('service-syncs-stripe-sync')
    ->description('Sync Stripe subscriptions into service_syncs staging')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('service-syncs:import --provider=stripe --limit=120 --fallback-email --link-code-on-email-match')
    ->cron('3,18,33,48 * * * *')
    ->name('service-syncs-import')
    ->description('Import pending service_syncs rows into services (create-only)')
    ->withoutOverlapping()
    ->runInBackground();

// Stripe invoice_syncs: webhook is primary; schedule is a twice-daily safety net.
Schedule::command('stripe:sync-invoices --mode=auto --limit=500 --recent-days=90')
    ->twiceDaily(6, 18)
    ->name('stripe-invoices-sync-auto')
    ->description('Auto Stripe invoices sync: backfill first, then mutable refresh')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('invoice-syncs:import-stripe --reconcile --limit=2000 --fallback-email --link-code-on-email-match')
    ->cron('15 6,18 * * *')
    ->name('stripe-invoice-syncs-import')
    ->description('Import/reconcile Stripe invoice_syncs into core invoices (status and balance)')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('stripe:sync-payments --limit=40 --recent-days=90')
    ->cron('8,23,38,53 * * * *')
    ->name('stripe-payments-sync')
    ->description('Sync Stripe charges into payment_syncs (staging)')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('payment-syncs:import-stripe --limit=120 --fallback-email --link-code-on-email-match')
    ->cron('12,27,42,57 * * * *')
    ->name('stripe-payment-syncs-import')
    ->description('Import pending payment_syncs rows into core payments')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('mercadopago:sync-payments --limit=40 --recent-days=90')
    ->cron('10,25,40,55 * * * *')
    ->name('mercadopago-payments-sync')
    ->description('Sync Mercado Pago payments into payment_syncs (staging)')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('mercadopago:enrich-settlement-payers --recent-days=90 --poll=120')
    ->cron('12,42 * * * *')
    ->name('mercadopago-settlement-payer-enrich')
    ->description('Enrich Mercado Pago payment_syncs with settlement report payer name/id')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('payment-syncs:import-mercadopago --limit=120 --fallback-email --link-code-on-email-match')
    ->cron('14,29,44,59 * * * *')
    ->name('mercadopago-payment-syncs-import')
    ->description('Import pending Mercado Pago payment_syncs into core payments')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('invoices:reconcile-stripe-collected-payments --limit=80')
    ->cron('20,50 * * * *')
    ->name('stripe-collected-invoice-payments-reconcile')
    ->description('Create missing payments for collected Stripe invoices without linked payments')
    ->withoutOverlapping()
    ->runInBackground();

// Fiscal export (Cuéntica → local): sweep + import, staggered from Stripe.
Schedule::command('cuentica:sync-invoices --mode=auto --limit=80')
    ->everyTenMinutes()
    ->name('cuentica-invoices-sync-auto')
    ->description('Pull Cuéntica sale/purchase documents into invoice_syncs')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('invoice-syncs:import-cuentica --reconcile --limit=120 --fallback-tax-id --fallback-email --link-code-on-match')
    ->cron('7,17,27,37,47,57 * * * *')
    ->name('cuentica-invoice-syncs-import')
    ->description('Import/reconcile Cuéntica invoice_syncs into core invoices')
    ->withoutOverlapping()
    ->runInBackground();

// Fiscal export (local → Cuéntica): sweep eligible local invoices and queue exports.
Schedule::command('fiscal:export-invoices --limit=80')
    ->everyFifteenMinutes()
    ->name('fiscal-export-invoices-sweep')
    ->description('Queue eligible local invoices for fiscal platform export')
    ->withoutOverlapping()
    ->runInBackground();

// Retry invoices whose last fiscal export failed (transient/data issues resolved later).
Schedule::command('fiscal:export-invoices --retry-failed --limit=40')
    ->hourly()
    ->name('fiscal-export-invoices-retry')
    ->description('Retry failed fiscal platform exports')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('ovh:sync')->daily();

Schedule::command('notifications:send-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Paused: daily Team Configuration Report
// Schedule::command('team:test-configurations --failures-only')
//     ->dailyAt('08:00')
//     ->name('team-config-monitoring')
//     ->description('Monitor team configurations and send individual failure reports to owners')
//     ->onFailure(function ()
//     {
//         Log::error('Team configuration monitoring command failed');
//     })
//     ->runInBackground();

// Paused: weekly Team Configuration admin summary
// Schedule::command('team:test-configurations --admin-summary')
//     ->weeklyOn(1, '09:00')
//     ->name('team-config-weekly-report')
//     ->description('Weekly team configuration report with admin summary')
//     ->runInBackground();

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

Schedule::job(new \App\Jobs\SyncPaidAdMetricsJob)
    ->hourly()
    ->name('paid-ads-sync-metrics')
    ->description('Pull daily paid ad metrics from connected ad platforms')
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\RefreshAdPlatformTokenJob)
    ->hourly()
    ->name('paid-ads-refresh-tokens')
    ->description('Refresh ad platform OAuth tokens before they expire')
    ->withoutOverlapping();

Schedule::command('exchange-rates:fetch-daily')
    ->dailyAt('23:00')
    ->timezone('America/Argentina/Buenos_Aires')
    ->name('fetch-daily-exchange-rates')
    ->description('Fetch daily USD/ARS (BCRA) and USD/EUR (Frankfurter) at end of AR day (after madrugada invoices, when BCRA quote is published)')
    ->onFailure(function ()
    {
        Log::error('Daily exchange rates fetch command failed');
    })
    ->runInBackground();

// Legacy CurrencyFreaks fetch (optional; requires CURRENCYFREAKS_API_KEY).
// Schedule::command('exchange-rates:fetch')
//     ->dailyAt('06:00')
//     ...

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

Schedule::command('webdav:sync-data')
    ->everyFifteenMinutes()
    ->name('webdav-sync-data')
    ->description('Queue WebDAV contacts, calendar and task sync jobs')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('performance-insights:generate')
    ->dailyAt('06:15')
    ->name('performance-insights-generate')
    ->description('Persist daily performance insight rows for admin/root users (idempotent without --force)')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('sentiment:compute-daily')
    ->dailyAt('06:20')
    ->name('sentiment-compute-daily')
    ->description('Analyze full inbound chat and email context from the last 24 hours per active contact')
    ->withoutOverlapping(120)
    ->runInBackground();
