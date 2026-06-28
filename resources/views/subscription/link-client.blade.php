@extends('layouts/layoutMaster')

@section('title', __('stripe_subscription.link.title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('stripe_subscription.link.title') }}</h4>
        <p class="text-muted">{{ __('stripe_subscription.link.subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('subscription.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('stripe_subscription.link.back') }}
        </a>
    </div>
</div>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <h6 class="mb-3">{{ __('stripe_subscription.link.section_subscription') }}</h6>
        <dl class="row mb-4">
            <dt class="col-sm-3">{{ __('stripe_subscription.link.stripe_customer_id') }}</dt>
            <dd class="col-sm-9"><code>{{ $subscription->customer_id ?? '—' }}</code></dd>
            <dt class="col-sm-3">{{ __('stripe_subscription.columns.customer_name') }}</dt>
            <dd class="col-sm-9">{{ $subscription->customer_name ?: '—' }}</dd>
            <dt class="col-sm-3">{{ __('stripe_subscription.columns.customer_email') }}</dt>
            <dd class="col-sm-9">{{ $subscription->customer_email ?: '—' }}</dd>
            <dt class="col-sm-3">{{ __('stripe_subscription.columns.plan_name') }}</dt>
            <dd class="col-sm-9">{{ $subscription->plan_name ?: '—' }}</dd>
            <dt class="col-sm-3">{{ __('stripe_subscription.columns.status') }}</dt>
            <dd class="col-sm-9">
                @php
                    $statusKey = 'stripe_subscription.status.'.$subscription->status;
                    $statusLabel = __($statusKey);
                @endphp
                {{ $statusLabel === $statusKey ? ($subscription->status ?: '—') : $statusLabel }}
            </dd>
            <dt class="col-sm-3">{{ __('stripe_subscription.columns.amount_total') }}</dt>
            <dd class="col-sm-9">
                @if($subscription->amount_total !== null)
                    {{ number_format((float) $subscription->amount_total, 2, ',', '.') }} {{ strtoupper($subscription->price_currency ?? 'EUR') }}
                @else
                    —
                @endif
            </dd>
        </dl>

        <h6 class="mb-3">{{ __('stripe_subscription.link.section_service') }}</h6>
        @if($linkedService)
            <dl class="row mb-4">
                <dt class="col-sm-3">{{ __('stripe_subscription.link.service_id') }}</dt>
                <dd class="col-sm-9">
                    <a href="{{ route('service.show', $linkedService->id) }}" class="text-body">
                        #{{ $linkedService->id }}
                    </a>
                </dd>
                <dt class="col-sm-3">{{ __('stripe_subscription.link.service_type') }}</dt>
                <dd class="col-sm-9">{{ $linkedService->category?->name ?? '—' }}</dd>
                <dt class="col-sm-3">{{ __('stripe_subscription.link.service_price') }}</dt>
                <dd class="col-sm-9">
                    @if($linkedService->price !== null)
                        {{ number_format((float) $linkedService->price, 2, ',', '.') }} {{ $linkedService->currency?->symbol ?? '' }}
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-sm-3">{{ __('stripe_subscription.link.service_client') }}</dt>
                <dd class="col-sm-9">{{ $linkedService->enterprise?->name ?? '—' }}</dd>
            </dl>
        @else
            <p class="text-muted mb-4">{{ __('stripe_subscription.link.service_not_found') }}</p>
        @endif

        <h6 class="mb-3">{{ __('stripe_subscription.link.section_client_match') }}</h6>
        @if($matchedEnterprise)
            <div class="alert alert-success mb-4" role="alert">
                <div class="fw-semibold">{{ __('stripe_subscription.link.match_found') }}</div>
                <div class="small mt-1">
                    {{ $matchedEnterprise->name }}
                    @if($matchedEnterprise->email) · {{ $matchedEnterprise->email }} @endif
                    @if($matchedEnterprise->phone) · {{ $matchedEnterprise->phone }} @endif
                </div>
            </div>
        @else
            <div class="alert alert-warning mb-4" role="alert">
                <div class="fw-semibold">{{ __('stripe_subscription.link.match_not_found') }}</div>
                <div class="small mt-1">{{ __('stripe_subscription.link.match_not_found_hint') }}</div>
            </div>
        @endif

        <div class="mb-4">
            <a
                href="{{ route('client.create', ['name' => $subscription->customer_name, 'email' => $subscription->customer_email, 'code' => $subscription->customer_id, 'link_subscription_id' => $subscription->id]) }}"
                class="btn btn-outline-primary"
            >
                <i class="ti ti-building-plus me-1"></i>{{ __('stripe_subscription.link.create_client') }}
            </a>
        </div>

        <form action="{{ route('subscription.stripe-link-client.store', $subscription) }}" method="POST" class="row g-3">
            @csrf
            <div class="col-12 col-md-8">
                <label for="enterprise_id" class="form-label">{{ __('stripe_subscription.link.client_label') }}</label>
                <select name="enterprise_id" id="enterprise_id" class="form-select @error('enterprise_id') is-invalid @enderror" required>
                    <option value="">{{ __('stripe_subscription.link.client_placeholder') }}</option>
                    @foreach ($enterprises as $enterprise)
                        <option value="{{ $enterprise->id }}" @selected((string) old('enterprise_id') === (string) $enterprise->id)>
                            {{ $enterprise->name }}@if (filled($enterprise->code)) ({{ $enterprise->code }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('enterprise_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12">
                <p class="text-muted small mb-0">{{ __('stripe_subscription.link.hint') }}</p>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-link me-1"></i>{{ __('stripe_subscription.link.submit') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
