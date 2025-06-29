<?php

namespace App\Providers;

use App\Listeners\AssignAdminRole;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

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
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Track user login
        Event::listen(Login::class, function (Login $event) {
            activity()
                ->causedBy($event->user)
                ->performedOn($event->user)
                ->withProperties([
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'session_id' => session()->getId(),
                ])
                ->log('User logged in');
        });

        // Track user logout
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                activity()
                    ->causedBy($event->user)
                    ->performedOn($event->user)
                    ->withProperties([
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'session_id' => session()->getId(),
                    ])
                    ->log('User logged out');
            }
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
