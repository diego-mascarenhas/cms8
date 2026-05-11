@php
    $businessCfgTeam = $team ?? auth()->user()->currentTeam ?? auth()->user()->teams->first();
    $canUpdateBusinessTeam = $businessCfgTeam && auth()->user()->can('update', $businessCfgTeam);
    $needsBusinessConfig = $canUpdateBusinessTeam && ! $businessCfgTeam->hasCompletedBusinessConfiguration();
    $showWhatsappQrCta = $canUpdateBusinessTeam && session()->has(\App\Support\HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA);
@endphp
@if ($needsBusinessConfig || $showWhatsappQrCta)
    <div class="alert alert-primary d-flex align-items-start mb-3" role="alert">
        <i class="ti {{ $needsBusinessConfig ? 'ti-building-store' : 'ti-qrcode' }} ti-md me-2 mt-1 flex-shrink-0"></i>
        <div class="flex-grow-1">
            @if ($needsBusinessConfig)
                <div class="fw-semibold mb-1">{{ __('Complete your business configuration') }}</div>
                <p class="small mb-2">{{ __('Add your business details in a few steps to get more out of Humano.') }}</p>
            @else
                <div class="fw-semibold mb-1">{{ __('humano_pricing.dashboard_post_checkout_whatsapp_title') }}</div>
                <p class="small mb-2">{{ __('humano_pricing.dashboard_post_checkout_whatsapp_body') }}</p>
            @endif
            @if ($needsBusinessConfig)
                <div class="row g-2">
                    <div class="col-12 col-sm-6 d-flex">
                        <a href="{{ route('team-settings.business-config', $businessCfgTeam) }}" class="btn btn-sm btn-primary waves-effect waves-light w-100 align-self-stretch text-center">
                            {{ __('Configure business') }}
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 d-flex">
                        <a href="{{ route('registration.onboarding.qr') }}" class="btn btn-sm btn-primary waves-effect waves-light w-100 align-self-stretch text-center">
                            <i class="ti ti-qrcode me-1"></i>{{ __('humano_pricing.dashboard_post_checkout_whatsapp_button') }}
                        </a>
                    </div>
                </div>
            @else
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ route('registration.onboarding.qr') }}" class="btn btn-sm btn-primary waves-effect waves-light">
                        <i class="ti ti-qrcode me-1"></i>{{ __('humano_pricing.dashboard_post_checkout_whatsapp_button') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif
