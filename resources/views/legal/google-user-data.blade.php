@extends('layouts/blankLayout')

@section('title', 'Google user data disclosure')

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
        <h2>Google user data — use, storage &amp; Limited Use</h2>
        <p>This page describes how <strong>{{ $configData['templateName'] }}</strong> accesses Google user data when you use the optional Google sign-in / OAuth integration (Google People and Google Calendar read-only scopes).</p>

        <h3>Scopes requested</h3>
        <p>The integration requests the minimum scopes needed for the feature: typically OpenID, email and profile identifiers, read-only access to Google Contacts, and read-only access to Google Calendar. Exact scope strings match those shown on Google’s consent screen when you connect.</p>

        <h3>How we use Google user data</h3>
        <ul>
          <li><strong>Contacts:</strong> To import or update contact records inside your team workspace so you can work with them alongside your other CRM data.</li>
          <li><strong>Calendar:</strong> To read calendar events you authorize and represent them in the product (for example in calendar views or linked records), in read-only form.</li>
          <li><strong>Identity:</strong> OpenID / email / profile are used to identify the Google account you connected and to associate tokens with your user and team.</li>
        </ul>
        <p>We do <strong>not</strong> use Google user data for serving ads, and we do <strong>not</strong> sell Google user data. Access is limited to operating the features you explicitly enable by connecting Google.</p>

        <h3>Google API Services User Data Policy (Limited Use)</h3>
        <p><strong>{{ $configData['templateName'] }}</strong>’s use of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy" rel="noopener noreferrer" target="_blank">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>

        <h3>Storage &amp; security</h3>
        <p>OAuth tokens and synced content are stored on our application servers and databases under access controls appropriate to a business application. Transmission uses HTTPS. For more detail, see our <a href="{{ route('security') }}">Security Policy</a> and <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

        <h3>Retention &amp; revocation</h3>
        <p>You can disconnect Google from your team settings at any time. That stops further sync and removes the stored OAuth connection for that integration path as described in <a href="{{ route('legal.data-deletion') }}">Data deletion &amp; disconnect</a>.</p>

        <h3>Related links</h3>
        <ul>
          <li><a href="{{ route('legal.google-connection') }}">Optional Google connection</a></li>
          <li><a href="{{ route('legal.application') }}">Application overview</a></li>
          <li><a href="{{ route('legal.license') }}">License (GNU AGPL v3)</a></li>
          <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
          <li><a href="{{ route('terms') }}">Terms and Conditions</a></li>
        </ul>

        <h3>Contact</h3>
        <p>Questions: @include('legal.partials.support-link', ['label' => 'Support']).</p>
      </div>
    </div>
  </div>
</div>
@endsection
