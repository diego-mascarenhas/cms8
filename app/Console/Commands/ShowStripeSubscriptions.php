<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Subscription;

class ShowStripeSubscriptions extends Command
{
	protected $signature = 'stripe:subscriptions';

	protected $description = 'Muestra todas las suscripciones activas de Stripe';

	public function handle()
	{
		$stripeKey = env('STRIPE_SECRET');
		$this->info('Using key: '.substr($stripeKey, 0, 12).'...');

		Stripe::setApiKey($stripeKey);

		try
		{
			$subscriptions = Subscription::all([
				'limit' => 100,
				'status' => 'all', // canceled, active, past_due
				// 'expand' => ['data.customer']
			]);

			if ($subscriptions->count() === 0)
			{
				$this->warn('No subscriptions found');

				return;
			}

			$this->info('Found '.$subscriptions->count().' subscriptions');

			$headers = ['ID', 'Customer', 'Plan', 'Status', 'Next Payment'];
			$rows = [];

			foreach ($subscriptions as $subscription)
			{
				$rows[] = [
					$subscription->id,
					$subscription->customer,
					$subscription->items->data[0]->price->product,
					$subscription->status,
					date('Y-m-d', $subscription->current_period_end),
				];
			}

			$this->table($headers, $rows);
		} catch (\Exception $e)
		{
			$this->error('Stripe Error: '.$e->getMessage());
			$this->error('Error Type: '.get_class($e));
		}
	}
}
