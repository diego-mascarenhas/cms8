<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageDeliveryStat;
use Illuminate\Console\Command;

class UpdateMessageStats extends Command
{
	protected $signature = 'message:update-stats {message_id?}';

	protected $description = 'Update message delivery statistics based on real deliveries';

	public function handle()
	{
		$messageId = $this->argument('message_id');

		if ($messageId)
		{
			$this->updateStatsForMessage($messageId);
		} else
		{
			$messages = Message::all();
			foreach ($messages as $message)
			{
				$this->updateStatsForMessage($message->id);
			}
		}

		return 0;
	}

	private function updateStatsForMessage($messageId)
	{
		$message = Message::with('deliveries')->find($messageId);

		if (! $message)
		{
			$this->error("Message {$messageId} not found");

			return;
		}

		$deliveries = $message->deliveries;

		$subscribers = $deliveries->count();
		$sent = $deliveries->whereNotNull('sent_at')->count();
		$delivered = $deliveries->whereNotNull('delivered_at')->count();
		$opened = $deliveries->whereNotNull('opened_at')->count();
		$failed = $deliveries->where('status_id', 4)->count();
		$pending = $deliveries->whereNull('sent_at')->count();

		// For now, set clicks to 0 - will be updated when tracking is implemented
		$clicks = 0;

		$ratio = $subscribers > 0 ? round(($opened / $subscribers) * 100, 2) : 0;

		MessageDeliveryStat::updateOrCreate(
			['message_id' => $messageId],
			[
				'subscribers' => $subscribers,
				'sent' => $sent,
				'delivered' => $delivered,
				'opened' => $opened,
				'clicks' => $clicks,
				'failed' => $failed,
				'remaining' => $pending,
				'rejected' => 0,
				'unsubscribed' => 0,
				'unique_opens' => $opened, // Assuming each open is unique for now
				'ratio' => $ratio,
			],
		);

		$this->info("Updated stats for message {$messageId}: {$subscribers} subscribers, {$sent} sent, {$delivered} delivered, {$opened} opened");
	}
}
