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
        <dl class="row mb-4">
            <dt class="col-sm-3">{{ __('stripe_subscription.link.stripe_customer_id') }}</dt>
            <dd class="col-sm-9"><code>{{ $subscription->customer_id ?? '—' }}</code></dd>
            <dt class="col-sm-3">{{ __('stripe_subscription.columns.customer_name') }}</dt>
            <dd class="col-sm-9">{{ $subscription->customer_name ?: '—' }}</dd>
        </dl>

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
