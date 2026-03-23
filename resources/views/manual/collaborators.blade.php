@extends('layouts/layoutManual')

@section('title', __('Colaboradores'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Colaboradores') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Fichas de colaboradores') }}</h5>
                <p>{{ __('Los colaboradores son personas externas que trabajan contigo en proyectos: freelancers, partners, proveedores, etc. En cada ficha de colaborador puedes:') }}</p>
                <ul>
                    <li>{{ __('Crear y mantener el perfil con datos de contacto, habilidades, software que domina, servicios que ofrece y temas o especialidades.') }}</li>
                    <li>{{ __('Gestionar tarifas y precios por colaborador (por hora, por proyecto o por servicio) para presupuestar correctamente.') }}</li>
                    <li>{{ __('Registrar disponibilidad y ausencias en un calendario: así ves qué días está disponible antes de asignarle un proyecto.') }}</li>
                    <li>{{ __('Subir portafolios, imágenes o archivos de referencia en la sección de medios.') }}</li>
                    <li>{{ __('Vincular el colaborador a una cuenta de usuario para que pueda entrar en Humano y ver los proyectos y tareas asignados.') }}</li>
                    <li>{{ __('Enviarles notificaciones o invitaciones (por ejemplo para unirse a un proyecto).') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Asignación a proyectos') }}</h5>
                <p>{{ __('Al crear o editar un proyecto puedes asignar uno o varios colaboradores y, si lo usas, añadir servicios o tareas. Puedes filtrar colaboradores por habilidades o disponibilidad para encontrar al más adecuado. Las tarifas y la disponibilidad te ayudan a planificar plazos y costes del proyecto.') }}</p>

                <p class="mb-0">{{ __('Todo lo que configures aquí (tarifas, habilidades, disponibilidad) se utiliza al armar presupuestos y al planificar el trabajo.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
