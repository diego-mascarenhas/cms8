@extends('layouts/layoutHelpSimple')

@section('title', __('help_onboarding.page_title'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 bg-label-success">
                <div class="d-flex align-items-center">
                    <i class="ti ti-rocket text-success me-2"></i>
                    <h4 class="card-title mb-0">{{ __('help_onboarding.title') }}</h4>
                </div>
                <a href="{{ route('help.index') }}" class="btn btn-sm btn-label-secondary">{{ __('help_onboarding.back_to_help') }}</a>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('help_onboarding.lead') }}</p>

                <div class="alert alert-info mb-4" role="alert">
                    <i class="ti ti-video me-2"></i>
                    {{ __('help_onboarding.video_note') }}
                </div>

                <h5 class="mt-2">{{ __('help_onboarding.overview_heading') }}</h5>
                <p>{{ __('help_onboarding.overview_intro') }}</p>
                <ol class="mb-4">
                    @foreach (trans('help_onboarding.overview_steps') as $step)
                        <li class="mb-1">{{ $step }}</li>
                    @endforeach
                </ol>

                <div class="card bg-label-warning mb-4">
                    <div class="card-body">
                        <h6 class="mb-2">
                            <i class="ti ti-alert-triangle me-1"></i>
                            {{ __('help_onboarding.dashboard_banner_heading') }}
                        </h6>
                        <p class="mb-2">{{ __('help_onboarding.dashboard_banner_body') }}</p>
                        <ul class="mb-2">
                            <li><strong>{{ __('Configure business') }}</strong> — {{ __('help_onboarding.dashboard_banner_configure') }}</li>
                            <li><strong>{{ __('humano_pricing.dashboard_post_checkout_whatsapp_button') }}</strong> — {{ __('help_onboarding.dashboard_banner_whatsapp') }}</li>
                        </ul>
                        <p class="text-muted small mb-0">{{ __('help_onboarding.dashboard_banner_hint') }}</p>
                    </div>
                </div>

                <h5 class="mt-4" id="step-checkout">{{ __('help_onboarding.step1_heading') }}</h5>
                <p>{{ __('help_onboarding.step1_intro') }}</p>
                <p class="mb-2">
                    <strong>{{ __('help_onboarding.step1_path_label') }}:</strong>
                    <a href="{{ route('pricing') }}" class="link-primary">{{ route('pricing') }}</a>
                </p>
                <ol class="mb-3">
                    @foreach (trans('help_onboarding.step1_steps') as $step)
                        <li class="mb-2">{{ $step }}</li>
                    @endforeach
                </ol>
                <p class="text-muted">{{ __('help_onboarding.step1_after_payment') }}</p>

                <h5 class="mt-5" id="step-business-config">{{ __('help_onboarding.step2_heading') }}</h5>
                <p>{{ __('help_onboarding.step2_intro') }}</p>
                <p>{{ __('help_onboarding.step2_access') }}</p>
                <h6 class="mt-3">{{ __('help_onboarding.step2_wizard_heading') }}</h6>
                <ol class="mb-3">
                    @foreach (trans('help_onboarding.step2_wizard_steps') as $step)
                        <li class="mb-1">{{ $step }}</li>
                    @endforeach
                </ol>
                <div class="alert alert-primary mb-0" role="alert">
                    <i class="ti ti-bulb me-1"></i>
                    {{ __('help_onboarding.step2_tip') }}
                </div>

                <h5 class="mt-5" id="step-whatsapp-qr">{{ __('help_onboarding.step3_heading') }}</h5>
                <p>{{ __('help_onboarding.step3_intro') }}</p>
                <p class="mb-2">
                    <strong>{{ __('help_onboarding.step3_path_label') }}:</strong>
                    <a href="{{ route('registration.onboarding.qr') }}" class="link-primary">{{ route('registration.onboarding.qr') }}</a>
                </p>
                <h6 class="mt-3">{{ __('help_onboarding.step3_phone_heading') }}</h6>
                <ol class="mb-3">
                    @foreach (trans('help_onboarding.step3_phone_steps') as $step)
                        <li class="mb-1">{{ $step }}</li>
                    @endforeach
                </ol>
                <p>{{ __('help_onboarding.step3_refresh') }}</p>
                <p>{{ __('help_onboarding.step3_connected') }}</p>
                <p class="text-muted small mb-0">{{ __('help_onboarding.step3_cloud_note') }}</p>

                <hr class="my-4">

                <h5>{{ __('help_onboarding.next_heading') }}</h5>
                <p class="mb-0">{{ __('help_onboarding.next_body') }}</p>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="{{ route('manual.index') }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-book me-1"></i>{{ __('help_onboarding.next_manual_link') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
