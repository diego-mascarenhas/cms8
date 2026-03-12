@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('Crear tu negocio'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-misc.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
@endsection

@section('content')
<div class="container-xxl container-p-y">
    <div class="mb-3">
        <h2 class="mb-1">{{ __('Crear tu negocio') }}</h2>
        <p class="text-muted mb-0">
            {{ __('Configura los datos de tu negocio en pocos pasos: datos básicos, información personal, dirección, redes sociales y revisión.') }}
        </p>
    </div>
    @livewire('landing.business-wizard', ['token' => $token ?? null])
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endpush
