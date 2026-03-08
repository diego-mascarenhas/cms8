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
                    <h5 class="card-title">{{ __('Crédito aplicado') }}</h5>
                    <p class="card-text text-muted mb-4">
                        {{ __('Los contactos de tus búsquedas de prospectos quedarán disponibles en la plataforma, en la sección Contactos.') }}
                    </p>
                    <a href="{{ route('enterprise.index') }}" class="btn btn-primary">
                        <i class="ti ti-target me-1"></i>{{ __('Buscar clientes') }}
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
