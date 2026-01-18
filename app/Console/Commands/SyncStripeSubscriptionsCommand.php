<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\SyncStripeSubscriptions as SyncStripeSubscriptionsAction;
use Illuminate\Console\Command;

class SyncStripeSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza las suscripciones de Stripe y registra cambios locales.';

    /**
     * Execute the console command.
     */
    public function handle(SyncStripeSubscriptionsAction $sync): int
    {
        $count = $sync->handle();

        $this->info("Sincronización completada: {$count} suscripciones procesadas.");

        return self::SUCCESS;
    }
}
