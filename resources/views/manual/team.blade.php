@extends('layouts/layoutManual')

@section('title', __('Equipo'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Equipo') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Gestión de usuarios') }}</h5>
                <p>{{ __('Los administradores pueden gestionar los usuarios del equipo: invitar a nuevas personas (por email), asignarles un rol (admin, colaborador, empleado), y activar o desactivar el acceso. Los usuarios son las cuentas que inician sesión en Humano; cada uno ve solo lo que su rol y permisos permiten.') }}</p>

                <h5 class="mt-4">{{ __('Departamentos') }}</h5>
                <p>{{ __('Los departamentos sirven para organizar el equipo (por ejemplo Ventas, Desarrollo, Administración). Puedes crear departamentos y asignar usuarios a cada uno; así puedes filtrar informes, tareas o permisos por departamento.') }}</p>

                <h5 class="mt-4">{{ __('Organización') }}</h5>
                <p>{{ __('Si está activo, el módulo de organización permite definir la estructura de la empresa (áreas, equipos, jerarquía) y vincularla a equipos o departamentos para una visión más clara de quién hace qué.') }}</p>

                <p class="mb-0">{{ __('La configuración del equipo (datos fiscales, buzones de correo, tokens de API, etc.) se hace en la sección de ajustes del equipo; los detalles técnicos están en la Ayuda.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
