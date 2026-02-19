@php
$configData = Helper::appClasses();
$isFront = false;
// Force full-width layout so header banner and content span entire viewport (ignore global contentLayout).
$container = 'container-fluid';
@endphp

@extends('layouts/commonMaster')

@section('layoutContent')
<div class="layout-wrapper layout-navbar-hidden">
  <div class="layout-container">
    <div class="layout-page">
      <div class="content-wrapper">
        <div class="container-fluid flex-grow-1 pt-1 pb-3">
          @yield('content')
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
