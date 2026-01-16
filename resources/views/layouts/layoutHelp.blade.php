@php
$configData = Helper::appClasses();
$isFront = true;
@endphp

@section('layoutContent')

@extends('layouts/commonMaster' )

<!-- Minimal Header with Logo Only -->
<header class="bg-navbar-theme border-bottom">
    <div class="container-fluid">
        <div class="navbar navbar-expand-lg px-3 px-md-4 py-3">
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

<!-- Main Content -->
<main class="flex-grow-1 bg-light">
    @yield('content')
</main>

@endsection