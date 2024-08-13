<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Bruler\AuthService;
use App\Services\Bruler\OrderService;

class BrulerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AuthService::class, function ($app)
        {
            return new AuthService();
        });

        $this->app->singleton(OrderService::class, function ($app)
        {
            return new OrderService($app->make(AuthService::class));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Load routes, migration, views, etc. if necesary.
        // Example: $this->loadRoutesFrom(__DIR__.'/../Routes/bruler.php');
    }
}
