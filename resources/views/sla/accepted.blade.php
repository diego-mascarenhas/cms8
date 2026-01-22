@extends('layouts/layoutMaster')

@section('title', 'SLA Aceptado')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="ti ti-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-3">¡SLA Aceptado Exitosamente!</h3>
                    <p class="text-muted mb-4">
                        Has aceptado el Acuerdo de Nivel de Servicio (SLA) para el producto <strong>{{ $product->name }}</strong>.
                    </p>
                    <p class="text-muted mb-4">
                        La aceptación ha sido registrada y vinculada a tu suscripción. Recibirás una confirmación por email.
                    </p>
                    <div class="alert alert-success">
                        <strong>Fecha de aceptación:</strong> {{ now()->format('d/m/Y H:i') }}
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="ti ti-home me-1"></i>Volver al inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
