@extends('layouts/layoutManual')

@section('title', __('Contactos'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Contactos') }}</h4>
        <a href="{{ route('mockups.contact-form') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-forms me-1"></i>{{ __('Mockup del formulario') }}
        </a>
    </div>
    <div class="card-body">
        <h5>{{ __('Lista de contactos') }}</h5>
        <p>{{ __('Los contactos son las personas con las que interactúas: leads, prospectos o clientes. Desde la lista de contactos puedes:') }}</p>
        <ul>
            <li>{{ __('Ver todos los contactos en una tabla con búsqueda y filtros.') }}</li>
            <li>{{ __('Crear contactos nuevos (nombre, email, teléfono, empresa, etc.) y editar los existentes.') }}</li>
            <li>{{ __('Importar contactos desde un archivo (CSV o Excel).') }}</li>
            <li>{{ __('Vincular un contacto a una cuenta de usuario para que pueda iniciar sesión.') }}</li>
            <li>{{ __('Asociar el contacto a una empresa y registrar sentimiento o datos personalizados.') }}</li>
        </ul>

        <x-manual.role-compare section="contacts" />

        <h5 class="mt-2">{{ __('Prospección / Buscar clientes') }}</h5>
        <p>{{ __('Desde prospección puedes buscar personas y empresas en fuentes externas (por ejemplo Apollo) y añadirlas como contactos.') }}</p>

        <h5 class="mt-4">{{ __('Lista de 60') }}</h5>
        <p>{{ __('app.list60_manual_intro') }}</p>
        <h6 class="mt-3">{{ __('app.list60_manual_add_title') }}</h6>
        <p>{{ __('app.list60_manual_add_body') }}</p>
        <h6 class="mt-3">{{ __('app.list60_manual_table_title') }}</h6>
        <p>{{ __('app.list60_manual_table_body') }}</p>
        <h6 class="mt-3">{{ __('app.list60_manual_outreach_title') }}</h6>
        <p>{{ __('app.list60_manual_outreach_body') }}</p>
        <h6 class="mt-3">{{ __('app.list60_manual_follow_up_title') }}</h6>
        <p>{{ __('app.list60_manual_follow_up_body') }}</p>
        <h6 class="mt-3">{{ __('app.list60_manual_status_title') }}</h6>
        <p>{{ __('app.list60_manual_status_body') }}</p>

        <p class="mb-0 mt-3">
            <a href="{{ \App\Support\GuidePresentation::url('lista-de-60') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('app.list60_manual_view_presentation') }}
            </a>
            <a href="{{ \App\Support\GuidePresentation::url('prospeccion') }}" class="btn btn-sm btn-label-secondary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Presentación prospección') }}
            </a>
            <a href="{{ route('help.contacts') }}" class="btn btn-sm btn-label-secondary">{{ __('Ayuda: contactos') }}</a>
        </p>
    </div>
</div>
@endsection
