<?php

namespace App\Providers;

use App\Listeners\AssignAdminRole;
use App\Listeners\EnableCoreModulesForTeam;
use App\Listeners\SendNewUserWelcomeEmailListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Jetstream\Events\TeamCreated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
            AssignAdminRole::class,
            SendNewUserWelcomeEmailListener::class,
        ],
        TeamCreated::class => [
            EnableCoreModulesForTeam::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Activity log tracking was removed from the application
        // If you need to track login/logout events, implement a custom solution

        /*
        // Track user login
        Event::listen(Login::class, function (Login $event)
        {
            // Custom login tracking logic can be added here
        });

        // Track user logout
        Event::listen(Logout::class, function (Logout $event)
        {
            // Custom logout tracking logic can be added here
        });
        */
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
