@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.login.title'))

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<style>
.assistant-content p { margin-bottom: 0.5rem; }
.assistant-content p:last-child { margin-bottom: 0; }
.assistant-content ul, .assistant-content ol { padding-left: 1.25rem; margin-bottom: 0.5rem; }
.assistant-content li { margin-bottom: 0.25rem; }
.assistant-content strong { font-weight: 600; }
.assistant-content h2, .assistant-content h3 { font-size: 1rem; margin: 0.75rem 0 0.5rem; }
/* Assistant block only: card fills and messages area scrolls */
.assistant-chat-wrapper { min-height: 420px; display: flex; flex-direction: column; }
.assistant-chat-wrapper .card { flex: 1; display: flex; flex-direction: column; min-height: 360px; }
.assistant-chat-wrapper .card .card-body { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.assistant-chat-wrapper .card .card-body > div:first-of-type { flex: 1; min-height: 0; overflow: auto; }
</style>
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">
    <!-- /Left Text -->
    @include('auth.partials.cover-left', ['coverIllustration' => 'auth-login-illustration'])
    <!-- /Left Text -->

    <!-- Login -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4" x-data="{ showAssistant: false }">
      <div class="w-px-400 mx-auto w-100">
        <!-- Login block (visible by default) -->
        <div x-show="!showAssistant" x-transition class="d-lg-none mb-3">@include('auth.partials.cta-button', ['mobile' => true])</div>
        <div x-show="!showAssistant" x-transition>
          <!-- Logo -->
          <div class="app-brand mb-4">
              @include('auth.partials.logo-full', ['logoId' => 'logo'])
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

          <button type="button" class="btn btn-label-secondary d-grid w-100 mt-3" @click="showAssistant = true">
            <i class="ti ti-robot me-2"></i>{{ __('Prueba el asistente') }}
          </button>
        </div>

        <!-- Assistant block (hidden by default, replaces content when shown) -->
        <div x-show="showAssistant" x-transition class="assistant-chat-wrapper" style="display: none;">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="app-brand mb-0">
                @include('auth.partials.logo-full', ['logoId' => 'logo-assistant'])
            </div>
            <button type="button" class="btn btn-sm btn-label-secondary" @click="showAssistant = false">
              <i class="ti ti-arrow-left me-1"></i>{{ __('Volver al inicio de sesión') }}
            </button>
          </div>
          @livewire('assistant-chat')
        </div>
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
  const animateLogo = {{ config('custom.custom.animateLogo') ? 'true' : 'false' }};
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
