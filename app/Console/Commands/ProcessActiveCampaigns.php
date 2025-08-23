<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessActiveCampaigns extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'campaigns:process-active';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Process active campaigns and create message deliveries with scheduled times';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->info('🚀 Processing active campaigns...');

		// Get all active messages that have been started
		$activeMessages = Message::where('status_id', 1)
			->whereNotNull('started_at')
			->get();

		if ($activeMessages->isEmpty())
		{
			$this->info('📭 No active campaigns found.');
			return 0;
		}

		$totalProcessed = 0;
		$totalCreated = 0;

		foreach ($activeMessages as $message)
		{
			$this->info("📧 Processing campaign: {$message->name} (ID: {$message->id})");

			$created = $this->processMessageCampaign($message);
			$totalCreated += $created;
			$totalProcessed++;

			if ($created > 0)
			{
				$this->info("   ✅ Created {$created} new deliveries");
			} else
			{
				$this->info("   ⏸️  No new deliveries needed");
			}
		}

		$this->info("🎉 Processed {$totalProcessed} campaigns, created {$totalCreated} deliveries");

		Log::info('📊 ProcessActiveCampaigns completed', [
			'campaigns_processed' => $totalProcessed,
			'deliveries_created' => $totalCreated,
		]);

		return 0;
	}

	/**
	 * Process a single message campaign
	 */
	private function processMessageCampaign(Message $message): int
	{
		// Get contacts for this message
		$contacts = $this->getContactsForMessage($message);

		// Get the last delivery time for this message to calculate next send time
		$lastDelivery = MessageDelivery::where('message_id', $message->id)
			->orderBy('sent_at', 'desc')
			->first();

		// Calculate base time for next deliveries
		$baseTime = $lastDelivery ? $lastDelivery->sent_at : $message->started_at;

		$createdCount = 0;
		$deliveryIndex = MessageDelivery::where('message_id', $message->id)->count();

		foreach ($contacts as $contact)
		{
			// Check if delivery already exists
			$existingDelivery = MessageDelivery::where('message_id', $message->id)
				->where('contact_id', $contact->id)
				->first();

			if (!$existingDelivery)
			{
				// Calculate scheduled time based on the last delivery + random interval
				$baseMinutes = config('services.email.delay.base_minutes', 5);
				$maxRandomSeconds = config('services.email.delay.random_seconds', 120);

				$delayMinutes = $deliveryIndex * $baseMinutes;
				$randomSeconds = rand(0, $maxRandomSeconds);
				$scheduledTime = $baseTime->copy()->addMinutes($delayMinutes)->addSeconds($randomSeconds);

				MessageDelivery::create([
					'team_id' => $message->team_id,
					'message_id' => $message->id,
					'contact_id' => $contact->id,
					'status_id' => 1, // pending
					'sent_at' => $scheduledTime,
				]);

				$createdCount++;
				$deliveryIndex++;

				// Only create one delivery per run to spread the load
				break;
			}
		}

		return $createdCount;
	}

	/**
	 * Get contacts for a message based on its category
	 */
	private function getContactsForMessage(Message $message)
	{
		if ($message->category)
		{
			return $message->category->contacts()->where('status_id', 1)->get();
		} else
		{
			// If no category, get all active contacts from the team
			return \App\Models\Contact::where('team_id', $message->team_id)
				->where('status_id', 1)
				->whereNotNull('email')
				->get();
		}
	}
}
