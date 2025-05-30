@extends('layouts/layoutMaster')

@section('title', 'Detalle de Tarifa')

@section('content')
<h4 class="fw-bold py-3 mb-4">
    <span class="text-muted fw-light">Tarifas /</span> Detalle
</h4>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Información de Tarifa</h5>
                <div>
                    <a href="{{ route('customer-fare.edit', $customerFare->id) }}" class="btn btn-primary me-2">
                        <i class="ti ti-pencil me-1"></i>Editar
                    </a>
                    <a href="{{ route('customer-fare.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6 class="fw-semibold">Colaborador:</h6>
                            <p>{{ $customerFare->customer->name ?? 'N/A' }}</p>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-semibold">Tipo de Tarifa:</h6>
                            <p>{{ $customerFare->fare->name ?? 'N/A' }}</p>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-semibold">Bloque:</h6>
                            <p>{{ $customerFare->fare->block->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6 class="fw-semibold">Idioma Origen:</h6>
                            <p>{{ $customerFare->languageOrigin->name ?? 'N/A' }}</p>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-semibold">Idioma Destino:</h6>
                            <p>{{ $customerFare->languageDestination->name ?? 'N/A' }}</p>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-semibold">Precio:</h6>
                            <p>
                                {{ $customerFare->formatted_amount }} 
                                / {{ $customerFare->fare->unit->type ?? 'N/A' }}
                                @if($customerFare->negotiable)
                                    <span class="badge bg-label-warning ms-1">Negociable</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 