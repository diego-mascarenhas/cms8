@extends('layouts/layoutManual')

@section('title', __('Primeros pasos'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">{{ __('Primeros pasos') }}</h4>
                <a href="{{ route('mockups.overview') }}" class="btn btn-sm btn-label-primary">
                    <i class="ti ti-map me-1"></i>{{ __('Mapa general') }}
                </a>
            </div>
            <div class="card-body">
                <h5>{{ __('¿Qué es Humano?') }}</h5>
                <p>{{ __('Humano es una plataforma de gestión empresarial. Permite centralizar en un solo sitio la gestión de contactos, clientes, colaboradores, proyectos, tareas, tiempo, facturación y comunicaciones (email, SMS, WhatsApp).') }}</p>

                <h5 class="mt-4">{{ __('Roles de usuario') }}</h5>
                <p>{{ __('Lo que puedes ver y hacer depende de tu rol:') }}</p>
                <ul>
                    <li><strong>{{ __('Root') }}</strong> — {{ __('Acceso total al sistema (superadministrador técnico).') }}</li>
                    <li><strong>{{ __('Admin') }}</strong> — {{ __('Administra el equipo: configuración, usuarios, facturación y todos los datos del equipo.') }}</li>
                    <li><strong>{{ __('Collaborator') }}</strong> — {{ __('Trabaja en proyectos y tareas; CRM operativo sin facturación ni usuarios.') }}</li>
                    <li><strong>{{ __('Client') }}</strong> — {{ __('Usuario final vinculado a un contacto: ve sus proyectos/servicios, abre tickets y consulta presupuestos. No administra el CRM.') }}</li>
                    <li><strong>{{ __('Employee') }}</strong> — {{ __('Miembro interno del equipo con permisos definidos por el administrador.') }}</li>
                </ul>

                <x-manual.role-compare section="getting-started" />

                <h5 class="mt-2">{{ __('Equipos (teams)') }}</h5>
                <p>{{ __('Los datos se organizan por equipo. Puedes pertenecer a uno o varios equipos y cambiar entre ellos. Cada equipo tiene sus propios contactos, proyectos, facturas y configuraciones. Al cambiar de equipo verás solo la información de ese equipo.') }}</p>

                <h5 class="mt-4">{{ __('Navegación por el menú') }}</h5>
                <p>{{ __('El menú principal lateral te permite acceder a:') }}</p>
                <ul>
                    <li>{{ __('Dashboard y Hoy para tener una visión general.') }}</li>
                    <li>{{ __('Contactos, Clientes y Colaboradores para gestionar personas.') }}</li>
                    <li>{{ __('Servicios, Proyectos y Tareas para el trabajo del día a día.') }}</li>
                    <li>{{ __('Productos, Pedidos, Facturas y Pagos para comercio y cobros (facturación: solo Admin).') }}</li>
                    <li>{{ __('Mensajes y Plantillas para campañas de comunicación.') }}</li>
                    <li>{{ __('Usuarios y Departamentos dentro de Equipo (usuarios: solo Admin).') }}</li>
                </ul>

                <p class="mb-3">{{ __('Los elementos del menú que ves dependen de tu rol y de los módulos activados para tu equipo.') }}</p>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('mockups.roles-flow') }}" class="btn btn-sm btn-label-primary">{{ __('Carriles Admin / Collaborator / Client') }}</a>
                    <a href="{{ route('mockups.admin-setup') }}" class="btn btn-sm btn-primary">{{ __('Diagrama: arranque admin') }}</a>
                    <a href="{{ route('mockups.collaborator-day') }}" class="btn btn-sm btn-label-secondary">{{ __('Diagrama: día collaborator') }}</a>
                    <a href="{{ route('mockups.client-journey') }}" class="btn btn-sm btn-success">{{ __('Diagrama: viaje Client') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
