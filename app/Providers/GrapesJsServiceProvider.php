<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Dotlogics\Grapesjs\App\Repositories\AssetRepository;
use App\Repositories\TeamAssetRepository;

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