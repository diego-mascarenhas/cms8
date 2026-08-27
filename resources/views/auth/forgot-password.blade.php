@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.forgot_password.title'))

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row{{ config('custom.custom.authMinimalLayout') ? ' justify-content-center' : '' }}">
    @include('auth.partials.cover-left', ['coverIllustration' => 'auth-forgot-password-illustration'])
    <!-- Forgot Password -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        @include('auth.partials.logo-full')
        <h3 class="mb-1">{{ __('auth.forgot_password.heading') }}</h3>
        <p class="mb-4">{{ __('auth.forgot_password.description') }}</p>

        @if (session('status'))
        <div class="mb-1 text-success">
          {{ session('status') }}
        </div>
        @endif

        <form id="formAuthentication" class="mb-3" action="{{ route('password.email') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label for="email" class="form-label">{{ __('auth.forgot_password.email') }}</label>
            <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="{{ __('auth.forgot_password.email_placeholder') }}" autofocus>
            @error('email')
            <span class="invalid-feedback" role="alert">
              <span class="fw-medium">{{ $message }}</span>
            </span>
            @enderror
          </div>
          <button type="submit" class="btn btn-primary d-grid w-100">
            {{ __('auth.forgot_password.send_reset_link') }}
          </button>
        </form>
        <div class="text-center">
          @if (Route::has('login'))
          <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center">
            <i class="ti ti-chevron-left scaleX-n1-rtl"></i>
            {{ __('auth.forgot_password.back_to_login') }}
          </a>
          @endif
        </div>
      </div>
    </div>
    <!-- /Forgot Password -->
  </div>
</div>
@endsection