@extends('layouts/layoutMaster')

@section('title', __('Detalle de tienda'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Tiendas') }}/</span> {{ $store->name }}</h4>
            <p class="text-muted">{{ __('Información de la sucursal') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            <a href="{{ route('store.edit', $store->id) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>{{ __('Editar tienda') }}
            </a>
            <a href="{{ route('store.index') }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i>{{ __('Volver al listado') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Datos de la tienda') }}</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <span class="fw-medium me-1">{{ __('Nombre') }}:</span>
                            <span>{{ $store->name }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">{{ __('Código') }}:</span>
                            <span>{{ $store->code ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">{{ __('Dirección') }}:</span>
                            <span>{{ $store->address ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">{{ __('Estado') }}:</span>
                            <span class="badge {{ $store->status ? 'bg-label-success' : 'bg-label-secondary' }}">
                                {{ $store->status ? __('Activa') : __('Inactiva') }}
                            </span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">{{ __('Principal') }}:</span>
                            <span class="badge {{ $store->is_main ? 'bg-label-primary' : 'bg-label-secondary' }}">
                                {{ $store->is_main ? __('Sí') : __('No') }}
                            </span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">{{ __('Creada') }}:</span>
                            <span>{{ optional($store->created_at)->format('d/m/Y H:i') }}</span>
                        </li>
                        <li class="mb-0">
                            <span class="fw-medium me-1">{{ __('Actualizada') }}:</span>
                            <span>{{ optional($store->updated_at)->format('d/m/Y H:i') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
