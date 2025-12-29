<?php

namespace App\Providers;

use App\Models\Subscription;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Use our custom Subscription model instead of Cashier's default
        Cashier::useSubscriptionModel(Subscription::class);
    }
}
