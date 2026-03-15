@extends('layouts/layoutManual')

@section('title', __('Clientes'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Clientes') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Qué puedes hacer con los clientes') }}</h5>
                <p>{{ __('Los clientes son las empresas o personas a las que facturas o con las que tienes un compromiso comercial. En el módulo de clientes puedes:') }}</p>
                <ul>
                    <li>{{ __('Mantener un listado de clientes con sus datos (nombre, CIF/NIF, dirección, contacto principal, etc.).') }}</li>
                    <li>{{ __('Crear fichas nuevas, editar las existentes y ver el detalle de cada cliente.') }}</li>
                    <li>{{ __('Importar clientes desde un archivo (CSV/Excel) para cargar muchos de una vez.') }}</li>
                    <li>{{ __('Vincular clientes a proyectos, facturas y pedidos; así sabes siempre para quién es cada trabajo o cada cobro.') }}</li>
                    <li>{{ __('Buscar negocios (por ejemplo con Google Places) y usar el resultado para crear o completar la ficha del cliente con datos de la empresa.') }}</li>
                </ul>

                <p class="mb-0">{{ __('Los clientes son el nexo entre el trabajo (proyectos, tareas) y la facturación: al crear una factura o un proyecto sueles elegir el cliente asociado.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
