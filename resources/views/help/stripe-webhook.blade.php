@extends('layouts/layoutHelpSimple')

@section('title', __('help_stripe_webhook.page_title'))

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
        <i class="ti ti-webhook text-primary ti-md" aria-hidden="true"></i>
        <h4 class="card-title mb-0">{{ __('help_stripe_webhook.title') }}</h4>
      </div>
      <div class="card-body">
        <p class="lead">{{ __('help_stripe_webhook.intro') }}</p>

        <h5 class="mt-4">{{ __('help_stripe_webhook.url_heading') }}</h5>
        <p class="mb-2">{{ __('help_stripe_webhook.url_method') }}</p>
        <p class="mb-2"><strong>{{ __('help_stripe_webhook.url_path_label') }}</strong> <code>/stripe/webhook</code></p>
        <p class="mb-2">{{ __('help_stripe_webhook.url_full_example') }}</p>
        <pre class="mb-3"><code>{{ url('/stripe/webhook') }}</code></pre>
        <p class="text-muted small mb-0">{{ __('help_stripe_webhook.url_https') }}</p>

        <h5 class="mt-4">{{ __('help_stripe_webhook.local_heading') }}</h5>
        <p class="mb-2">{{ __('help_stripe_webhook.local_body') }}</p>
        <pre class="language-bash mb-0"><code>stripe listen --forward-to {{ url('/stripe/webhook') }}</code></pre>

        <h5 class="mt-4">{{ __('help_stripe_webhook.multi_heading') }}</h5>
        <p class="mb-2">{{ __('help_stripe_webhook.multi_body') }}</p>
        <pre class="mb-0"><code>{{ url('/stripe/webhook/mailer') }}</code></pre>

        <h5 class="mt-4">{{ __('help_stripe_webhook.events_heading') }}</h5>
        <p>{{ __('help_stripe_webhook.events_intro') }}</p>
        <ul class="mb-3">
          @foreach (trans('help_stripe_webhook.events_required') as $event)
            <li><code>{{ $event }}</code></li>
          @endforeach
        </ul>
        <p class="mb-2"><strong>{{ __('help_stripe_webhook.events_recommended_heading') }}</strong></p>
        <ul class="mb-3">
          @foreach (trans('help_stripe_webhook.events_recommended') as $event)
            <li><code>{{ $event }}</code></li>
          @endforeach
        </ul>
        <p class="mb-1">{{ __('help_stripe_webhook.events_checkout') }}</p>
        <ul class="mb-0">
          <li><code>{{ __('help_stripe_webhook.events_checkout_item') }}</code></li>
        </ul>

        <h5 class="mt-4">{{ __('help_stripe_webhook.scope_heading') }}</h5>
        <p class="mb-0">{{ __('help_stripe_webhook.scope_body') }}</p>

        <h5 class="mt-4">{{ __('help_stripe_webhook.secret_heading') }}</h5>
        <p class="mb-0">{{ __('help_stripe_webhook.secret_body') }}</p>
      </div>
    </div>
  </div>
</div>
@endsection
