@extends('layouts/layoutMaster')

@section('title', 'Configuración del negocio')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}" />
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Ajustes/</span> Configuración del negocio</h4>
        <p class="text-muted">Configura los datos de tu negocio paso a paso. Los datos se guardan al cambiar de paso.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i> Volver a Ajustes
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        @livewire('settings.business-config-wizard', ['team' => $team])
    </div>
</div>
@endsection
