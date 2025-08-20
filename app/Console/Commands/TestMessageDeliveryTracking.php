<?php

namespace App\Console\Commands;

use App\Models\MessageDelivery;
use App\Models\MessageDeliveryTracking;
use Illuminate\Console\Command;

class TestMessageDeliveryTracking extends Command
{
	/**
	 * The name and signature of the console command.
	 */
	protected $signature = 'test:message-delivery-tracking {delivery_id?}';

	/**
	 * The console command description.
	 */
	protected $description = 'Test the improved message delivery tracking system';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->info('🧪 Testing Message Delivery Tracking System');
		$this->line('');

		// Get delivery ID
		$deliveryId = $this->argument('delivery_id');
		if (! $deliveryId)
		{
			// Get first available delivery
			$delivery = MessageDelivery::first();
			if (! $delivery)
			{
				$this->error('❌ No message deliveries found. Send a test campaign first.');

				return 1;
			}
			$deliveryId = $delivery->id;
		} else
		{
			$delivery = MessageDelivery::find($deliveryId);
			if (! $delivery)
			{
				$this->error("❌ Delivery ID {$deliveryId} not found.");

				return 1;
			}
		}

		$this->info("📧 Testing with Delivery ID: {$delivery->id}");
		$contactName = $delivery->contact->name ?? 'Unknown';
		$contactEmail = $delivery->contact->email ?? 'Unknown';
		$this->info("📧 Contact: {$contactName} ({$contactEmail})");
		$this->line('');

		// Test 1: Create tracking events
		$this->info('🧪 Test 1: Creating tracking events');

		// Test opened event
		$openedEvent = MessageDeliveryTracking::createEvent(
			$delivery->id,
			'opened',
			[
				'source' => 'test_command',
				'test_run' => now(),
				'user_location' => 'Madrid, Spain',
			],
		);
		$this->line("✅ Opened event created: ID {$openedEvent->id}");

		// Test clicked event
		$clickedEvent = MessageDeliveryTracking::createEvent(
			$delivery->id,
			'clicked',
			[
				'source' => 'test_command',
				'test_run' => now(),
				'clicked_url' => 'https://example.com/test',
			],
		);
		$this->line("✅ Clicked event created: ID {$clickedEvent->id}");

		$this->line('');

		// Test 2: Use scopes
		$this->info('🧪 Test 2: Testing scopes');

		$openedCount = MessageDeliveryTracking::where('message_delivery_id', $delivery->id)
			->opened()
			->count();
		$this->line("✅ Opened events for this delivery: {$openedCount}");

		$clickedCount = MessageDeliveryTracking::where('message_delivery_id', $delivery->id)
			->clicked()
			->count();
		$this->line("✅ Clicked events for this delivery: {$clickedCount}");

		$this->line('');

		// Test 3: Show all events
		$this->info('🧪 Test 3: All tracking events for this delivery');
		$events = MessageDeliveryTracking::where('message_delivery_id', $delivery->id)
			->orderBy('tracked_at', 'desc')
			->get();

		if ($events->count() > 0)
		{
			$this->table(
				['ID', 'Event', 'Tracked At', 'IP', 'Metadata'],
				$events->map(function ($event)
				{
					return [
						$event->id,
						$event->event,
						$event->tracked_at->format('Y-m-d H:i:s'),
						$event->ip_address,
						json_encode($event->metadata, JSON_PRETTY_PRINT),
					];
				}),
			);
		} else
		{
			$this->line('📭 No tracking events found');
		}

		$this->line('');

		// Test 4: URLs
		$this->info('🧪 Test 4: Tracking URLs');
		$trackingUrl = $delivery->getTrackingUrl();
		$this->line("✅ Tracking URL: {$trackingUrl}");

		$trackedUrl = $delivery->getTrackedUrl('https://example.com');
		$this->line("✅ Tracked URL: {$trackedUrl}");

		$this->line('');
		$this->info('🎉 All tests completed successfully!');
		$this->line('');
		$this->comment('💡 Tip: Visit the tracking URL in browser to test real tracking');
		$this->comment("💡 URL: {$trackingUrl}");

		return 0;
	}
}
