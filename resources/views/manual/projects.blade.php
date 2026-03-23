@extends('layouts/layoutManual')

@section('title', __('Proyectos'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Proyectos') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Crear y gestionar proyectos') }}</h5>
                <p>{{ __('Un proyecto agrupa todo el trabajo para un cliente o un objetivo interno: presupuesto, servicios, colaboradores y tareas. En el módulo de proyectos puedes:') }}</p>
                <ul>
                    <li>{{ __('Crear proyectos nuevos y vincularlos a un cliente o contacto.') }}</li>
                    <li>{{ __('Definir el presupuesto: especificación, unidades de tarifa, cantidades y totales. Puedes generar o editar la especificación del presupuesto desde la propia pantalla.') }}</li>
                    <li>{{ __('Añadir servicios al proyecto (del catálogo de servicios) con cantidades y precios.') }}</li>
                    <li>{{ __('Seleccionar y asignar colaboradores; el sistema puede filtrarlos por habilidades o disponibilidad.') }}</li>
                    <li>{{ __('Enviar notificaciones a los colaboradores asignados para avisarles del proyecto o de cambios.') }}</li>
                    <li>{{ __('Quitar colaboradores del proyecto si dejan de participar.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Vista detalle del proyecto') }}</h5>
                <p>{{ __('En la ficha del proyecto ves el presupuesto completo, los servicios, los colaboradores y las tareas relacionadas. Puedes añadir tareas sugeridas y seguir el avance. Todo queda centralizado en un solo sitio.') }}</p>

                <p class="mb-0">{{ __('Los proyectos son el eje que conecta clientes, servicios, colaboradores y tareas; desde aquí se organiza y factura el trabajo.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
