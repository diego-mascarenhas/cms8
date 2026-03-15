@extends('layouts/layoutManual')

@section('title', __('Dashboard y Hoy'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Dashboard y Hoy') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Dashboard') }}</h5>
                <p>{{ __('El dashboard es tu pantalla de resumen. Muestra de un vistazo la actividad reciente del equipo: últimos contactos añadidos o actualizados, tareas pendientes, estado de proyectos y métricas clave (por ejemplo número de contactos, proyectos activos, facturación). Te ayuda a ver qué requiere atención sin tener que entrar en cada módulo.') }}</p>

                <h5 class="mt-4">{{ __('Vista “Hoy” (Today)') }}</h5>
                <p>{{ __('La vista Hoy se centra en lo que importa para el día actual: tareas con vencimiento hoy, eventos o reuniones si usas calendario, y accesos rápidos para iniciar o continuar trabajo. Es tu lista del día para no perder el foco.') }}</p>

                <p class="mb-0">{{ __('Ambas vistas están en el menú principal y se adaptan a tu rol y permisos.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
