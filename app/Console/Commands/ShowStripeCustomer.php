<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Customer;

class ShowStripeCustomer extends Command
{
    protected $signature = 'stripe:customer {customerId}';
    protected $description = 'Displays the details of a specific Stripe customer';

    public function handle()
    {
        $stripeKey = env('STRIPE_SECRET');
        Stripe::setApiKey($stripeKey);

        try {
            $customerId = $this->argument('customerId');
            $customer = Customer::retrieve([
                'id' => $customerId,
                'expand' => ['subscriptions']
            ]);

            $this->info("Customer Details:");
            $this->table(['Field', 'Value'], [
                ['ID', $customer->id],
                ['Email', $customer->email],
                ['Name', $customer->name],
                ['Phone', $customer->phone ?? 'Not specified'],
                ['Created', date('Y-m-d H:i:s', $customer->created)],
                ['Active Subscriptions', $customer->subscriptions->count()],
            ]);

            if ($customer->subscriptions->count() > 0) {
                $this->info("\nSubscriptions:");
                $rows = [];
                foreach ($customer->subscriptions->data as $subscription) {
                    $rows[] = [
                        $subscription->id,
                        $subscription->items->data[0]->price->product,
                        $subscription->status,
                        date('Y-m-d', $subscription->current_period_end),
                        $subscription->items->data[0]->price->unit_amount / 100 . ' ' . 
                            strtoupper($subscription->items->data[0]->price->currency)
                    ];
                }
                
                $this->table(
                    ['ID', 'Product', 'Status', 'Next Payment', 'Price'],
                    $rows
                );
            }

        } catch (\Exception $e) {
            $this->error("Stripe Error: " . $e->getMessage());
        }
    }
} 