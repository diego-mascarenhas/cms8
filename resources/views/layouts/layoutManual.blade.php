@php
$configData = Helper::appClasses();
$isFront = false;
$container = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

@section('layoutContent')

@extends('layouts/commonMaster' )

<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <!-- Manual Sidebar Menu - Full Height (same as Help) -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

      <div class="menu-inner-shadow"></div>

      <!-- Logo/Brand at top of sidebar -->
      <div class="navbar-brand app-brand demo d-flex py-3 px-4 border-bottom">
        <a href="{{ url('/') }}" class="app-brand-link gap-2">
          <span class="app-brand-logo demo">
            @include('_partials.macros', ['height' => 20])
          </span>
          <span class="app-brand-text demo menu-text fw-bold">{{ config('variables.templateName') }}</span>
        </a>
      </div>

      <!-- Manual Navigation Menu -->
      <ul class="menu-inner py-1">
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Manual de usuario</span>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.index') ? 'active' : '' }}">
          <a href="{{ route('manual.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-home"></i>
            <div>Introducción</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.getting-started') ? 'active' : '' }}">
          <a href="{{ route('manual.getting-started') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-rocket"></i>
            <div>Primeros pasos</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('wapify.ayuda') ? 'active' : '' }}">
          <a href="{{ route('wapify.ayuda') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-help"></i>
            <div>{{ __('Guía rápida: venta por WhatsApp') }}</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Operaciones</span>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.dashboard') ? 'active' : '' }}">
          <a href="{{ route('manual.dashboard') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-layout-grid"></i>
            <div>Dashboard y Hoy</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.contacts') ? 'active' : '' }}">
          <a href="{{ route('manual.contacts') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-users"></i>
            <div>Contactos</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.clients') ? 'active' : '' }}">
          <a href="{{ route('manual.clients') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-user-heart"></i>
            <div>Clientes</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.collaborators') ? 'active' : '' }}">
          <a href="{{ route('manual.collaborators') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-users-group"></i>
            <div>Colaboradores</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.services') ? 'active' : '' }}">
          <a href="{{ route('manual.services') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-rocket"></i>
            <div>Servicios</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.projects') ? 'active' : '' }}">
          <a href="{{ route('manual.projects') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-folder"></i>
            <div>Proyectos</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.tasks') ? 'active' : '' }}">
          <a href="{{ route('manual.tasks') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-layout-kanban"></i>
            <div>Tareas y tiempo</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.chat') ? 'active' : '' }}">
          <a href="{{ route('manual.chat') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-message-chatbot"></i>
            <div>Chat y WhatsApp</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Comercio y facturación</span>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.products-and-orders') ? 'active' : '' }}">
          <a href="{{ route('manual.products-and-orders') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-package"></i>
            <div>Productos y pedidos</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.billing') ? 'active' : '' }}">
          <a href="{{ route('manual.billing') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-file-invoice"></i>
            <div>Facturas y pagos</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Campañas y equipo</span>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.campaigns') ? 'active' : '' }}">
          <a href="{{ route('manual.campaigns') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-send"></i>
            <div>Mensajes y plantillas</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.team') ? 'active' : '' }}">
          <a href="{{ route('manual.team') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-users"></i>
            <div>Equipo</div>
          </a>
        </li>

        <li class="menu-item {{ request()->routeIs('manual.more-features') ? 'active' : '' }}">
          <a href="{{ route('manual.more-features') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-apps"></i>
            <div>Más funciones</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase mt-2">
          <span class="menu-header-text">Técnico</span>
        </li>

        <li class="menu-item">
          <a href="{{ route('help.index') }}" class="menu-link">
            <i class="menu-icon tf-icons ti ti-help-circle"></i>
            <div>Ayuda y API</div>
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
