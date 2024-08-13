@extends('layouts/blankLayout')

@section('title', 'Privacy Policy')

@section('page-style')
  {{-- Page Css files --}}
  <link rel="stylesheet" href="{{ asset(mix('assets/vendor/css/pages/page-auth.css')) }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-basic px-4">
  <div class="authentication-inner py-4">
    <!-- Logo -->
    <div class="app-brand mb-4">
      <a href="{{ url('/') }}" class="app-brand-link gap-2">
        <span class="app-brand-logo demo">@include('_partials.macros', ["height" => 20, "withbg" => 'fill: #fff;'])</span>
      </a>
    </div>
    <!-- /Logo -->
    <div class="card">
      <div class="card-body">
        <h2>Privacy Policy</h2>
        <p>This privacy policy explains how <strong>{{ $configData['templateName'] }}</strong> collects, uses, and protects your information when you use our service.</p>
        <!-- Your privacy policy content here -->
        
        <h3>1. Information We Collect</h3>
        <p>We collect various types of information in connection with the services we provide, including:</p>
        <ul>
          <li>Personal identification information (Name, email address, phone number, etc.)</li>
          <li>Technical data (IP address, browser type, and version, time zone setting, etc.)</li>
        </ul>
        
        <h3>2. How We Use Your Information</h3>
        <p>We use the collected data for various purposes:</p>
        <ul>
          <li>To provide and maintain our service</li>
          <li>To notify you about changes to our service</li>
          <li>To provide customer support</li>
        </ul>
        
        <h3>3. Data Protection</h3>
        <p>We implement a variety of security measures to maintain the safety of your personal information when you enter, submit, or access your personal information.</p>
        
        <h3>4. Changes to This Privacy Policy</h3>
        <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page.</p>
        
        <h3>5. Contact Us</h3>
        <p>If you have any questions about this Privacy Policy, please contact us at <a href="{{ $configData['support'] }}">Support</a>.</p>

        <h3>Follow Us</h3>
        <ul>
          @if (!empty($configData['facebookUrl']))
            <li><a href="{{ $configData['facebookUrl'] }}">Facebook</a></li>
          @endif
          @if (!empty($configData['twitterUrl']))
            <li><a href="{{ $configData['twitterUrl'] }}">Twitter</a></li>
          @endif
          @if (!empty($configData['githubUrl']))
            <li><a href="{{ $configData['githubUrl'] }}">GitHub</a></li>
          @endif
          @if (!empty($configData['dribbbleUrl']))
            <li><a href="{{ $configData['dribbbleUrl'] }}">Dribbble</a></li>
          @endif
          @if (!empty($configData['instagramUrl']))
            <li><a href="{{ $configData['instagramUrl'] }}">Instagram</a></li>
          @endif
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
