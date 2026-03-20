<?php

namespace App\Providers;

use App\Contracts\WhatsAppGateway;
use App\Models\SubscriptionProduct;
use App\Models\TicketResponse;
use App\Observers\SubscriptionProductObserver;
use App\Observers\TicketResponseObserver;
use App\Services\Stripe\StripeProductService;
use App\Services\WhatsApp\CloudWhatsAppGateway;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;
use Yajra\DataTables\Html\Builder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register StripeProductService
        $this->app->singleton(StripeProductService::class, function ($app)
        {
            return new StripeProductService(
                new StripeClient(config('cashier.secret')),
            );
        });

        $this->app->bind(WhatsAppGateway::class, function ($app)
        {
            if (config('whatsapp.driver') === 'local')
            {
                return new LocalWhatsAppGateway(
                    config('whatsapp.local.base_url', ''),
                    config('whatsapp.local.webhook_secret'),
                );
            }

            return new CloudWhatsAppGateway(WhatsAppMessageService::forCurrentUser());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::useVite();
        JsonResource::withoutWrapping();

        // Register SubscriptionProduct Observer
        SubscriptionProduct::observe(SubscriptionProductObserver::class);
        TicketResponse::observe(TicketResponseObserver::class);

        // Only register CustomTranslator when not in console mode to prevent bootstrap issues
        if (! $this->app->runningInConsole())
        {
            // Force override the translator after it's been resolved
            $this->app->extend('translator', function ($translator, $app)
            {
                $loader = $app['translation.loader'];
                $locale = $app['config']['app.locale'];

                $customTranslator = new \App\Translation\CustomTranslator($loader, $locale);
                $customTranslator->setFallback($app['config']['app.fallback_locale']);

                return $customTranslator;
            });
        }
    }
}
