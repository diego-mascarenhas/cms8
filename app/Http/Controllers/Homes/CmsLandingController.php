<?php

namespace App\Http\Controllers\Homes;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlashLandingLeadRequest;
use App\Mail\SlashLandingInterestMail;
use App\Support\GuidePresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CmsLandingController extends Controller
{
    public function index(): View
    {
        return view('homes.cms.landing', [
            'presentationUrl' => GuidePresentation::url('cms-wordpress'),
        ]);
    }

    public function newsletter(): View
    {
        return view('homes.cms.newsletter', [
            'landingUrl' => route('cms.landing'),
            'presentationUrl' => GuidePresentation::url('cms-wordpress'),
            'registerUrl' => route('cms.landing').'#empezar',
        ]);
    }

    public function storeLead(StoreSlashLandingLeadRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $email = $validated['email'];
        $source = $validated['source'] ?? 'cta';
        $name = filled($validated['name'] ?? null) ? trim((string) $validated['name']) : null;
        $phone = filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null;
        $sourceLabel = __('cms_landing.lead.sources.'.$source);
        $submittedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');

        $recipient = config('app.notification_email');

        if (filled($recipient))
        {
            Mail::to((string) $recipient)->send(new SlashLandingInterestMail($email, $sourceLabel, $submittedAt, $name, $phone));
        } else
        {
            Log::channel('leads')->warning('CMS landing lead notification skipped: NOTIFICATION_EMAIL is not configured.', [
                'email' => $email,
                'source' => $source,
            ]);
        }

        Log::channel('leads')->info(sprintf(
            '[%s] CMS landing lead - Email: %s, Name: %s, Phone: %s, Source: %s',
            $submittedAt,
            $email,
            $name ?? 'no proporcionado',
            $phone ?? 'no proporcionado',
            $source,
        ));

        return redirect()
            ->to(route('cms.landing').'#empezar')
            ->with('slash_lead_sent', true);
    }
}
