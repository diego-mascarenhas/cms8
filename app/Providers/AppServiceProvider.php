<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Yajra\DataTables\Html\Builder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::useVite();
        JsonResource::withoutWrapping();

        // Force override the translator after it's been resolved
        $this->app->extend('translator', function ($translator, $app) {
            $loader = $app['translation.loader'];
            $locale = $app['config']['app.locale'];

            $customTranslator = new \App\Translation\CustomTranslator($loader, $locale);
            $customTranslator->setFallback($app['config']['app.fallback_locale']);

            return $customTranslator;
        });
    }
}
