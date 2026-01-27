@php
$configData = Helper::appClasses();
$isFront = false;
@endphp

@section('layoutContent')

@extends('layouts/commonMaster' )

<!-- Minimal Header with Logo Only -->
<header class="bg-navbar-theme border-bottom">
    <div class="container-fluid">
        <div class="navbar navbar-expand-lg px-3 px-md-4 py-2">
            <div class="navbar-brand app-brand demo d-flex py-0 me-4">
                <a href="{{ url('/') }}" class="app-brand-link gap-2">
                    <span class="app-brand-logo demo">
                        @include('_partials.macros', ['height' => 20])
                    </span>
                    <span class="app-brand-text demo menu-text fw-bold">{{ config('variables.templateName') }}</span>
                </a>
            </div>
        </div>
    </div>
</header>

<div class="layout-wrapper layout-content-navbar" style="margin-top: 0;">
  <div class="layout-container">

    <!-- Documentation Sidebar Menu -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

      <div class="menu-inner-shadow"></div>

      <!-- Help Navigation Menu -->
      <ul class="menu-inner py-1">
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">{{ __('Documentation') }}</span>
        </li>

        <li class="menu-item {{ request()->routeIs('help.index') ? 'active' : '' }}">
          <a href="{{ route('help.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-home"></i>
            <div>{{ __('Introduction') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.usage') ? 'active' : '' }}">
          <a href="{{ route('help.usage') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-book"></i>
            <div>{{ __('How to Use') }}</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">{{ __('Modules') }}</span>
        </li>

        <li class="menu-item {{ request()->routeIs('help.contacts') ? 'active' : '' }}">
          <a href="{{ route('help.contacts') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-users"></i>
            <div>{{ __('Contact Management') }}</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">{{ __('API Documentation') }}</span>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api') ? 'active' : '' }}">
          <a href="{{ route('help.api') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-api"></i>
            <div>{{ __('API Overview') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.authentication') ? 'active' : '' }}">
          <a href="{{ route('help.api.authentication') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-key"></i>
            <div>{{ __('Authentication') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.contacts') ? 'active' : '' }}">
          <a href="{{ route('help.api.contacts') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-users"></i>
            <div>{{ __('Contacts') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.contents') ? 'active' : '' }}">
          <a href="{{ route('help.api.contents') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-file-text"></i>
            <div>{{ __('Contents') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.enterprises') ? 'active' : '' }}">
          <a href="{{ route('help.api.enterprises') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-building"></i>
            <div>{{ __('Enterprises') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.payments') ? 'active' : '' }}">
          <a href="{{ route('help.api.payments') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-credit-card"></i>
            <div>{{ __('Payments') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.products') ? 'active' : '' }}">
          <a href="{{ route('help.api.products') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-package"></i>
            <div>{{ __('Products') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.orders') ? 'active' : '' }}">
          <a href="{{ route('help.api.orders') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-shopping-bag"></i>
            <div>{{ __('Orders') }}</div>
          </a>
        </li>
      </ul>
    </aside>

    <!-- Layout page -->
    <div class="layout-page">

      <!-- Content wrapper -->
      <div class="content-wrapper">

        <!-- Content -->
        <div class="container-fluid flex-grow-1 py-3">
          @yield('content')
        </div>
        <!-- / Content -->

        <div class="content-backdrop fade"></div>
      </div>
      <!-- / Content wrapper -->
    </div>
    <!-- / Layout page -->
  </div>

  <!-- Overlay -->
  <div class="layout-overlay layout-menu-toggle"></div>

  <!-- Drag Target Area To SlideIn Menu On Small Screens -->
  <div class="drag-target"></div>
</div>
<!-- / Layout wrapper -->

@endsection