<?php

namespace App\Providers;

use App\Contracts\CheckoutSessionRetriever;
use App\Contracts\WhatsAppGateway;
use App\Http\Controllers\TeamInvitationAcceptController;
use App\Livewire\Teams\TeamMemberManager;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\SubscriptionProduct;
use App\Models\Task;
use App\Models\Team;
use App\Models\TicketResponse;
use App\Observers\CalendarEventWebDavOutboundObserver;
use App\Observers\ContactGoogleOutboundObserver;
use App\Observers\ContactWebDavOutboundObserver;
use App\Observers\SubscriptionProductObserver;
use App\Observers\TaskWebDavOutboundObserver;
use App\Observers\TeamDefaultPromptsObserver;
use App\Observers\TicketResponseObserver;
use App\Services\AssistantToolsService;
use App\Services\Fiscal\Cuentica\CuenticaFiscalExportAdapter;
use App\Services\Fiscal\FiscalExportRouter;
use App\Services\Fiscal\NullFiscalExportAdapter;
use App\Services\Stripe\StripeCheckoutSessionRetriever;
use App\Services\Stripe\StripeProductService;
use App\Services\WhatsApp\CloudWhatsAppGateway;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\UnimplementedWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Support\WhatsAppDriver;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stripe\StripeClient;
use Yajra\DataTables\Html\Builder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CheckoutSessionRetriever::class, StripeCheckoutSessionRetriever::class);

        $this->app->scoped(AssistantToolsService::class);

        // Register StripeProductService
        $this->app->singleton(StripeProductService::class, function ($app)
        {
            return new StripeProductService(
                new StripeClient(config('cashier.secret')),
            );
        });

        $this->app->bind(FiscalExportRouter::class, function ($app)
        {
            return new FiscalExportRouter(
                adapters: [
                    CuenticaFiscalExportAdapter::PLATFORM => $app->make(CuenticaFiscalExportAdapter::class),
                ],
                nullAdapter: $app->make(NullFiscalExportAdapter::class),
            );
        });

        $this->app->bind(WhatsAppGateway::class, function ($app)
        {
            $team = auth()->user()?->currentTeam;
            $driver = WhatsAppDriver::forTeam($team);
            if ($driver === WhatsAppDriver::LOCAL)
            {
                $baseUrl = $team?->getWhatsAppServiceBaseUrl() ?: config('whatsapp.local.base_url', '');

                return new LocalWhatsAppGateway(
                    $baseUrl,
                    config('whatsapp.local.webhook_secret'),
                    $team?->id,
                );
            }

            if ($driver === WhatsAppDriver::TWILIO)
            {
                return new CloudWhatsAppGateway(WhatsAppMessageService::forCurrentUser());
            }

            return new UnimplementedWhatsAppGateway($driver);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::useVite();
        JsonResource::withoutWrapping();

        $this->app->resolving(Builder::class, function (Builder $builder): void
        {
            $builder->addTableClass('table-hover');
        });

        // Register SubscriptionProduct Observer
        SubscriptionProduct::observe(SubscriptionProductObserver::class);
        TicketResponse::observe(TicketResponseObserver::class);
        Contact::observe(ContactGoogleOutboundObserver::class);
        Contact::observe(ContactWebDavOutboundObserver::class);
        CalendarEvent::observe(CalendarEventWebDavOutboundObserver::class);
        Task::observe(TaskWebDavOutboundObserver::class);
        Team::observe(TeamDefaultPromptsObserver::class);

        $this->registerPublicTeamInvitationAcceptRoute();
        $this->registerLivewireComponentOverrides();

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

    /**
     * Override Jetstream's authenticated-only invitation route with a public signed URL.
     */
    private function registerPublicTeamInvitationAcceptRoute(): void
    {
        Route::middleware(config('jetstream.middleware', ['web']))
            ->get('/team-invitations/{invitation}', TeamInvitationAcceptController::class)
            ->middleware('signed')
            ->name('team-invitations.accept');
    }

    private function registerLivewireComponentOverrides(): void
    {
        Livewire::component('teams.team-member-manager', TeamMemberManager::class);
    }
}
