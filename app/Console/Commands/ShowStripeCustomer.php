<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentMethod;

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
                'expand' => ['subscriptions', 'default_source']
            ]);

            // Customer Details
            $this->info("\n📋 Customer Details:");
            $this->table(['Field', 'Value'], [
                ['ID', $customer->id],
                ['Email', $customer->email],
                ['Name', $customer->name],
                ['Phone', $customer->phone ?? 'Not specified'],
                ['Created', date('Y-m-d H:i:s', $customer->created)],
                ['Active Subscriptions', $customer->subscriptions->count()],
            ]);

            // Payment Methods
            $this->info("\n💳 Payment Methods:");
            $paymentMethods = PaymentMethod::all([
                'customer' => $customerId,
                'type' => 'card'
            ]);

            if ($paymentMethods->count() > 0) {
                $rows = [];
                foreach ($paymentMethods as $pm) {
                    $rows[] = [
                        $pm->id,
                        $pm->card->brand,
                        "**** {$pm->card->last4}",
                        "{$pm->card->exp_month}/{$pm->card->exp_year}",
                        $pm->id === $customer->default_source ? 'Yes' : 'No'
                    ];
                }
                $this->table(
                    ['ID', 'Brand', 'Last 4', 'Expiration', 'Default'],
                    $rows
                );
            }

            // Subscriptions
            if ($customer->subscriptions->count() > 0) {
                $this->info("\n📅 Subscriptions:");
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

            // Invoices
            $this->info("\n📑 Recent Invoices:");
            $invoices = Invoice::all([
                'customer' => $customerId,
                'limit' => 5,
                'expand' => ['data.payment_intent']
            ]);

            if ($invoices->count() > 0) {
                $rows = [];
                foreach ($invoices as $invoice) {
                    $rows[] = [
                        $invoice->number,
                        date('Y-m-d', $invoice->created),
                        $invoice->amount_paid / 100 . ' ' . strtoupper($invoice->currency),
                        $invoice->status,
                        $invoice->payment_intent ? $invoice->payment_intent->status : 'N/A'
                    ];
                }
                $this->table(
                    ['Number', 'Date', 'Amount', 'Status', 'Payment Status'],
                    $rows
                );
            } else {
                $this->info("No invoices found");
            }

        } catch (\Exception $e) {
            $this->error("Stripe Error: " . $e->getMessage());
        }
    }
} 