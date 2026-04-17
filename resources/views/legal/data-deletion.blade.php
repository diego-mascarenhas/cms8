@extends('layouts/blankLayout')

@section('title', 'Data deletion & disconnect')

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
        <h2>Data deletion &amp; disconnecting Google</h2>
        <p>This page explains how to stop Google data access and how to request removal of related data in <strong>{{ $configData['templateName'] }}</strong>.</p>

        <h3>Disconnect Google (in the product)</h3>
        <ol>
          <li>Sign in to <strong>{{ $configData['templateName'] }}</strong>.</li>
          <li>Open your team <strong>Settings</strong> (team configuration area).</li>
          <li>Find the <strong>Google People &amp; Calendar</strong> card.</li>
          <li>Use <strong>Disconnect</strong> (or equivalent) for the connected Google account.</li>
        </ol>
        <p>Disconnecting revokes the application’s ability to obtain new Google data using the stored OAuth tokens and removes that stored connection from our side as part of that flow.</p>

        <h3>Revoke access in your Google Account</h3>
        <p>You can also remove the app’s access at any time in your Google Account under <strong>Security → Third-party access</strong> (wording may vary by Google UI). That revokes tokens on Google’s side; you should still disconnect in the product so our records stay consistent.</p>

        <h3>Synced contacts and calendar data</h3>
        <p>After disconnect, previously synced records may remain in your team workspace (for example as normal contact or calendar records) until your team administrator deletes or archives them according to your internal policies. If you need all copies removed, contact your team admin or @include('legal.partials.support-link', ['label' => 'Support']) with your team name and the Google account email you used.</p>

        <h3>Account-wide deletion</h3>
        <p>For deletion of your entire user account or all personal data held by the service provider, contact @include('legal.partials.support-link', ['label' => 'Support']). We will respond subject to our policies and applicable law.</p>

        <h3>Related links</h3>
        <ul>
          <li><a href="{{ route('legal.google-connection') }}">Optional Google connection</a></li>
          <li><a href="{{ route('legal.google-user-data') }}">Google user data &amp; Limited Use</a></li>
          <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
          <li><a href="{{ route('legal.application') }}">Application overview</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
