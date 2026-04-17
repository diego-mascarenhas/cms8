@extends('layouts/blankLayout')

@section('title', 'Legal documents')

@section('page-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-basic px-4">
  <div class="authentication-inner py-4">
    <div class="app-brand mb-4">
      <a href="{{ url('/') }}" class="app-brand-link gap-2">
        <span class="app-brand-logo demo">@include('_partials.macros', ["height" => 20, "withbg" => 'fill: #fff;'])</span>
      </a>
    </div>
    <div class="card">
      <div class="card-body">
        <h2 class="mb-1">Legal documents</h2>
        <p class="text-muted mb-4">{{ $configData['templateName'] }} — policies and disclosures for reviewers and users.</p>

        <ul class="list-unstyled mb-0">
          <li class="mb-3">
            <a href="{{ route('legal.application') }}" class="fw-medium d-block">Application overview</a>
            <span class="text-muted small">Product description (OAuth “Application home page”).</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('legal.license') }}" class="fw-medium d-block">License (GNU AGPL v3)</a>
            <span class="text-muted small">Open-source license summary and link to the official FSF text.</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('legal.google-connection') }}" class="fw-medium d-block">Optional Google connection</a>
            <span class="text-muted small">What the OAuth integration does and where to read the details.</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('legal.google-user-data') }}" class="fw-medium d-block">Google user data &amp; Limited Use</a>
            <span class="text-muted small">How Google Contacts and Calendar data are used.</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('legal.data-deletion') }}" class="fw-medium d-block">Data deletion &amp; disconnect</a>
            <span class="text-muted small">Stop Google sync and request data removal.</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('privacy') }}" class="fw-medium d-block">Privacy Policy</a>
            <span class="text-muted small">What we collect, how we use it, and your rights.</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('cookies') }}" class="fw-medium d-block">Cookie Policy</a>
            <span class="text-muted small">Session, security, and optional third-party cookies.</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('terms') }}" class="fw-medium d-block">Terms and Conditions</a>
            <span class="text-muted small">Rules for using the website and the service.</span>
          </li>
          <li class="mb-3">
            <a href="{{ route('security') }}" class="fw-medium d-block">Security Policy</a>
            <span class="text-muted small">How we protect data, access, and reporting issues.</span>
          </li>
          <li class="mb-0">
            <a href="{{ route('sla') }}" class="fw-medium d-block">Service Level Agreement (SLA)</a>
            <span class="text-muted small">Uptime targets, maintenance, and support expectations.</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
