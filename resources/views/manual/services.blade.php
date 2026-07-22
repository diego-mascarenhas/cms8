@extends('layouts/layoutManual')

@section('title', __('Servicios'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Servicios') }}</h4>
        <a href="{{ route('mockups.service-form') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-forms me-1"></i>{{ __('Mockup del formulario') }}
        </a>
    </div>
    <div class="card-body">
        <h5>{{ __('Catálogo de servicios') }}</h5>
        <p>{{ __('Los servicios son lo que ofreces (diseño, desarrollo, consultoría…). Se usan al armar proyectos y presupuestos.') }}</p>
        <ul>
            <li>{{ __('Crear y editar servicios con nombre, categoría, responsable y descripción.') }}</li>
            <li>{{ __('Incluirlos en proyectos con cantidades y precios (precios visibles sobre todo para Admin).') }}</li>
        </ul>

        <x-manual.role-compare section="services" />
    </div>
</div>
@endsection
