@extends('layouts/layoutMaster')

@section('title', __('Service Details'))

@section('content')
@php
    $frequency = (int) ($service->frequency ?? 0);
    $frequencyLabel = match ($frequency) {
        1 => 'Mensual',
        3 => 'Trimestral',
        6 => 'Semestral',
        12 => 'Anual',
        default => $frequency > 0 ? $frequency.' meses' : '',
    };
    $currencyCode = $service->currency?->code ?? ($service->currency?->symbol ?? '');
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Service') }}/</span>
            {{ $service->description ?: __('Service').' #'.$service->id }}
        </h4>
        <p class="text-muted mb-0">{{ $service->client?->name }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
        @can('update', $service)
            <a href="{{ route('service.edit', $service->id) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>{{ __('Edit') }}
            </a>
        @endcan
        @if($service->client)
            <a href="{{ route('client.show', $service->client->id) }}" class="btn btn-outline-primary waves-effect waves-light">
                <i class="ti ti-building me-1"></i>Empresa
            </a>
        @endif
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Service Details') }}</h5>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3 fw-medium">Cliente</div>
            <div class="col-md-9">
                @if($service->client)
                    <a href="{{ route('client.show', $service->client->id) }}" class="text-body">{{ $service->client->name }}</a>
                @endif
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-3 fw-medium">Categoría</div>
            <div class="col-md-9">{{ $service->category?->name }}</div>
        </div>
        <div class="row mb-3">
            <div class="col-md-3 fw-medium">Precio</div>
            <div class="col-md-9">
                @if($service->price !== null && (float) $service->price != 0.0)
                    {{ number_format((float) $service->price, 2) }} {{ $currencyCode }}
                @endif
            </div>
        </div>
        @if((float) ($service->discount ?? 0) > 0)
            <div class="row mb-3">
                <div class="col-md-3 fw-medium">Descuento</div>
                <div class="col-md-9">{{ $service->discount }}%</div>
            </div>
        @endif
        <div class="row mb-3">
            <div class="col-md-3 fw-medium">Frecuencia</div>
            <div class="col-md-9">{{ $frequencyLabel }}</div>
        </div>
        <div class="row mb-3">
            <div class="col-md-3 fw-medium">Próxima</div>
            <div class="col-md-9">{{ $service->next_billing ? $service->next_billing->format('d/m/Y') : '' }}</div>
        </div>
        <div class="row mb-0">
            <div class="col-md-3 fw-medium">Estado</div>
            <div class="col-md-9">{!! $service->status_label !!}</div>
        </div>
    </div>
</div>
@endsection
