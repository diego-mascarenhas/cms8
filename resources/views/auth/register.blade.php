@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.register.title'))

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">
    <!-- /Left Text -->
    @include('auth.partials.cover-left', ['coverIllustration' => 'auth-register-illustration'])
    <!-- /Left Text -->

    <!-- Register -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <div class="d-lg-none mb-3">@include('auth.partials.cta-button', ['mobile' => true])</div>
        <!-- Logo -->
        <div class="app-brand mb-4">
          <a href="{{url('/')}}" class="app-brand-link gap-2">
            @include('auth.partials.logo-full')
          </a>
        </div>
        <!-- /Logo -->
        <h3 class="mb-1">{{ __('auth.register.heading') }}</h3>
        <p class="mb-4">{{ __('auth.register.description') }}</p>

        @if (! empty($teamInvitation))
        <div class="alert alert-primary mb-4" role="alert">
            {{ __('You have been invited to join the :team team. Create your account to accept.', ['team' => $teamInvitation->team->name]) }}
        </div>
        @endif

        @if (session('status'))
        <div class="alert alert-info mb-4" role="alert">
            {{ session('status') }}
        </div>
        @endif

        <form id="formAuthentication" class="mb-3" action="{{ route('register') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label for="username" class="form-label">{{ __('auth.register.username') }}</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="username" name="name" placeholder="{{ __('auth.register.username_placeholder') }}" autofocus value="{{ old('name') }}" />
            @error('name')
            <span class="invalid-feedback" role="alert">
              <span class="fw-medium">{{ $message }}</span>
            </span>
            @enderror
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">{{ __('auth.register.email') }}</label>
            <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="{{ __('auth.register.email_placeholder') }}" value="{{ old('email', $teamInvitation->email ?? '') }}" @if (! empty($lockInvitationEmail)) readonly @endif />
            @error('email')
            <span class="invalid-feedback" role="alert">
              <span class="fw-medium">{{ $message }}</span>
            </span>
            @enderror
          </div>
          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="password">{{ __('auth.register.password') }}</label>
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

          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="password-confirm">{{ __('auth.register.confirm_password') }}</label>
            <div class="input-group input-group-merge">
              <input type="password" id="password-confirm" class="form-control" name="password_confirmation" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
              <span class="input-group-text cursor-pointer">
                <i class="ti ti-eye-off"></i>
              </span>
            </div>
          </div>
          @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
            <div class="mb-3">
              <div class="form-check @error('terms') is-invalid @enderror">
                <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms" />
                <label class="form-check-label" for="terms">
                  {{ __('auth.register.terms_agree') }}
                  <a href="{{ route('policy.show') }}" target="_blank">{{ __('auth.register.privacy_policy') }}</a> &
                  <a href="{{ route('terms.show') }}" target="_blank">{{ __('auth.register.terms') }}</a>
                </label>
              </div>
              @error('terms')
                <div class="invalid-feedback" role="alert">
                    <span class="fw-medium">{{ $message }}</span>
                </div>
              @enderror
            </div>
          @endif
          <button type="submit" class="btn btn-primary d-grid w-100">{{ __('auth.register.sign_up') }}</button>
        </form>

        <p class="text-center mt-2">
          <span>{{ __('auth.register.already_account') }}</span>
          @if (Route::has('login'))
          <a href="{{ route('login') }}">
            <span>{{ __('auth.register.sign_in') }}</span>
          </a>
          @endif
        </p>
      </div>
    </div>
    <!-- /Register -->
  </div>
</div>
@endsection