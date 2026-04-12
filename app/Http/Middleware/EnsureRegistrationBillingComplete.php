<?php

namespace App\Http\Middleware;

use App\Enums\RegistrationMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationBillingComplete
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = RegistrationMode::fromConfiguration();

        if (! $mode->requiresBillingCompletion())
        {
            return $next($request);
        }

        if (! $request->user())
        {
            return $next($request);
        }

        $team = $request->user()->currentTeam;

        if ($team?->passesRegistrationBillingGate())
        {
            return $next($request);
        }

        if ($request->routeIs('registration.*'))
        {
            return $next($request);
        }

        if ($request->routeIs('subscription.success'))
        {
            return $next($request);
        }

        if ($request->routeIs([
            'subscription.billing-info',
            'subscription.save-billing-info',
            'subscription.checkout',
        ]))
        {
            return $next($request);
        }

        if ($this->allowsShellRequestsWhileCompletingBilling($request))
        {
            return $next($request);
        }

        if ($request->routeIs('logout'))
        {
            return $next($request);
        }

        if ($mode === RegistrationMode::Checkout)
        {
            return redirect()->route('registration.checkout.start');
        }

        return redirect()->route('registration.billing');
    }

    /**
     * Routes the layout may call in the background (Livewire, timers, team switch).
     * Without this, 302 redirects from those requests can kick the user off billing-info.
     */
    private function allowsShellRequestsWhileCompletingBilling(Request $request): bool
    {
        if (str_starts_with($request->path(), 'livewire-'))
        {
            return true;
        }

        return $request->routeIs([
            'time.running',
            'attendance.running',
            'attendance.start',
            'attendance.pause',
            'attendance.resume',
            'attendance.stop',
            'current-team.update',
            'chat.whatsapp-refresh-qr',
            'chat.whatsapp-qr-image',
        ]);
    }
}
