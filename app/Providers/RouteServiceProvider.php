<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request)
        {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function ()
        {
            Route::middleware('api')
                ->prefix('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            // Stripe webhook - NO middleware to avoid CSRF (default account)
            Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook']);
            // Per-category Stripe webhooks (multi-account)
            Route::post('/stripe/webhook/{category}', [\App\Http\Controllers\CategoryStripeWebhookController::class, 'handleWebhook'])
                ->where('category', 'mentoring|mailer|prospecting|hosting|support');

            // Local WhatsApp (Baileys) webhook - NO web middleware to avoid CSRF
            Route::post('/webhook/whatsapp-local', [\App\Http\Controllers\WhatsAppLocalWebhookController::class, 'handleIncomingMessage'])
                ->name('webhook.whatsapp-local');
            Route::post('/webhook/whatsapp-local/{hash}', [\App\Http\Controllers\WhatsAppLocalWebhookController::class, 'handleIncomingMessage'])
                ->name('webhook.whatsapp-local.team');

            // Mailbox package removed - routes commented out
            // Route::middleware('mailbox')
            // 	->prefix('mailbox')
            // 	->group(base_path('routes/mailbox.php'));
        });
    }
}
