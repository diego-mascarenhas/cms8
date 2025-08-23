<?php

namespace App\Console;

use App\Console\Commands\SendPendingNotifications;
use App\Console\Commands\UpdateHostMetrics;
use App\Models\Domain;
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
		// $schedule->command('inspire')
		//	 ->hourly()
		//	 ->appendOutputTo(storage_path('logs/inspire.log'));

		// $schedule->command('inspire')
		//	 ->dailyAt('07:00')
		//	 ->emailOutputTo('diego.mascarenhas@icloud.com');

		// $schedule->command('app:register-application')->dailyAt('23:59');

		// $schedule->command('emails:get')
		//		 ->everyMinute()
		//		 ->withoutOverlapping();

		// $schedule->command('update:host-metrics')
		//	 ->everyFiveMinutes()
		//	 ->when(function ()
		//	 {
		//		 return !empty(env('VCENTER_HOST'));
		//	 });

		// $schedule->command('update:vm-metrics')
		//	 ->twiceDaily(1, 13)
		//	 ->when(function ()
		//	 {
		//		 return !empty(env('VCENTER_HOST'));
		//	 });

		// $schedule->command('update:whm-service-status')
		//	 ->twiceDaily(2, 14)
		//	 ->when(function ()
		//	 {
		//		 return !empty(env('WHM_SERVERS'));
		//	 });

		// $schedule->command('fetch:bruler-data')
		//	 ->hourly()
		//	 ->when(function ()
		//	 {
		//		 return !empty(env('BRULER_API_KEY'));
		//	 });

		// $schedule->command('db:seed', [
		//	 '--class' => 'ImportDataSeeder',
		// ])->dailyAt('07:00')
		//	 ->timezone('Europe/Madrid')
		//	 ->onOneServer()
		//	 ->withoutOverlapping()
		//	 ->runInBackground()
		//	 ->before(function ()
		//	 {
		//		 Log::info('Starting the ImportDataSeeder task.');
		//	 })
		//	 ->after(function ()
		//	 {
		//		 Log::info('Finished the ImportDataSeeder task.');
		//	 });

		// $schedule->job(new \App\Jobs\SendBalanceEmail())->monthlyOn(1, '00:00');

		$schedule->command('stripe:suspend-overdue')
			->daily()
			->at('03:00');

		$schedule->job(new \App\Jobs\WhmServerTest)->everyFiveMinutes();

		$schedule->job(new \App\Jobs\WhmDomainSync)->twiceDaily(6, 18);

		$schedule->job(new \App\Jobs\UpdateDomainInfo)->daily();

		$schedule->job(function ()
		{
			Domain::select('id')->orderBy('id')->chunk(50, function ($domains)
			{
				foreach ($domains as $domain)
				{
					\App\Jobs\UpdateDomainSiteType::dispatch($domain->id);
				}
			});
		})->dailyAt('04:00')->withoutOverlapping();

		$schedule->job(function ()
		{
			Domain::select('id')->orderBy('id')->chunk(50, function ($domains)
			{
				foreach ($domains as $domain)
				{
					\App\Jobs\UpdateDomainPhpVersion::dispatch($domain->id);
				}
			});
		})->dailyAt('04:30')->withoutOverlapping();

		$schedule->command('ovh:sync')->daily();

		// Send pending notifications every 5 minutes
		$schedule->command('notifications:send-pending')
			->everyFiveMinutes()
			->withoutOverlapping()
			->onOneServer()
			->runInBackground();

		// CMS7 Import: Only if CMS_GROUP is set
		if (! empty(env('CMS_GROUP')))
		{
			$schedule->command('import:interactive --auto')
				->dailyAt('02:00')
				->withoutOverlapping()
				->onOneServer()
				->runInBackground()
				->before(function ()
				{
					Log::info('Starting CMS7 import (enterprises & contacts)');
				})
				->after(function ()
				{
					Log::info('Finished CMS7 import (enterprises & contacts)');
				});
		}

		// Team configuration monitoring - daily at 8:00 AM
		// Sends individual reports to team owners only for failures
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
		// Sends individual reports to team owners + admin summary
		$schedule->command('team:test-configurations --admin-summary')
			->weeklyOn(1, '09:00') // Monday at 9:00 AM
			->name('team-config-weekly-report')
			->description('Weekly team configuration report with admin summary')
			->runInBackground();

		// Email Campaign Processing
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
