@extends('layouts/blankLayout')

@section('title', 'Service Level Agreement')

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
        <h2>Service Level Agreement (SLA)</h2>
        
        <h3>1. Introduction</h3>
        <p>This Service Level Agreement ("SLA") outlines the levels of service that <strong>{{ $configData['templateName'] }}</strong> aims to provide to its customers. This SLA is an integral part of our commitment to providing a high-quality service.</p>
        
        <h3>2. Definitions</h3>
        <p>
          <strong>Service:</strong> The services provided by <strong>{{ $configData['templateName'] }}</strong> as outlined in the documentation.<br>
          <strong>Uptime:</strong> The time during which the service is available and operational.<br>
          <strong>Downtime:</strong> The time during which the service is unavailable or not operational.
        </p>
        
        <h3>3. Service Commitment</h3>
        <p><strong>{{ $configData['templateName'] }}</strong> is committed to providing at least 99.9% uptime. We will use commercially reasonable efforts to ensure that the service is available 24/7, excluding scheduled maintenance and any downtime caused by circumstances beyond our control.</p>
        
        <h3>4. Scheduled Maintenance</h3>
        <p>Scheduled maintenance will be performed during off-peak hours and will be communicated to customers at least 48 hours in advance. During these periods, the service may be temporarily unavailable.</p>
        
        <h3>5. Support</h3>
        <p>Our support team is available to assist you with any issues or questions you may have. Support requests can be made through our <a href="{{ $configData['support'] }}">Support</a> channel. We aim to respond to all support requests within 24 hours.</p>
        
        <h3>6. Incident Management</h3>
        <p>In the event of a service disruption, our team will promptly work to diagnose and resolve the issue. Affected customers will be notified as soon as possible, and regular updates will be provided until the issue is resolved.</p>
        
        <h3>7. Customer Responsibilities</h3>
        <p>Customers are responsible for maintaining their own internet connection and ensuring that their use of the service complies with our terms and conditions.</p>
        
        <h3>8. Modifications to this SLA</h3>
        <p>We may update this SLA from time to time. We will notify you of any changes by posting the new SLA on this page.</p>
        
        <h3>9. Contact Us</h3>
        <p>If you have any questions about this SLA, please contact us at <a href="{{ $configData['support'] }}">Support</a>.</p>

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
