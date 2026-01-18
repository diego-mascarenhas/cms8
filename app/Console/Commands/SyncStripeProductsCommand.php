<?php

namespace App\Console\Commands;

use App\Actions\Products\SyncStripeProducts;
use Illuminate\Console\Command;

class SyncStripeProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize subscription products from Stripe';

    /**
     * Execute the console command.
     */
    public function handle(SyncStripeProducts $syncAction): int
    {
        $this->info('Starting product synchronization from Stripe...');

        try
        {
            $processed = $syncAction->handle();

            $this->info("Successfully synchronized {$processed} products from Stripe.");

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('Error synchronizing products: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
