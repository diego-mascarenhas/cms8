<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class ShowStripeProduct extends Command
{
    protected $signature = 'stripe:product {productId}';

    protected $description = 'Displays all details of a Stripe product, including its prices';

    public function handle()
    {
        $stripeKey = env('STRIPE_SECRET');
        $this->info('Using key: '.substr($stripeKey, 0, 12).'...');

        Stripe::setApiKey($stripeKey);

        $productId = $this->argument('productId');

        try {
            $product = Product::retrieve($productId);

            if ($product) {
                $this->info('Product Details:');
                $this->info(json_encode($product, JSON_PRETTY_PRINT));

                $this->info('Prices:');
                $prices = Price::all(['product' => $productId]);
                foreach ($prices->data as $price) {
                    $this->info('Price ID: '.$price->id);
                    $this->info('Currency: '.$price->currency);
                    $this->info('Unit Amount: '.$price->unit_amount);
                    $this->info('Tax Behavior: '.$price->tax_behavior);
                    $this->info('Recurring: '.json_encode($price->recurring, JSON_PRETTY_PRINT));
                    $this->info('---');
                }
            } else {
                $this->error('No product found with ID: '.$productId);
            }

        } catch (\Exception $e) {
            $this->error('Stripe Error: '.$e->getMessage());
            $this->error('Error Type: '.get_class($e));
            $this->error('Trace: '.$e->getTraceAsString());
        }
    }
}
