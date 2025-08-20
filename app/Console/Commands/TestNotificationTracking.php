<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class TestNotificationTracking extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'test:notification-tracking';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Test notification tracking functionality';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->info('Testing notification tracking functionality...');

		// Get the first notification
		$notification = Notification::first();

		if (! $notification)
		{
			$this->error('No notifications found. Please create some notifications first.');

			return Command::FAILURE;
		}

		$this->info("Testing with notification ID: {$notification->id}");

		// Test token generation
		$token = $notification->getTrackingToken();
		$this->info("Generated tracking token: {$token}");

		// Test token validation
		$foundNotification = Notification::findByTrackingToken($token);

		if ($foundNotification && $foundNotification->id === $notification->id)
		{
			$this->info('✅ Token validation successful!');
		} else
		{
			$this->error('❌ Token validation failed!');

			return Command::FAILURE;
		}

		// Test URL generation
		$trackingUrl = $notification->getTrackingUrl();
		$this->info("Tracking URL: {$trackingUrl}");

		$clickUrl = $notification->getTrackedUrl('https://example.com');
		$this->info("Click tracking URL: {$clickUrl}");

		// Test with invalid token
		$invalidToken = 'invalid_token_123';
		$invalidNotification = Notification::findByTrackingToken($invalidToken);

		if ($invalidNotification === null)
		{
			$this->info('✅ Invalid token correctly rejected!');
		} else
		{
			$this->error('❌ Invalid token was accepted!');

			return Command::FAILURE;
		}

		$this->info('🎉 All tests passed! Notification tracking is working correctly.');

		return Command::SUCCESS;
	}
}
