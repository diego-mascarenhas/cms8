<?php

namespace App\Http\Controllers\Homes;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlashLandingLeadRequest;
use App\Mail\SlashLandingInterestMail;
use App\Services\HumanoPricingPlanResolver;
use App\Support\ApplicationLocales;
use App\Support\HumanoGuidePresentations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SlashLandingController extends Controller
{
    /**
     * @return list<array{url: string, title: string, subtitle: string, description: string, icon: string}>
     */
    public static function guidePresentations(): array
    {
        return HumanoGuidePresentations::all();
    }

    public static function isConfiguredAsPublicHome(): bool
    {
        if (config('app.public_home_route') === 'slash')
        {
            return true;
        }

        if (filled(config('app.public_home_route')))
        {
            return false;
        }

        $path = config('app.public_home_path');

        if (! is_string($path) || $path === '')
        {
            return false;
        }

        return '/'.ltrim($path, '/') === '/slash';
    }

    public function index(): View
    {
        if (! self::isConfiguredAsPublicHome())
        {
            abort(404);
        }

        app()->setLocale(ApplicationLocales::DEFAULT);

        return view('homes.slash.landing', [
            'guidePresentations' => self::guidePresentations(),
            'landingPlans' => app(HumanoPricingPlanResolver::class)->plansForDisplay(),
        ]);
    }

    public function storeLead(StoreSlashLandingLeadRequest $request): RedirectResponse
    {
        if (! self::isConfiguredAsPublicHome())
        {
            abort(404);
        }

        app()->setLocale(ApplicationLocales::DEFAULT);

        $validated = $request->validated();
        $email = $validated['email'];
        $source = $validated['source'] ?? 'cta';
        $name = filled($validated['name'] ?? null) ? trim((string) $validated['name']) : null;
        $phone = filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null;
        $sourceLabel = __('slash_landing.lead.sources.'.$source);
        $submittedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');

        $recipient = config('app.notification_email');

        if (filled($recipient))
        {
            Mail::to((string) $recipient)->send(new SlashLandingInterestMail($email, $sourceLabel, $submittedAt, $name, $phone));
        } else
        {
            Log::channel('leads')->warning('Slash landing lead notification skipped: NOTIFICATION_EMAIL is not configured.', [
                'email' => $email,
                'source' => $source,
            ]);
        }

        Log::channel('leads')->info(sprintf(
            '[%s] Slash landing lead - Email: %s, Name: %s, Phone: %s, Source: %s',
            $submittedAt,
            $email,
            $name ?? 'no proporcionado',
            $phone ?? 'no proporcionado',
            $source,
        ));

        return redirect()
            ->to(route('slash').'#precios')
            ->with('slash_lead_sent', true);
    }
}
