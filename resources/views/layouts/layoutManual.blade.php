@php
$configData = Helper::appClasses();
$isFront = false;
$includeSharePreview = true;
$container = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
$isWapifyAyuda = request()->routeIs('wapify.ayuda');
$manualSections = \App\Http\Controllers\ManualController::guideSections();
$mockupSections = \App\Support\ManualDocumentation::mockups();
@endphp

@section('layoutContent')

@extends('layouts/commonMaster' )

<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

      <div class="menu-inner-shadow"></div>

      <div class="navbar-brand app-brand demo d-flex py-3 px-4 border-bottom">
        <a href="{{ url('/') }}" class="app-brand-link">
          <span class="app-brand-logo demo app-brand-img">
            <img
              src="{{ Helper::logoAssetForStyle($configData['style'] ?? 'light') }}"
              data-app-light-img="{{ Helper::logoThemeDataImg('light') }}"
              data-app-dark-img="{{ Helper::logoThemeDataImg('dark') }}"
              alt="{{ config('app.name') }}"
              style="height: 44px; width: auto;"
            >
          </span>
        </a>
      </div>

      <ul class="menu-inner py-1">
        @if ($isWapifyAyuda)
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('Guía rápida') }}</span>
          </li>

          <li class="menu-item manual-menu-item">
            <a href="{{ route('wapify.ayuda') }}#configuracion-negocio" class="menu-link manual-menu-link" data-section="configuracion-negocio">
              <i class="menu-icon tf-icons ti ti-settings"></i>
              <div>1. {{ __('Configuración del negocio') }}</div>
            </a>
          </li>
          <li class="menu-item manual-menu-item">
            <a href="{{ route('wapify.ayuda') }}#carga-productos" class="menu-link manual-menu-link" data-section="carga-productos">
              <i class="menu-icon tf-icons ti ti-package"></i>
              <div>2. {{ __('Carga de productos') }}</div>
            </a>
          </li>
          <li class="menu-item manual-menu-item">
            <a href="{{ route('wapify.ayuda') }}#escaneo-qr" class="menu-link manual-menu-link" data-section="escaneo-qr">
              <i class="menu-icon tf-icons ti ti-qrcode"></i>
              <div>3. {{ __('Escaneo de QR') }}</div>
            </a>
          </li>
          <li class="menu-item manual-menu-item">
            <a href="{{ route('wapify.ayuda') }}#pedidos" class="menu-link manual-menu-link" data-section="pedidos">
              <i class="menu-icon tf-icons ti ti-shopping-cart"></i>
              <div>4. {{ __('Pedidos') }}</div>
            </a>
          </li>
          <li class="menu-item manual-menu-item">
            <a href="{{ route('wapify.ayuda') }}#ordenes" class="menu-link manual-menu-link" data-section="ordenes">
              <i class="menu-icon tf-icons ti ti-list-check"></i>
              <div>5. {{ __('Ordenes') }}</div>
            </a>
          </li>

          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('Más documentación') }}</span>
          </li>
          <li class="menu-item">
            <a href="{{ route('manual.index') }}" class="menu-link">
              <i class="menu-icon tf-icons ti ti-book"></i>
              <div>{{ __('Manual completo') }}</div>
            </a>
          </li>
        @else
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('Manual de usuario') }}</span>
          </li>

          <li class="menu-item {{ request()->routeIs('manual.index') ? 'active' : '' }}">
            <a href="{{ route('manual.index') }}" class="menu-link">
              <i class="menu-icon tf-icons ti ti-home"></i>
              <div>{{ __('Inicio') }}</div>
            </a>
          </li>

          @foreach ($manualSections as $section)
            <li class="menu-item {{ request()->routeIs($section['route']) ? 'active' : '' }}">
              <a href="{{ route($section['route']) }}" class="menu-link">
                <i class="menu-icon tf-icons ti {{ $section['icon'] }}"></i>
                <div>{{ $section['title'] }}</div>
              </a>
            </li>
          @endforeach

          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('Mockups') }}</span>
          </li>

          <li class="menu-item {{ request()->routeIs('mockups.index') ? 'active' : '' }}">
            <a href="{{ route('mockups.index') }}" class="menu-link">
              <i class="menu-icon tf-icons ti ti-layout-board"></i>
              <div>{{ __('Catálogo') }}</div>
            </a>
          </li>

          @foreach ($mockupSections as $mockup)
            <li class="menu-item {{ request()->routeIs($mockup['route']) ? 'active' : '' }}">
              <a href="{{ route($mockup['route']) }}" class="menu-link">
                <i class="menu-icon tf-icons ti {{ $mockup['icon'] }}"></i>
                <div>{{ __($mockup['title']) }}</div>
              </a>
            </li>
          @endforeach

          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('Enlaces') }}</span>
          </li>
          <li class="menu-item">
            <a href="{{ route('help.index') }}" class="menu-link">
              <i class="menu-icon tf-icons ti ti-help"></i>
              <div>{{ __('Ayuda técnica') }}</div>
            </a>
          </li>
        @endif
      </ul>
    </aside>

    <div class="layout-page">
      <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="container-fluid d-flex align-items-center justify-content-between py-2">
          <button type="button" class="layout-menu-toggle btn btn-icon btn-text-secondary rounded-pill d-xl-none">
            <i class="ti ti-menu-2 ti-md"></i>
          </button>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-primary">Admin</span>
            <span class="badge bg-label-secondary">Collaborator</span>
            <span class="badge bg-label-success">Client</span>
          </div>
        </div>
      </nav>

      <div class="content-wrapper">
        <div class="container-fluid flex-grow-1 py-3">
          @yield('content')
        </div>
        <div class="content-backdrop fade"></div>
      </div>
    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>
  <div class="drag-target"></div>
</div>

@if ($isWapifyAyuda)
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
@endif

@endsection
