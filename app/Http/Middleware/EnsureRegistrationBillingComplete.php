<?php

namespace App\Http\Middleware;

use App\Enums\RegistrationMode;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            $request->session()->forget('humano_after_public_payment_link_checkout');

            return $next($request);
        }

        if ($request->session()->pull('humano_after_public_payment_link_checkout', false))
        {
            Log::warning('Registration billing gate failed after public payment link checkout; redirecting to pricing instead of Stripe', $this->billingGateDiagnostics($request->user(), $team));

            return redirect()->route('pricing')
                ->with('error', __('humano_pricing.checkout_billing_gate_pending'));
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
            'chat.whatsapp-warmup-qr',
            'chat.whatsapp-disconnect',
            'chat.whatsapp-qr-image',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function billingGateDiagnostics(User $user, ?Team $team): array
    {
        $subscriptions = [];
        if ($team)
        {
            foreach ($team->subscriptions()->get(['id', 'stripe_id', 'stripe_status', 'stripe_price', 'type', 'data']) as $sub)
            {
                $subscriptions[] = [
                    'id' => $sub->id,
                    'stripe_id' => $sub->stripe_id,
                    'stripe_status' => $sub->stripe_status,
                    'stripe_price' => $sub->stripe_price,
                    'type' => $sub->type,
                    'data' => $sub->data,
                ];
            }
        }

        return [
            'user_id' => $user->id,
            'team_id' => $team?->id,
            'current_team_id' => $user->current_team_id,
            'registration_mode' => config('registration.mode'),
            'registration_stripe_product_id' => config('registration.stripe_product_id'),
            'subscriptions' => $subscriptions,
        ];
    }
}
