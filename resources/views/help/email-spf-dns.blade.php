@extends('layouts/layoutHelpSimple')

@section('title', __('help_email_spf_dns.page_title'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="ti ti-shield-check text-primary ti-md" aria-hidden="true"></i>
                <h4 class="card-title mb-0">{{ __('help_email_spf_dns.title') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('help_email_spf_dns.intro') }}</p>

                <h5 class="mt-4">{{ __('help_email_spf_dns.required_record_heading') }}</h5>
                <p class="mb-2">{{ __('help_email_spf_dns.required_record_body') }}</p>
                <pre class="mb-3"><code>{{ \App\Helpers\DnsHelper::REVISION_ALPHA_SPF_INCLUDE }}</code></pre>

                <h5 class="mt-4">{{ __('help_email_spf_dns.example_heading') }}</h5>
                <p class="mb-2">{{ __('help_email_spf_dns.example_body') }}</p>
                <pre class="mb-0"><code>{{ \App\Helpers\DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT }}</code></pre>

                <h5 class="mt-4">{{ __('help_email_spf_dns.domain_heading') }}</h5>
                <p class="mb-0">{{ __('help_email_spf_dns.domain_body') }}</p>

                <h5 class="mt-4">{{ __('help_email_spf_dns.why_heading') }}</h5>
                <p class="mb-0">{{ __('help_email_spf_dns.why_body') }}</p>

                <h5 class="mt-4">{{ __('help_email_spf_dns.includes_chain_heading') }}</h5>
                <p class="mb-0">{{ __('help_email_spf_dns.includes_chain_body') }}</p>

                <h5 class="mt-4">{{ __('help_email_spf_dns.propagation_heading') }}</h5>
                <p class="mb-0">{{ __('help_email_spf_dns.propagation_body') }}</p>

                <h5 class="mt-4">{{ __('help_email_spf_dns.verify_heading') }}</h5>
                <p class="mb-2">{{ __('help_email_spf_dns.verify_body') }}</p>
                <pre class="language-bash mb-2"><code>dig TXT example.com +short</code></pre>
                <p class="text-muted small mb-0">{{ __('help_email_spf_dns.verify_note') }}</p>

                <h5 class="mt-4">{{ __('help_email_spf_dns.own_smtp_heading') }}</h5>
                <p class="mb-0">{{ __('help_email_spf_dns.own_smtp_body') }}</p>

                <p class="mt-4 mb-0">
                    <a href="{{ route('help.index') }}" class="btn btn-label-secondary">{{ __('help_email_spf_dns.back_to_help') }}</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
