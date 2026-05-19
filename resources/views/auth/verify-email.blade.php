@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', 'Verify Email')

@section('page-style')
  {{-- Page Css files --}}
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg ">
  <div class="authentication-inner row{{ config('custom.custom.authMinimalLayout') ? ' justify-content-center' : '' }}">
    @include('auth.partials.left-cover-column', ['coverIllustration' => 'auth-verify-email-illustration'])

    <!--  Verify email -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-4 p-sm-5">
      <div class="w-px-400 mx-auto">
        <div class="app-brand mb-4">
          <a href="{{url('/')}}" class="app-brand-link gap-2">
            @include('auth.partials.logo-full')
          </a>
        </div>
        <h3 class="mb-1">Verify your email ✉️</h3>
        @if (session('status') == 'verification-link-sent')
          <div class="alert alert-success" role="alert">
            <div class="alert-body">
              A new verification link has been sent to the email address you provided during registration.
            </div>
          </div>
        @endif
        <p class="text-start mb-4">
          Account activation link sent to your email address: <span class="fw-medium">{{Auth::user()->email}}</span> Please follow the link inside to continue.
        </p>
        <div class="mt-4 d-flex gap-2">
          <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-label-secondary">click here to request another</button>
          </form>

          <form method="POST" action="{{route('logout')}}">
            @csrf
            <button type="submit" class="btn btn-danger">Log Out</button>
          </form>
        </div>
      </div>
    </div>
    <!-- / Verify email -->
  </div>
</div>
@endsection