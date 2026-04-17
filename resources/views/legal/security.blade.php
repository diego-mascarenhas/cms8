@extends('layouts/blankLayout')

@section('title', 'Security Policy')

@section('page-style')
  {{-- Page Css files --}}
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
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
        @include('legal.partials.document-nav')
        <h2>Security Policy</h2>
        
        <h3>1. Overview</h3>
        <p>Welcome to the Security Policy for <strong>{{ $configData['templateName'] }}</strong>. This document outlines the security measures, guidelines, and policies implemented by <strong>{{ $configData['creatorName'] }}</strong> to protect the integrity, availability, and confidentiality of our software and user data.</p>
        
        <h3>2. Policy Statement</h3>
        <p><strong>{{ $configData['templateName'] }}</strong> is committed to maintaining a secure environment for our users. We continuously monitor and improve our security practices to safeguard against threats and vulnerabilities.</p>
        
        <h3>3. Security Measures</h3>
        <h4>3.1. Data Encryption</h4>
        <p>All data transmitted between users and our servers is encrypted using <strong>SSL/TLS</strong>. Sensitive data stored in our databases is encrypted using industry-standard encryption algorithms.</p>
        
        <h4>3.2. Access Control</h4>
        <p>Access to the source code repository (<a href="{{ $configData['repository'] }}">{{ $configData['repository'] }}</a>) is restricted to authorized personnel only. Authentication mechanisms are in place to ensure that only authorized users can access sensitive functionalities.</p>
        
        <h4>3.3. Regular Updates</h4>
        <p><strong>{{ $configData['templateName'] }}</strong> is regularly updated to address potential security vulnerabilities. The latest version ({{ $configData['templateVersion'] }}) is always available at our <a href="{{ $configData['changelog'] }}">Changelog</a> page.</p>
        
        <h4>3.4. Security Audits</h4>
        <p>Regular security audits are conducted to identify and mitigate potential risks. Third-party security tools are used to scan for vulnerabilities and ensure compliance with security standards.</p>
        
        <h3>4. Incident Response</h3>
        <h4>4.1. Reporting Security Issues</h4>
        <p>Users can report security vulnerabilities by contacting us at @include('legal.partials.support-link', ['label' => 'Support']). We encourage responsible disclosure and will respond to security reports within 48 hours.</p>
        
        <h4>4.2. Incident Management</h4>
        <p>In the event of a security incident, our dedicated team will follow a predefined incident response plan to contain, mitigate, and resolve the issue. Affected users will be notified promptly, and remediation steps will be provided.</p>
        
        <h3>5. User Responsibilities</h3>
        <h4>5.1. Account Security</h4>
        <p>Users are responsible for maintaining the security of their account credentials. Strong, unique passwords are recommended, and users should update their passwords regularly.</p>
        
        <h4>5.2. Reporting Issues</h4>
        <p>Users should promptly report any suspicious activities or potential security issues to our support team.</p>
        
        <h3>6. Compliance</h3>
        <p><strong>{{ $configData['templateName'] }}</strong> complies with all applicable data protection laws and regulations. Our security practices are aligned with industry standards to ensure the highest level of protection for our users.</p>
        
        <h3>7. Additional Resources</h3>
        <ul>
          <li><a href="{{ $configData['livePreview'] }}">Live Preview</a></li>
          <li><a href="{{ $configData['productPage'] }}">Product Page</a></li>
          <li><a href="{{ $configData['documentation'] }}">Documentation</a></li>
          <li><a href="{{ $configData['repository'] }}">Repository</a></li>
          <li><a href="{{ route('legal.license') }}">License (GNU AGPL v3)</a></li>
        </ul>
        
        <h3>8. Contact Information</h3>
        <p>For any security-related questions or concerns, please reach out to us at @include('legal.partials.support-link', ['label' => 'Support']) or visit our <a href="{{ $configData['creatorUrl'] }}">LinkedIn</a>.</p>
        
        <p>Thank you for using <strong>{{ $configData['templateName'] }}</strong>. Your security is our priority.</p>
      </div>
    </div>
  </div>
</div>
@endsection
