@extends('layouts/blankLayout')

@section('title', 'Application overview')

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
        @include('legal.partials.document-nav')
        <h2>{{ $configData['templateName'] }} — Application overview</h2>
        <p><strong>{{ $configData['templateName'] }}</strong> is a team-based business application (CRM-style) for managing contacts, projects, communications, billing, and related workflows. Access is provided to invited users within an organization or team.</p>

        <h3>What the product does</h3>
        <ul>
          <li>Stores and processes business data that your team enters or imports (including optional integrations such as Google Contacts and Calendar when enabled).</li>
          <li>Provides dashboards, lists, messaging, and integrations configured by your team administrators.</li>
        </ul>

        <h3>Policies</h3>
        <ul>
          <li><a href="{{ route('legal.index') }}">All legal documents</a></li>
          <li><a href="{{ route('legal.license') }}">License (GNU AGPL v3)</a></li>
          <li><a href="{{ route('legal.google-connection') }}">Optional Google connection</a></li>
          <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
          <li><a href="{{ route('cookies') }}">Cookie Policy</a></li>
          <li><a href="{{ route('terms') }}">Terms and Conditions</a></li>
          <li><a href="{{ route('security') }}">Security Policy</a></li>
          <li><a href="{{ route('legal.google-user-data') }}">Google user data &amp; Limited Use</a></li>
          <li><a href="{{ route('legal.data-deletion') }}">Data deletion &amp; disconnect</a></li>
        </ul>

        <h3>Contact</h3>
        <p>For questions about this application, contact us at @include('legal.partials.support-link', ['label' => 'Support']).</p>
      </div>
    </div>
  </div>
</div>
@endsection
