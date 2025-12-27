<?php

namespace App\Console\Commands;

use App\Enums\EmailPlan;
use Illuminate\Console\Command;
use Stripe\Price;
use Stripe\Stripe;

class StripePriceInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:price-info {priceId? : Stripe Price ID to query}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get information about a Stripe price ID and its associated plan';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Stripe::setApiKey(config('cashier.secret'));

        $priceId = $this->argument('priceId');

        // If no price ID provided, show all active subscriptions
        if (! $priceId)
        {
            return $this->showAllSubscriptions();
        }

        try
        {
            $this->info("🔍 Fetching price information for: {$priceId}");
            $this->newLine();

            // Retrieve the price
            $price = Price::retrieve([
                'id' => $priceId,
                'expand' => ['product'],
            ]);

            // Display price information
            $this->info('💰 Price Information:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Price ID', $price->id],
                    ['Product ID', is_string($price->product) ? $price->product : $price->product->id],
                    ['Product Name', is_string($price->product) ? 'N/A' : $price->product->name],
                    ['Amount', number_format($price->unit_amount / 100, 2).' '.strtoupper($price->currency)],
                    ['Interval', $price->recurring->interval ?? 'N/A'],
                    ['Active', $price->active ? '✅ Yes' : '❌ No'],
                ],
            );

            // Try to determine EmailPlan
            $this->newLine();
            $emailPlan = EmailPlan::fromStripePriceId($priceId);
            $this->info("📧 Detected Email Plan: {$emailPlan->getDisplayName()}");

            // Show plan configuration
            $this->newLine();
            $this->info('⚙️  Plan Configuration:');
            $this->table(
                ['Feature', 'Limit'],
                [
                    ['Monthly Emails', number_format($emailPlan->getMonthlyLimit())],
                    ['Daily Emails', $emailPlan->getDailyLimit() ? number_format($emailPlan->getDailyLimit()) : 'Unlimited'],
                    ['Contacts', number_format($emailPlan->getContactLimit())],
                ],
            );

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Show all active subscriptions
     */
    protected function showAllSubscriptions(): int
    {
        $this->info('📊 Active Subscriptions Report:');
        $this->newLine();

        try
        {
            $subscriptions = \App\Models\Team::with('owner')
                ->whereHas('subscriptions', function ($query)
                {
                    $query->where('stripe_status', 'active');
                })
                ->get();

            if ($subscriptions->isEmpty())
            {
                $this->warn('⚠️  No active subscriptions found.');

                return Command::SUCCESS;
            }

            $data = [];
            foreach ($subscriptions as $team)
            {
                $subscription = $team->subscription('default');
                if ($subscription && $subscription->active())
                {
                    $emailPlan = EmailPlan::fromStripePriceId($subscription->stripe_price);

                    $data[] = [
                        'Team' => $team->name,
                        'Owner' => $team->owner->email ?? 'N/A',
                        'Price ID' => $subscription->stripe_price,
                        'Detected Plan' => $emailPlan->getDisplayName(),
                        'Status' => $subscription->stripe_status,
                    ];
                }
            }

            $this->table(
                ['Team', 'Owner', 'Price ID', 'Detected Plan', 'Status'],
                $data,
            );

            $this->newLine();
            $this->info('💡 Tip: Run "php artisan stripe:price-info <priceId>" to see details of a specific price.');

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
