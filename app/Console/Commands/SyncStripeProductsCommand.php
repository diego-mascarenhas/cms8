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
    protected $signature = 'products:sync
                            {--category= : Stripe account category (mentoring|mailer|prospecting|hosting|support). Omit to use default Cashier account}';

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
        $category = $this->option('category');
        $this->info('Starting product synchronization from Stripe'.($category ? " (category: {$category})" : '').'...');

        try
        {
            $processed = $syncAction->handle($category ?: null);

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
