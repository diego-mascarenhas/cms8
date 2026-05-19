@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp


@extends('layouts/blankLayout')

@section('title', __('auth.confirm_password.title'))

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row{{ config('custom.custom.authMinimalLayout') ? ' justify-content-center' : '' }}">
    @include('auth.partials.left-cover-column', ['coverIllustration' => 'auth-forgot-password-illustration'])
    <!--  password confirm -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="app-brand mb-4">
          <a href="{{url('/')}}" class="app-brand-link">
            @include('auth.partials.logo-full')
          </a>
        </div>
        <!-- /Logo -->
        <h3 class="mb-1">{{ __('auth.confirm_password.title') }}</h3>
        <p class="text-start mb-4">{{ __('auth.confirm_password.description') }}</p>
        <form id="twoStepsForm" action="{{ route('password.confirm') }}" method="POST">
          @csrf
          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="password">{{ __('auth.confirm_password.enter_password') }}</label>
            <div class="input-group input-group-merge @error('password') is-invalid @enderror">
              <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
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
          <button type="submit" class="btn btn-primary d-grid w-100 mb-3">{{ __('auth.confirm_password.confirm_button') }}</button>
        </form>
      </div>
    </div>
    <!-- / password confirm -->
  </div>
</div>
@endsection