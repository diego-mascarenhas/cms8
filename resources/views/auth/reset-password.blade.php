@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.reset_password.title'))

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row{{ config('custom.custom.authMinimalLayout') ? ' justify-content-center' : '' }}">
    @include('auth.partials.left-cover-column', ['coverIllustration' => 'auth-forgot-password-illustration'])
    <!-- Reset Password -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="app-brand mb-4">
          <a href="{{url('/')}}" class="app-brand-link">
            @include('auth.partials.logo-full')
          </a>
        </div>
        <!-- /Logo -->
        <h3 class="mb-1">{{ __('auth.reset_password.heading') }}</h3>
        <form id="formAuthentication" class="mb-3" action="{{ route('password.update') }}" method="POST">
          @csrf
          <input type="hidden" name="token" value="{{ $request->route('token') }}">

          <div class="mb-3">
            <label for="email" class="form-label">{{ __('auth.reset_password.email') }}</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="john@example.com" value="{{Request()->email}}" readonly />
            @error('email')
            <span class="invalid-feedback" role="alert">
              <span class="fw-medium">{{ $message }}</span>
            </span>
            @enderror
          </div>

          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="password">{{ __('auth.reset_password.new_password') }}</label>
            <div class="input-group input-group-merge @error('password') is-invalid @enderror">
              <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" autofocus />
              <span class="input-group-text cursor-pointer">
                <i class="ti ti-eye-off"></i>
              </span>
            </div>
            @error('password')
            <span class="invalid-feedback" role="alert">
              <span class="fw-medium">{{ $message }}</span>
            </span>
            @enderror
          </div>
          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="confirm-password">{{ __('auth.reset_password.confirm_password') }}</label>
            <div class="input-group input-group-merge">
              <input type="password" id="confirm-password" class="form-control" name="password_confirmation" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
              <span class="input-group-text cursor-pointer">
                <i class="ti ti-eye-off"></i>
              </span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary d-grid w-100 mb-3">
            {{ __('auth.reset_password.set_password') }}
          </button>
          <div class="text-center">
            @if (Route::has('login'))
            <a href="{{ route('login') }}">
              <i class="ti ti-chevron-left scaleX-n1-rtl"></i>
              {{ __('auth.reset_password.back_to_login') }}
            </a>
            @endif
          </div>
        </form>
      </div>
    </div>
    <!-- /Reset Password -->
  </div>
</div>
@endsection