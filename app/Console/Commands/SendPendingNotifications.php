<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPendingNotifications extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'notifications:send-pending {--limit=50 : Maximum number of notifications to send} {--dry-run : Only show what would be sent}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Send pending notifications that have not been sent yet';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$limit = $this->option('limit');
		$dryRun = $this->option('dry-run');

		$this->info('Starting to process pending notifications...');

		// Get pending notifications
		$pendingNotifications = Notification::with(['contact', 'user', 'team'])
			->unsent()
			->orderBy('created_at', 'asc')
			->limit($limit)
			->get();

		if ($pendingNotifications->isEmpty())
		{
			$this->info('No pending notifications found.');

			return Command::SUCCESS;
		}

		$this->info("Found {$pendingNotifications->count()} pending notifications.");

		if ($dryRun)
		{
			$this->info('DRY RUN MODE - No notifications will be sent');
			$this->table(
				['ID', 'Contact', 'Email', 'Subject', 'Created'],
				$pendingNotifications->map(function ($notification)
				{
					return [
						$notification->id,
						$notification->contact->name,
						$notification->contact->email,
						\Str::limit($notification->subject, 50),
						$notification->created_at->format('Y-m-d H:i:s'),
					];
				}),
			);

			return Command::SUCCESS;
		}

		$sentCount = 0;
		$errorCount = 0;

		foreach ($pendingNotifications as $notification)
		{
			try
			{
				// Check if contact has email
				if (empty($notification->contact->email))
				{
					$this->warn("Skipping notification ID {$notification->id} - Contact has no email");

					continue;
				}

				// Dispatch the job
				SendNotificationJob::dispatch($notification);
				$sentCount++;

				$this->info("Queued notification ID {$notification->id} for {$notification->contact->name}");

				Log::info('Notification queued for sending', [
					'notification_id' => $notification->id,
					'contact_id' => $notification->contact->id,
					'contact_email' => $notification->contact->email,
					'subject' => $notification->subject,
				]);
			} catch (\Exception $e)
			{
				$errorCount++;
				$this->error("Failed to queue notification ID {$notification->id}: {$e->getMessage()}");

				Log::error('Failed to queue notification', [
					'notification_id' => $notification->id,
					'error' => $e->getMessage(),
					'contact_id' => $notification->contact->id ?? null,
				]);
			}
		}

		$this->info('Command completed:');
		$this->info("- Notifications queued: {$sentCount}");

		if ($errorCount > 0)
		{
			$this->warn("- Errors encountered: {$errorCount}");
		}

		return Command::SUCCESS;
	}
}
