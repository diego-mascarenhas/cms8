@extends('layouts/layoutMaster')

@section('title', ($kind ?? \App\Enums\AutomationKind::Action) === \App\Enums\AutomationKind::Funnel ? __('Embudos') : __('Automatizaciones'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
@php
    $kind = $kind ?? \App\Enums\AutomationKind::Action;
    $isFunnel = $kind === \App\Enums\AutomationKind::Funnel;
@endphp
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ $isFunnel ? __('Embudos') : __('Automatizaciones') }}</h4>
        <p class="text-muted">
            {{ $isFunnel
                ? __('Flujos conversacionales: pasos, respuestas esperadas y salidas a automatizaciones')
                : __('Acciones reutilizables (crear cita, contacto, tarea…) que el embudo puede disparar') }}
        </p>
    </div>
    @can('create', \App\Models\Automation::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route($kind->createRouteName()) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>{{ $isFunnel ? __('Crear embudo') : __('Crear automatización') }}
        </a>
    </div>
    @endcan
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
