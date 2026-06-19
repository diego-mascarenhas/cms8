@php
$configData = Helper::appClasses();
$isFront = false;
$includeSharePreview = true;
$container = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

@section('layoutContent')

@extends('layouts/commonMaster' )

<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <!-- Documentation Sidebar Menu - Full Height -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

      <div class="menu-inner-shadow"></div>

      <!-- Logo/Brand at top of sidebar -->
      <div class="navbar-brand app-brand demo d-flex py-3 px-4 border-bottom">
        <a href="{{ url('/') }}" class="app-brand-link">
          <span class="app-brand-logo demo app-brand-img">
            <img src="{{ Helper::logoAsset('dark') }}" alt="Wapify" style="height: 44px; width: auto;">
          </span>
        </a>
      </div>

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

        <li class="menu-item {{ request()->routeIs('help.onboarding') ? 'active' : '' }}">
          <a href="{{ route('help.onboarding') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-rocket"></i>
            <div>{{ __('help_onboarding.sidebar_title') }}</div>
          </a>
        </li>

        <li class="menu-item">
          <a href="{{ route('manual.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-book"></i>
            <div>{{ __('User Manual') }}</div>
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

        <li class="menu-item {{ request()->routeIs('help.chat-assistant') ? 'active' : '' }}">
          <a href="{{ route('help.chat-assistant') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-message-chatbot"></i>
            <div>{{ __('Chat and Assistant') }}</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">{{ __('Variables de Entorno') }}</span>
        </li>

        <li class="menu-item {{ request()->routeIs('help.environment-variables') && !request()->routeIs('help.environment-variables.google-analytics') ? 'active' : '' }}">
          <a href="{{ route('help.environment-variables') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-settings"></i>
            <div>{{ __('Configuraciones') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.environment-variables.google-analytics') ? 'active' : '' }}">
          <a href="{{ route('help.environment-variables.google-analytics') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-chart-line"></i>
            <div>{{ __('Google Analytics') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.woocommerce-configuration') ? 'active' : '' }}">
          <a href="{{ route('help.woocommerce-configuration') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-brand-wordpress"></i>
            <div>{{ __('WooCommerce') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.wordpress-mcp-cursor') ? 'active' : '' }}">
          <a href="{{ route('help.wordpress-mcp-cursor') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-plug-connected"></i>
            <div>{{ __('WordPress MCP in Cursor') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.plugins') ? 'active' : '' }}">
          <a href="{{ route('help.plugins') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-puzzle"></i>
            <div>{{ __('help_plugins.sidebar_title') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.stripe-webhook') ? 'active' : '' }}">
          <a href="{{ route('help.stripe-webhook') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-brand-stripe"></i>
            <div>{{ __('Stripe webhooks') }}</div>
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

        <li class="menu-item {{ request()->routeIs('help.api.posts') ? 'active' : '' }}">
          <a href="{{ route('help.api.posts') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-file-text"></i>
            <div>{{ __('Posts') }}</div>
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

        <li class="menu-item {{ request()->routeIs('help.api.tasks') ? 'active' : '' }}">
          <a href="{{ route('help.api.tasks') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-list-check"></i>
            <div>{{ __('Tasks') }}</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('help.api.prompts') ? 'active' : '' }}">
          <a href="{{ route('help.api.prompts') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-sparkles"></i>
            <div>{{ __('Prompts') }}</div>
          </a>
        </li>
      </ul>
    </aside>

    <!-- Layout page -->
    <div class="layout-page">

      <!-- Minimal Header - Logo moved to sidebar -->
      <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="container-fluid">
          <!-- Logo moved to sidebar -->
        </div>
      </nav>

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
