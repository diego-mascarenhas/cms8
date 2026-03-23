@extends('layouts/layoutManual')

@section('title', __('Primeros pasos'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Primeros pasos') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('¿Qué es Humano?') }}</h5>
                <p>{{ __('Humano es una plataforma de gestión empresarial. Permite centralizar en un solo sitio la gestión de contactos, clientes, colaboradores, proyectos, tareas, tiempo, facturación y comunicaciones (email, SMS, WhatsApp).') }}</p>

                <h5 class="mt-4">{{ __('Roles de usuario') }}</h5>
                <p>{{ __('Lo que puedes ver y hacer depende de tu rol:') }}</p>
                <ul>
                    <li><strong>{{ __('Root') }}</strong> — {{ __('Acceso total al sistema (superadministrador técnico).') }}</li>
                    <li><strong>{{ __('Admin') }}</strong> — {{ __('Administra el equipo: configuración, usuarios, facturación y todos los datos del equipo.') }}</li>
                    <li><strong>{{ __('Collaborator') }}</strong> — {{ __('Trabaja en proyectos y tareas; puede tener acceso limitado a clientes y facturación según permisos.') }}</li>
                    <li><strong>{{ __('Employee') }}</strong> — {{ __('Miembro interno del equipo con permisos definidos por el administrador.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Equipos (teams)') }}</h5>
                <p>{{ __('Los datos se organizan por equipo. Puedes pertenecer a uno o varios equipos y cambiar entre ellos. Cada equipo tiene sus propios contactos, proyectos, facturas y configuraciones. Al cambiar de equipo verás solo la información de ese equipo.') }}</p>

                <h5 class="mt-4">{{ __('Navegación por el menú') }}</h5>
                <p>{{ __('El menú principal lateral te permite acceder a:') }}</p>
                <ul>
                    <li>{{ __('Dashboard y Hoy para tener una visión general.') }}</li>
                    <li>{{ __('Contactos, Clientes y Colaboradores para gestionar personas.') }}</li>
                    <li>{{ __('Servicios, Proyectos y Tareas para el trabajo del día a día.') }}</li>
                    <li>{{ __('Productos, Pedidos, Facturas y Pagos para comercio y cobros.') }}</li>
                    <li>{{ __('Mensajes y Plantillas para campañas de comunicación.') }}</li>
                    <li>{{ __('Usuarios y Departamentos dentro de Equipo.') }}</li>
                </ul>

                <p class="mb-0">{{ __('Los elementos del menú que ves dependen de tu rol y de los módulos activados para tu equipo.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
