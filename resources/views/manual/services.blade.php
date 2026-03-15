@extends('layouts/layoutManual')

@section('title', __('Servicios'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Servicios') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('¿Qué son los servicios?') }}</h5>
                <p>{{ __('Los servicios son los tipos de trabajo o ofertas que ofreces: por ejemplo diseño web, desarrollo a medida, consultoría, redacción, etc. Cada servicio tiene nombre, descripción y puede tener un precio o tarifa asociada.') }}</p>

                <h5 class="mt-4">{{ __('Qué puedes hacer') }}</h5>
                <ul>
                    <li>{{ __('Crear y editar servicios con nombre, descripción, precio unitario o tipo de tarifa.') }}</li>
                    <li>{{ __('Usar los servicios al armar presupuestos de proyectos: añades líneas de servicio y el sistema calcula totales.') }}</li>
                    <li>{{ __('Asignar servicios a un proyecto concreto y vincularlos a colaboradores o tareas si tu flujo lo requiere.') }}</li>
                    <li>{{ __('Consultar proyecciones de facturación basadas en los servicios y horas o unidades registradas.') }}</li>
                </ul>

                <p class="mb-0">{{ __('Los servicios te permiten estandarizar qué vendes y cómo presupuestas y facturas: todos usan el mismo catálogo y las mismas tarifas.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
