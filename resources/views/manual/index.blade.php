@extends('layouts/layoutManual')

@section('title', __('Manual de usuario'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Manual de usuario de Humano') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Este manual describe todo lo que puedes hacer en Humano. Está pensado para usuarios finales y se centra en las funciones y el uso diario, no en detalles técnicos.') }}</p>

                <p>{{ __('Puedes usar el menú lateral para ir a cualquier sección. Para documentación técnica (API, variables de entorno, integraciones), entra en') }} <a href="{{ route('help.index') }}">{{ __('Ayuda y documentación') }}</a>.</p>

                <h5 class="mt-4">{{ __('Qué encontrarás aquí') }}</h5>
                <ul>
                    <li><strong>{{ __('Primeros pasos') }}</strong> — {{ __('Roles, equipos y navegación básica.') }}</li>
                    <li><strong>{{ __('Dashboard y Hoy') }}</strong> — {{ __('Vista general y vista del día.') }}</li>
                    <li><strong>{{ __('Contactos') }}</strong> — {{ __('Gestión de contactos, prospección y Lista de 60.') }}</li>
                    <li><strong>{{ __('Clientes') }}</strong> — {{ __('Fichas de clientes y datos relacionados.') }}</li>
                    <li><strong>{{ __('Colaboradores') }}</strong> — {{ __('Perfiles, tarifas, disponibilidad y portafolios.') }}</li>
                    <li><strong>{{ __('Servicios') }}</strong> — {{ __('Servicios que ofreces y su uso en proyectos.') }}</li>
                    <li><strong>{{ __('Proyectos') }}</strong> — {{ __('Crear y gestionar proyectos, presupuestos y colaboradores.') }}</li>
                    <li><strong>{{ __('Tareas y tiempo') }}</strong> — {{ __('Tareas, kanban, registro de tiempo y asistencia.') }}</li>
                    <li><strong>{{ __('Chat y WhatsApp') }}</strong> — {{ __('Conversaciones e integración con WhatsApp.') }}</li>
                    <li><strong>{{ __('Productos y pedidos') }}</strong> — {{ __('Catálogo de productos y gestión de pedidos.') }}</li>
                    <li><strong>{{ __('Facturas y pagos') }}</strong> — {{ __('Facturación, pagos, ingresos, gastos y panel financiero.') }}</li>
                    <li><strong>{{ __('Mensajes y plantillas') }}</strong> — {{ __('Campañas de email/SMS y plantillas de mensajes.') }}</li>
                    <li><strong>{{ __('Equipo') }}</strong> — {{ __('Usuarios, departamentos y organización.') }}</li>
                    <li><strong>{{ __('Más funciones') }}</strong> — {{ __('Empresas, contenidos, prompts, notificaciones y otras herramientas.') }}</li>
                </ul>

                <div class="alert alert-info mt-4" role="alert">
                    <h6 class="alert-heading mb-2">
                        <i class="ti ti-info-circle me-2"></i>
                        {{ __('¿Nuevo en Humano?') }}
                    </h6>
                    <p class="mb-0">{{ __('Empieza por') }} <a href="{{ route('manual.getting-started') }}" class="alert-link">{{ __('Primeros pasos') }}</a> {{ __('para conocer los roles, equipos y la navegación básica.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
