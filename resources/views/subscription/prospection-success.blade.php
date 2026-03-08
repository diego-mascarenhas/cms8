@extends('layouts/layoutMaster')

@section('title', __('Pago completado - Prospection'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="text-center mb-5">
        <h1 class="mb-2">{{ __('Pago completado') }}</h1>
        <p class="text-muted">{{ __('Tu compra de Prospection se ha realizado correctamente.') }}</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="ti ti-circle-check text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="card-title">{{ __('Descargar exportación') }}</h5>
                    <p class="card-text text-muted mb-4">
                        {{ __('Puedes descargar el CSV de contactos cuando hayas realizado la búsqueda en la aplicación de Prospection. Si ya realizaste la búsqueda y el pago, usa el enlace inferior para descargar.') }}
                    </p>
                    <a href="{{ $downloadUrl }}" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
                        <i class="ti ti-download me-1"></i>{{ __('Descargar CSV') }}
                    </a>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="{{ route('subscription.index') }}" class="btn btn-label-secondary">
                    {{ __('Volver a planes') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
