@extends('layouts/layoutHelpSimple')

@section('title', __('help_team_billing.page_title'))

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
        <i class="ti ti-receipt text-primary ti-md" aria-hidden="true"></i>
        <h4 class="card-title mb-0">{{ __('help_team_billing.title') }}</h4>
      </div>
      <div class="card-body">
        <p class="lead">{{ __('help_team_billing.intro') }}</p>

        <h5 class="mt-4">{{ __('help_team_billing.where_heading') }}</h5>
        <p>{{ __('help_team_billing.where_body') }}</p>
        <p class="mb-1"><strong>{{ __('help_team_billing.where_path') }}</strong></p>
        <p class="mb-0"><code>{{ __('help_team_billing.where_route') }}</code></p>

        <h5 class="mt-4">{{ __('help_team_billing.two_invoices_heading') }}</h5>
        <ul class="mb-0">
          <li>{{ __('help_team_billing.two_invoices_plan') }}</li>
          <li>{{ __('help_team_billing.two_invoices_usage') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('help_team_billing.rates_heading') }}</h5>
        <p>{{ __('help_team_billing.rates_intro') }}</p>
        <ul>
          <li>{{ __('help_team_billing.rates_tokens') }}</li>
          <li>{{ __('help_team_billing.rates_whatsapp') }}</li>
          <li>{{ __('help_team_billing.rates_mailer') }}</li>
        </ul>
        <p class="mb-0">{{ __('help_team_billing.rates_history') }}</p>

        <h5 class="mt-4">{{ __('help_team_billing.frequency_heading') }}</h5>
        <p>{{ __('help_team_billing.frequency_intro') }}</p>
        <ul class="mb-0">
          <li>{{ __('help_team_billing.frequency_weekly') }}</li>
          <li>{{ __('help_team_billing.frequency_monthly') }}</li>
          <li>{{ __('help_team_billing.frequency_anchor') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('help_team_billing.change_heading') }}</h5>
        <p>{{ __('help_team_billing.change_intro') }}</p>
        <ul class="mb-0">
          <li>{{ __('help_team_billing.change_close') }}</li>
          <li>{{ __('help_team_billing.change_open') }}</li>
          <li>{{ __('help_team_billing.change_stripe') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('help_team_billing.items_heading') }}</h5>
        <p>{{ __('help_team_billing.items_intro') }}</p>
        <ul>
          <li>{{ __('help_team_billing.items_tokens') }}</li>
          <li>{{ __('help_team_billing.items_sources') }}</li>
          <li>{{ __('help_team_billing.items_whatsapp') }}</li>
          <li>{{ __('help_team_billing.items_mailer') }}</li>
        </ul>
        <p class="mb-0">{{ __('help_team_billing.items_total') }}</p>

        <h5 class="mt-4">{{ __('help_team_billing.preview_heading') }}</h5>
        <ul class="mb-0">
          <li>{{ __('help_team_billing.preview_kpis') }}</li>
          <li>{{ __('help_team_billing.preview_table') }}</li>
          <li>{{ __('help_team_billing.preview_months') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('help_team_billing.status_heading') }}</h5>
        <ul class="mb-0">
          <li>{{ __('help_team_billing.status_not_issued') }}</li>
          <li>{{ __('help_team_billing.status_adjustments') }}</li>
          <li>{{ __('help_team_billing.status_weeks') }}</li>
          <li>{{ __('help_team_billing.status_mailer') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('help_team_billing.cli_heading') }}</h5>
        <p class="mb-2">{{ __('help_team_billing.cli_body') }}</p>
        <pre class="language-bash mb-2"><code>{{ __('help_team_billing.cli_example') }}</code></pre>
        <p class="mb-0">{{ __('help_team_billing.cli_products') }}</p>

        <h5 class="mt-4">{{ __('help_team_billing.related_heading') }}</h5>
        <p class="mb-2">
          <a href="{{ route('help.stripe-webhook') }}">{{ __('help_team_billing.related_stripe') }}</a>
        </p>
        <p class="mb-0">
          <a href="{{ route('manual.billing') }}">{{ __('help_team_billing.related_manual') }}</a>
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
