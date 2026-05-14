@php
    $businessCfgTeam = $team ?? auth()->user()->currentTeam ?? auth()->user()->teams->first();
    $canUpdateBusinessTeam = $businessCfgTeam && auth()->user()->can('update', $businessCfgTeam);
    $needsBusinessConfig = $canUpdateBusinessTeam && ! $businessCfgTeam->hasCompletedBusinessConfiguration();
    $showWhatsappQrCta = $canUpdateBusinessTeam && session()->has(\App\Support\HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA);
    $wrapInDashboardTopRow = $dashboardTopRow ?? false;
@endphp
@if ($needsBusinessConfig || $showWhatsappQrCta)
    @if ($wrapInDashboardTopRow)
        <div class="row mb-4">
            <div class="col-12">
    @endif
    <div class="alert alert-warning mb-0" role="alert">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <i class="ti {{ $needsBusinessConfig ? 'ti-alert-triangle' : 'ti-qrcode' }} ti-lg me-2 flex-shrink-0"></i>
            <div class="flex-grow-1 min-w-0">
                @if ($needsBusinessConfig)
                    @php
                        $onboardingWelcomeFirstName = explode(' ', (string) auth()->user()->name, 2)[0] ?: auth()->user()->name;
                    @endphp
                    <span class="fw-medium d-block">{{ __('Welcome name to app onboarding banner', ['name' => $onboardingWelcomeFirstName, 'app' => config('app.name')]) }}</span>
                    <span class="small text-muted d-block mt-1">{{ __('Welcome onboarding complete business hint') }}</span>
                @else
                    <span class="fw-medium d-block">{{ __('humano_pricing.dashboard_post_checkout_whatsapp_title') }}</span>
                    <span class="small text-muted">{{ __('humano_pricing.dashboard_post_checkout_whatsapp_body') }}</span>
                @endif
            </div>
            @if ($needsBusinessConfig)
                <a href="{{ route('team-settings.business-config', $businessCfgTeam) }}" class="btn btn-warning btn-sm waves-effect waves-light">
                    <i class="ti ti-building-store ti-sm me-1"></i>{{ __('Configure business') }}
                </a>
                <a href="{{ route('registration.onboarding.qr') }}" class="btn btn-warning btn-sm waves-effect waves-light">
                    <i class="ti ti-qrcode ti-sm me-1"></i>{{ __('humano_pricing.dashboard_post_checkout_whatsapp_button') }}
                </a>
            @else
                <a href="{{ route('registration.onboarding.qr') }}" class="btn btn-warning btn-sm waves-effect waves-light">
                    <i class="ti ti-qrcode ti-sm me-1"></i>{{ __('humano_pricing.dashboard_post_checkout_whatsapp_button') }}
                </a>
            @endif
        </div>
    </div>
    @if ($wrapInDashboardTopRow)
            </div>
        </div>
    @endif
@endif
