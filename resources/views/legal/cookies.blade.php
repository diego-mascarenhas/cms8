@extends('layouts/blankLayout')

@section('title', 'Cookie Policy')

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
        <h2>Cookie Policy</h2>
        <p>This policy explains how <strong>{{ $configData['templateName'] }}</strong> uses cookies and similar technologies when you use our websites and web application. It should be read together with our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

        <h3>1. What cookies are</h3>
        <p>Cookies are small text files stored on your device when you visit a site. They are widely used to make sites work, keep you signed in, remember preferences, and understand how services are used.</p>

        <h3>2. Cookies we use (typical Laravel / SaaS application)</h3>
        <p>We use cookies that are strictly necessary to operate the service:</p>
        <ul>
          <li><strong>Session and authentication:</strong> to keep you logged in securely, associate requests with your account, and protect forms (for example CSRF protection). These are usually first-party cookies set by our domain.</li>
          <li><strong>Security:</strong> to reduce abuse (for example rate limiting or fraud prevention where implemented).</li>
          <li><strong>Preferences:</strong> where the product stores UI or language choices in a cookie or local storage, only to improve your experience.</li>
        </ul>
        <p>Exact cookie names may change with application updates (for example framework session identifiers).</p>

        <h3>3. Optional / third-party cookies</h3>
        <p>Some pages may load third-party scripts (for example embedded maps, analytics, or support widgets) that can set their own cookies. Those providers have their own policies. If you do not want those cookies, you can use browser controls or extensions to block third-party cookies.</p>

        <h3>4. Legal basis and retention</h3>
        <p>Strictly necessary cookies are used based on our legitimate interest in providing a secure, working service. Session cookies typically expire when you close the browser or after a period of inactivity according to server configuration. See our <a href="{{ route('privacy') }}">Privacy Policy</a> for broader data practices.</p>

        <h3>5. How to control cookies</h3>
        <p>You can block or delete cookies through your browser settings. Blocking strictly necessary cookies may prevent sign-in or break parts of the application.</p>

        <h3>6. Changes</h3>
        <p>We may update this Cookie Policy from time to time. The “Last updated” notion is the date this page was last revised in the product repository; material changes may also be reflected in the Privacy Policy or in-product notices where appropriate.</p>

        <h3>7. Contact</h3>
        <p>Questions: @include('legal.partials.support-link', ['label' => 'Support']).</p>
      </div>
    </div>
  </div>
</div>
@endsection
