@extends('layouts/blankLayout')

@section('title', 'Google connection (optional)')

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
        <h2>Optional Google connection</h2>
        <p><strong>{{ $configData['templateName'] }}</strong> can connect to a user’s Google account so that the team can sync <strong>Google Contacts</strong> and <strong>Google Calendar</strong> into the product. This integration is <strong>optional</strong>: you choose whether to start OAuth from your team settings.</p>

        <h3>What is authorized</h3>
        <p>Access is read-only for contacts and calendar, plus standard OpenID identifiers (email/profile) needed to identify the connected account. Exact scope names appear on Google’s consent screen when you connect.</p>

        <h3>What we use it for</h3>
        <p>Data is used only to display and synchronize information inside <strong>{{ $configData['templateName'] }}</strong> for your team (for example CRM contacts and calendar-related features). We do not sell Google user data to third parties; see our detailed disclosure.</p>

        <h3>Detailed documents</h3>
        <ul>
          <li><a href="{{ route('legal.google-user-data') }}">Google user data &amp; Limited Use</a> — storage, Limited Use, and Google API Services User Data Policy.</li>
          <li><a href="{{ route('legal.data-deletion') }}">Data deletion &amp; disconnect</a> — how to revoke access and what happens to synced data.</li>
        </ul>

        <h3>Technical entry points</h3>
        <p>OAuth starts from the in-product team settings flow. The redirect URL is always <code>{{ rtrim(config('app.url'), '/') }}/integrations/google/callback</code> (derived from <code>APP_URL</code>).</p>

        <h3>Related</h3>
        <ul>
          <li><a href="{{ route('legal.application') }}">Application overview</a></li>
          <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
