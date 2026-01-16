@php
$configData = Helper::appClasses();
$isFront = false;
$container = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

@section('layoutContent')

@extends('layouts/commonMaster' )

<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

        <!-- Layout page -->
        <div class="layout-page">

            <!-- Minimal Header with Logo Only -->
            <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                <div class="container-fluid">
                    <div class="navbar-brand app-brand demo d-flex py-0 py-lg-2 me-4">
                        <a href="{{ url('/') }}" class="app-brand-link gap-2">
                            <span class="app-brand-logo demo">
                                @include('_partials.macros', ['height' => 20])
                            </span>
                            <span class="app-brand-text demo menu-text fw-bold">{{ config('variables.templateName') }}</span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Content wrapper -->
            <div class="content-wrapper">

                <!-- Content -->
                <div class="{{ $container }} flex-grow-1 container-p-y">
                    @yield('content')
                </div>
                <!-- / Content -->

                <div class="content-backdrop fade"></div>
            </div>
            <!-- / Content wrapper -->
        </div>
        <!-- / Layout page -->
    </div>
</div>
<!-- / Layout wrapper -->

@endsection