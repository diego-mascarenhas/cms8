<?php

namespace App\Console\Commands;

use App\Jobs\SendMessageCampaignJob;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledDeliveries extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'campaigns:send-scheduled';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Send scheduled message deliveries that are due';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->info('📤 Checking for scheduled deliveries...');

		// Get deliveries that are scheduled to be sent now or in the past
		$dueDeliveries = MessageDelivery::where('status_id', 1) // pending
			->where('sent_at', '<=', now())
			->whereNull('delivered_at') // not delivered yet
			->with(['contact', 'message', 'team']) // eager load relations
			->orderBy('sent_at', 'asc')
			->limit(config('services.email.processing.deliveries_per_send_run', 100)) // Configurable limit
			->get();

		if ($dueDeliveries->isEmpty())
		{
			$this->info('📭 No deliveries due for sending.');
			return 0;
		}

		$this->info("📧 Found {$dueDeliveries->count()} deliveries due for sending");

		$successCount = 0;
		$errorCount = 0;

		foreach ($dueDeliveries as $delivery)
		{
			try
			{
				// Dispatch the job to send the email
				SendMessageCampaignJob::dispatch($delivery);

				$this->info("   ✅ Queued delivery {$delivery->id} to {$delivery->contact->email}");
				$successCount++;

			} catch (\Exception $e)
			{
				$this->error("   ❌ Failed to queue delivery {$delivery->id}: {$e->getMessage()}");
				$errorCount++;

				Log::error('Failed to queue delivery', [
					'delivery_id' => $delivery->id,
					'contact_email' => $delivery->contact->email ?? 'unknown',
					'error' => $e->getMessage(),
				]);
			}
		}

		$this->info("🎉 Queued {$successCount} deliveries, {$errorCount} errors");

		Log::info('📊 SendScheduledDeliveries completed', [
			'deliveries_queued' => $successCount,
			'errors' => $errorCount,
		]);

		return 0;
	}
}
