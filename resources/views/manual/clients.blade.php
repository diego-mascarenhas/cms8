@extends('layouts/layoutManual')

@section('title', __('Clientes'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Clientes') }}</h4>
        <a href="{{ route('mockups.client-form') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-forms me-1"></i>{{ __('Mockup del formulario') }}
        </a>
    </div>
    <div class="card-body">
        <h5>{{ __('Qué puedes hacer con los clientes') }}</h5>
        <p>{{ __('Los clientes son las empresas o personas a las que facturas o con las que tienes un compromiso comercial. En el módulo de clientes puedes:') }}</p>
        <ul>
            <li>{{ __('Mantener un listado de clientes con sus datos (nombre, CIF/NIF, dirección, contacto principal, etc.).') }}</li>
            <li>{{ __('Crear fichas nuevas, editar las existentes y ver el detalle de cada cliente.') }}</li>
            <li>{{ __('Importar clientes desde un archivo (CSV/Excel).') }}</li>
            <li>{{ __('Vincular clientes a proyectos, facturas y pedidos.') }}</li>
            <li>{{ __('Buscar negocios (por ejemplo con Google Places) para completar la ficha.') }}</li>
        </ul>

        <x-manual.role-compare section="clients" />

        <p class="mb-0">{{ __('Los clientes conectan el trabajo (proyectos, tareas) con la facturación: al crear una factura el admin elige el cliente asociado.') }}</p>
    </div>
</div>
@endsection
