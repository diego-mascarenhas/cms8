@extends('layouts/layoutMaster')

@section('title', __('Edit order'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Orders') }}/</span> {{ __('Edit') }} #{{ $order['number'] ?? $order['id'] ?? '' }}</h4>
            <p class="text-muted">{{ __('Orders from your WooCommerce store') }}</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('order.index') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">{{ __('Edit order') }}</h5>
        <form class="card-body" action="{{ route('order.update', $order['id']) }}" method="POST">
            @csrf
            @method('PUT')

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @php
                $billing = $order['billing'] ?? [];
            @endphp

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="status" class="form-label">{{ __('Status') }} (*)</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="pending" {{ old('status', $order['status'] ?? '') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                        <option value="processing" {{ old('status', $order['status'] ?? '') === 'processing' ? 'selected' : '' }}>{{ __('Processing') }}</option>
                        <option value="on-hold" {{ old('status', $order['status'] ?? '') === 'on-hold' ? 'selected' : '' }}>{{ __('On hold') }}</option>
                        <option value="completed" {{ old('status', $order['status'] ?? '') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                        <option value="cancelled" {{ old('status', $order['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                        <option value="refunded" {{ old('status', $order['status'] ?? '') === 'refunded' ? 'selected' : '' }}>{{ __('Refunded') }}</option>
                        <option value="failed" {{ old('status', $order['status'] ?? '') === 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <x-input-textarea id="customer_note" label="{{ __('Customer note') }}" rows="2"
                        value="{{ old('customer_note', $order['customer_note'] ?? '') }}" />
                </div>

                <div class="col-12">
                    <h6 class="mb-2">{{ __('Billing address') }}</h6>
                </div>
                <div class="col-md-4">
                    <label for="billing_first_name" class="form-label">{{ __('First name') }}</label>
                    <input type="text" id="billing_first_name" name="billing[first_name]" class="form-control @error('billing.first_name') is-invalid @enderror"
                        value="{{ old('billing.first_name', $billing['first_name'] ?? '') }}">
                    @error('billing.first_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_last_name" class="form-label">{{ __('Last name') }}</label>
                    <input type="text" id="billing_last_name" name="billing[last_name]" class="form-control @error('billing.last_name') is-invalid @enderror"
                        value="{{ old('billing.last_name', $billing['last_name'] ?? '') }}">
                    @error('billing.last_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_company" class="form-label">{{ __('Company') }}</label>
                    <input type="text" id="billing_company" name="billing[company]" class="form-control @error('billing.company') is-invalid @enderror"
                        value="{{ old('billing.company', $billing['company'] ?? '') }}">
                    @error('billing.company')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="billing_address_1" class="form-label">{{ __('Address 1') }}</label>
                    <input type="text" id="billing_address_1" name="billing[address_1]" class="form-control @error('billing.address_1') is-invalid @enderror"
                        value="{{ old('billing.address_1', $billing['address_1'] ?? '') }}">
                    @error('billing.address_1')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="billing_address_2" class="form-label">{{ __('Address 2') }}</label>
                    <input type="text" id="billing_address_2" name="billing[address_2]" class="form-control @error('billing.address_2') is-invalid @enderror"
                        value="{{ old('billing.address_2', $billing['address_2'] ?? '') }}">
                    @error('billing.address_2')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_city" class="form-label">{{ __('City') }}</label>
                    <input type="text" id="billing_city" name="billing[city]" class="form-control @error('billing.city') is-invalid @enderror"
                        value="{{ old('billing.city', $billing['city'] ?? '') }}">
                    @error('billing.city')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_state" class="form-label">{{ __('State') }}</label>
                    <input type="text" id="billing_state" name="billing[state]" class="form-control @error('billing.state') is-invalid @enderror"
                        value="{{ old('billing.state', $billing['state'] ?? '') }}">
                    @error('billing.state')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_postcode" class="form-label">{{ __('Postcode') }}</label>
                    <input type="text" id="billing_postcode" name="billing[postcode]" class="form-control @error('billing.postcode') is-invalid @enderror"
                        value="{{ old('billing.postcode', $billing['postcode'] ?? '') }}">
                    @error('billing.postcode')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_country" class="form-label">{{ __('Country') }}</label>
                    <input type="text" id="billing_country" name="billing[country]" class="form-control @error('billing.country') is-invalid @enderror"
                        value="{{ old('billing.country', $billing['country'] ?? '') }}" maxlength="2">
                    @error('billing.country')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_email" class="form-label">{{ __('Email') }}</label>
                    <input type="email" id="billing_email" name="billing[email]" class="form-control @error('billing.email') is-invalid @enderror"
                        value="{{ old('billing.email', $billing['email'] ?? '') }}">
                    @error('billing.email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="billing_phone" class="form-label">{{ __('Phone') }}</label>
                    <input type="text" id="billing_phone" name="billing[phone]" class="form-control @error('billing.phone') is-invalid @enderror"
                        value="{{ old('billing.phone', $billing['phone'] ?? '') }}">
                    @error('billing.phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
                <a href="{{ route('order.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
