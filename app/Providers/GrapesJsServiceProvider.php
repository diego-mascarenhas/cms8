<?php

namespace App\Providers;

use App\Repositories\TeamAssetRepository;
use Dotlogics\Grapesjs\App\Repositories\AssetRepository;
use Illuminate\Support\ServiceProvider;

class GrapesJsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind our custom team-aware asset repository
        $this->app->bind(AssetRepository::class, TeamAssetRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
