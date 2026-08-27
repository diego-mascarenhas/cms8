@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.two_factor.title'))

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row{{ config('custom.custom.authMinimalLayout') ? ' justify-content-center' : '' }}">
    @include('auth.partials.left-cover-column', ['coverIllustration' => 'auth-two-step-illustration'])
    <!-- Two Steps Verification -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-4 p-sm-5">
      <div class="w-px-400 mx-auto">
        @include('auth.partials.logo-full')
        <h3 class="mb-1">{{ __('auth.two_factor.heading') }}</h3>
        <div x-data="{ recovery: false }">
          <div class="mb-3" x-show="! recovery">
            {{ __('auth.two_factor.auth_description') }}
          </div>

          <div class="mb-3" x-show="recovery">
            {{ __('auth.two_factor.recovery_description') }}
          </div>

          <x-validation-errors class="mb-1" />

          <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf

            <div class="mb-3" x-show="! recovery">
              <x-label class="form-label" value="{{ __('auth.two_factor.code_label') }}" />
              <x-input class="{{ $errors->has('code') ? 'is-invalid' : '' }}" type="text" inputmode="numeric" name="code" autofocus x-ref="code" autocomplete="one-time-code" />
              <x-input-error for="code"></x-input-error>
            </div>

            <div class="mb-3" x-show="recovery">
              <x-label class="form-label" value="{{ __('auth.two_factor.recovery_code_label') }}" />
              <x-input class="{{ $errors->has('recovery_code') ? 'is-invalid' : '' }}" type="text" name="recovery_code" x-ref="recovery_code" autocomplete="one-time-code" />
              <x-input-error for="recovery_code"></x-input-error>
            </div>

            <div class="d-flex justify-content-end my-2 gap-2">
              <div x-show="! recovery" x-on:click="recovery = true; $nextTick(() => { $refs.recovery_code.focus()})">
                <button type="button" class="btn btn-outline-secondary me-1">
                  {{ __('auth.two_factor.use_recovery') }}
                </button>
              </div>
              <div x-cloak x-show="recovery" x-on:click="recovery = false; $nextTick(() => { $refs.code.focus() })">
                <button type="button" class="btn btn-outline-secondary me-1">
                  {{ __('auth.two_factor.use_authentication') }}
                </button>
              </div>
              <x-button>{{ __('auth.two_factor.login_button') }}</x-button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- / Two Steps Verification -->
  </div>
</div>

@endsection