@php
$configData = Helper::appClasses();
$isFront = false;
$container = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

@extends('layouts/commonMaster')

@section('layoutContent')
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
      <div class="container-fluid">
        <a href="{{ url('/') }}" class="app-brand-link gap-2">
          <span class="app-brand-logo demo">@include('_partials.macros', ['height' => 20])</span>
          <span class="app-brand-text demo menu-text fw-bold">{{ config('variables.templateName') }}</span>
        </a>
      </div>
    </nav>
    <div class="layout-page">
      <div class="content-wrapper">
        <div class="container-fluid flex-grow-1 py-3">
          @yield('content')
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
