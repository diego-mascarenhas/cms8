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
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-4">
                            <h6 class="text-muted mb-1">{{ __('Nombre') }}</h6>
                            <p class="mb-0">{{ $store->name }}</p>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <h6 class="text-muted mb-1">{{ __('Código') }}</h6>
                            <p class="mb-0">{{ $store->code ?? '-' }}</p>
                        </div>
                        <div class="col-sm-12 col-lg-4">
                            <h6 class="text-muted mb-1">{{ __('Dirección') }}</h6>
                            <p class="mb-0">{{ $store->address ?? '-' }}</p>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <h6 class="text-muted mb-1">{{ __('Estado') }}</h6>
                            <p class="mb-0">
                                <span class="badge {{ $store->status ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ $store->status ? __('Activa') : __('Inactiva') }}
                                </span>
                            </p>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <h6 class="text-muted mb-1">{{ __('Principal') }}</h6>
                            <p class="mb-0">
                                <span class="badge {{ $store->is_main ? 'bg-label-primary' : 'bg-label-secondary' }}">
                                    {{ $store->is_main ? __('Sí') : __('No') }}
                                </span>
                            </p>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <h6 class="text-muted mb-1">{{ __('Creada') }}</h6>
                            <p class="mb-0">{{ optional($store->created_at)->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <h6 class="text-muted mb-1">{{ __('Actualizada') }}</h6>
                            <p class="mb-0">{{ optional($store->updated_at)->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $paymentKeys = $store->enabledCheckoutPaymentMethods();
                $fulfillmentKeys = $store->enabledCheckoutFulfillmentTypes();
                $paymentLabels = \App\Models\Store::checkoutPaymentMethodLabels();
                $fulfillmentLabels = \App\Models\Store::checkoutFulfillmentLabels();
            @endphp
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Ventas por WhatsApp / tienda') }}</h5>
                    <p class="card-subtitle text-muted small mb-0 mt-1">{{ __('Medios de pago y formas de entrega que ofrece esta sucursal a los clientes.') }}</p>
                </div>
                <div class="card-body">
                    <h6 class="text-muted mb-2">{{ __('Medios de pago') }}</h6>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @forelse ($paymentKeys as $key)
                            <span class="badge bg-label-primary">{{ $paymentLabels[$key] ?? $key }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </div>
                    <h6 class="text-muted mb-2">{{ __('Formas de entrega') }}</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse ($fulfillmentKeys as $key)
                            <span class="badge bg-label-info">{{ $fulfillmentLabels[$key] ?? $key }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
