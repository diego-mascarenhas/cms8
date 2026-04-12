@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.registration.billing_title'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg min-vh-100">
  <div class="authentication-inner row justify-content-center">
    <div class="d-flex col-12 col-md-10 col-lg-7 col-xl-6 align-items-center p-sm-5 p-4">
      <section class="w-100" aria-labelledby="registration-payment-heading">
        <div class="app-brand mb-4">
          <a href="{{ url('/') }}" class="app-brand-link gap-2">
            @include('auth.partials.logo-full')
          </a>
        </div>
        <h4 id="registration-payment-heading" class="mb-1">{{ __('auth.registration.billing_heading') }}</h4>
        <p class="text-muted mb-4">{{ __('auth.registration.billing_description') }}</p>

        <div class="card shadow-sm">
          <div class="card-body p-4 p-md-5">
            @if ($checkoutProduct)
            <p class="small mb-3">
              <span class="fw-medium">{{ $checkoutProduct->name }}</span>
              @if ($checkoutProduct->getFormattedPrice() !== '—')
              <span class="text-muted">· {{ $checkoutProduct->getFormattedPrice() }}</span>
              @endif
            </p>
            @endif

            @if (session('error'))
            <div class="alert alert-danger mb-3" role="alert">{{ session('error') }}</div>
            @endif

            <a href="{{ route('registration.checkout.start') }}" class="btn btn-primary w-100 mb-2">
              {{ __('auth.registration.pay_with_stripe') }}
            </a>
            <form method="POST" action="{{ route('logout') }}" class="d-inline w-100">
              @csrf
              <button type="submit" class="btn btn-label-secondary w-100">{{ __('auth.registration.sign_out') }}</button>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
