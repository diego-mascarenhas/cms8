@extends('layouts/blankLayout')

@section('title', 'License (GNU AGPL v3)')

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
        <h2>License (GNU AGPL v3)</h2>
        <p>The <strong>{{ $configData['templateName'] }}</strong> software (source code and the work as licensed) is free software released under the <strong>GNU Affero General Public License, version 3</strong> (AGPL-3.0), or, at your option, any later version published by the Free Software Foundation. The binding text is only the document published by the FSF: <a href="{{ $configData['licenseUrl'] }}" rel="noopener noreferrer" target="_blank">Full license text (FSF)</a>.</p>
        <p>The only binding legal terms for the licensed program are in that license document. This page is a short orientation for readers and reviewers; it is <strong>not</strong> a substitute for the AGPL text.</p>

        <h3>Official full text</h3>
        <p>Same document on gnu.org: <a href="{{ $configData['licenseUrl'] }}" rel="noopener noreferrer" target="_blank">www.gnu.org/licenses/agpl-3.0.html</a></p>

        <h3>What AGPL-3 means in brief</h3>
        <p class="text-muted small">Summary only, not legal advice.</p>
        <ul>
          <li>AGPL-3 is a <strong>strong copyleft</strong> license: modified versions of the program generally must remain under the same license when you convey them, subject to the exact conditions in the license text.</li>
          <li>The <strong>Affero</strong> variant adds obligations relevant when users interact with the program over a <strong>network</strong>; you must follow the license’s requirements for “remote network interaction” and related source-offering rules as stated in AGPL-3.</li>
          <li>If you combine AGPL-covered code with other code, the AGPL may affect the combined work as described in the license; read the definitions of “Corresponding Source” and “covered work” in the official text.</li>
        </ul>

        <h3>Source code</h3>
        <p>Project repository: <a href="{{ $configData['repository'] }}" rel="noopener noreferrer" target="_blank">{{ $configData['repository'] }}</a></p>

        <h3>Relationship to other policies</h3>
        <p>Our <a href="{{ route('terms') }}">Terms and Conditions</a> refer to this license for the software. Other materials (branding, documentation not marked as AGPL-covered, etc.) may be subject to separate rights as stated in the Terms.</p>

        <h3>Related</h3>
        <ul>
          <li><a href="{{ route('legal.index') }}">All legal documents</a></li>
          <li><a href="{{ route('legal.application') }}">Application overview</a></li>
          <li><a href="{{ route('legal.google-connection') }}">Optional Google connection</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
