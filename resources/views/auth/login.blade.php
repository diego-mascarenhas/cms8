@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.login.title'))

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/css/pages/page-auth.css')) }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">
    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="{{ asset('assets/img/illustrations/auth-login-illustration-'.$configData['style'].'.png') }}" alt="auth-login-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-login-illustration-light.png" data-app-dark-img="illustrations/auth-login-illustration-dark.png">

        <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-login-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>
    <!-- /Left Text -->

    <!-- Login -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="app-brand mb-4">
            <span id="logo" class="app-brand-logo demo">@include('_partials.macros',["height"=>20,"withbg"=>'fill: #fff;'])</span>
        </div>
        <!-- /Logo -->
        <h3 class="mb-1">
            {{ \App\Helpers\TranslationHelper::transGroup('welcome', 'auth', ['name' => config('variables.templateName')]) }}
        </h3>
        <p class="mb-4">{{ __('auth.login.description') }}</p>

        @if (session('status'))
        <div class="alert alert-success mb-1 rounded-0" role="alert">
          <div class="alert-body">
            {{ session('status') }}
          </div>
        </div>
        @endif

        <form id="formAuthentication" class="mb-3" action="{{ route('login') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label for="login-email" class="form-label">{{ __('auth.login.email') }}</label>
            <input type="text" class="form-control @error('email') is-invalid @enderror" id="login-email" name="email" placeholder="{{ __('auth.login.email_placeholder') }}" autofocus value="{{ old('email') }}">
            @error('email')
            <span class="invalid-feedback" role="alert">
              <span class="fw-medium">{{ $message }}</span>
            </span>
            @enderror
          </div>
          <div class="mb-3 form-password-toggle">
            <div class="d-flex justify-content-between">
              <label class="form-label" for="login-password">{{ __('auth.login.password') }}</label>
              @if (Route::has('password.request'))
              <a href="{{ route('password.request') }}">
                <small>{{ __('auth.login.forgot_password') }}</small>
              </a>
              @endif
            </div>
            <div class="input-group input-group-merge @error('password') is-invalid @enderror">
              <input type="password" id="login-password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
            @error('password')
            <span class="invalid-feedback" role="alert">
              <span class="fw-medium">{{ $message }}</span>
            </span>
            @enderror
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember-me" name="remember" {{ old('remember') ? 'checked' : '' }}>
              <label class="form-check-label" for="remember-me">
                {{ __('auth.login.remember_me') }}
              </label>
            </div>
          </div>
          <button class="btn btn-primary d-grid w-100" type="submit">{{ __('auth.login.sign_in') }}</button>
        </form>

        @if (Route::has('register') && config('custom.custom.showRegister'))
        <p class="text-center">
          <span>{{ __('auth.login.new_platform') }}</span>
          <a href="{{ route('register') }}">
            <span>{{ __('auth.login.create_account') }}</span>
          </a>
        </p>
        @endif
      </div>
    </div>
    <!-- /Login -->
  </div>
</div>
@endsection

<style>
  @keyframes vibrate {
    0% { transform: translate(0); }
    25% { transform: translate(-2px, 2px); }
    50% { transform: translate(2px, -2px); }
    75% { transform: translate(-2px, -2px); }
    100% { transform: translate(0); }
  }
  .vibrate {
    animation: vibrate 0.5s infinite;
  }
</style>

<script>
  const animateLogo = {{ config('custom.animateLogo') ? 'true' : 'false' }};
  if (animateLogo) {
    setInterval(() => {
      const logo = document.getElementById('logo');
      logo.classList.add('vibrate');
      setTimeout(() => {
        logo.classList.remove('vibrate');
      }, 500);
    }, 300000);
  }
</script>
