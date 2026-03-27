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
        <a href="{{ url('/') }}" class="app-brand-link">
          <span class="app-brand-logo demo app-brand-img">
            <img src="{{ Helper::logoAsset('dark') }}" alt="Wapify" style="height: 44px; width: auto;">
          </span>
        </a>
      </div>

      <!-- Manual Navigation Menu -->
      <ul class="menu-inner py-1">
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Manual de usuario</span>
        </li>

        <li class="menu-item manual-menu-item">
          <a href="{{ route('wapify.ayuda') }}#configuracion-negocio" class="menu-link manual-menu-link" data-section="configuracion-negocio">
            <i class="menu-icon tf-icons ti ti-settings"></i>
            <div>1. Configuración del negocio</div>
          </a>
        </li>

        <li class="menu-item manual-menu-item">
          <a href="{{ route('wapify.ayuda') }}#carga-productos" class="menu-link manual-menu-link" data-section="carga-productos">
            <i class="menu-icon tf-icons ti ti-package"></i>
            <div>2. Carga de productos</div>
          </a>
        </li>

        <li class="menu-item manual-menu-item">
          <a href="{{ route('wapify.ayuda') }}#escaneo-qr" class="menu-link manual-menu-link" data-section="escaneo-qr">
            <i class="menu-icon tf-icons ti ti-qrcode"></i>
            <div>3. Escaneo de QR</div>
          </a>
        </li>

        <li class="menu-item manual-menu-item">
          <a href="{{ route('wapify.ayuda') }}#pedidos" class="menu-link manual-menu-link" data-section="pedidos">
            <i class="menu-icon tf-icons ti ti-shopping-cart"></i>
            <div>4. Pedidos</div>
          </a>
        </li>

        <li class="menu-item manual-menu-item">
          <a href="{{ route('wapify.ayuda') }}#ordenes" class="menu-link manual-menu-link" data-section="ordenes">
            <i class="menu-icon tf-icons ti ti-list-check"></i>
            <div>5. Ordenes</div>
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

@push('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function ()
  {
    var links = document.querySelectorAll('.manual-menu-link');
    if (!links.length)
    {
      return;
    }

    var setActiveSection = function ()
    {
      var hash = window.location.hash ? window.location.hash.replace('#', '') : 'configuracion-negocio';

      links.forEach(function (link)
      {
        var listItem = link.closest('.manual-menu-item');
        if (!listItem)
        {
          return;
        }

        if (link.dataset.section === hash)
        {
          listItem.classList.add('active');
        }
        else
        {
          listItem.classList.remove('active');
        }
      });
    };

    links.forEach(function (link)
    {
      link.addEventListener('click', function ()
      {
        window.requestAnimationFrame(setActiveSection);
      });
    });

    window.addEventListener('hashchange', setActiveSection);
    setActiveSection();
  });
</script>
@endpush

@endsection
