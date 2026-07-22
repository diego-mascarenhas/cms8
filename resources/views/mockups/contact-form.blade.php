@extends('layouts/layoutManual')

@section('title', __($mockup['title']))

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="card-title mb-1">{{ __($mockup['title']) }}</h4>
            <p class="text-muted mb-0">{{ __($mockup['description']) }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('manual.contacts') }}" class="btn btn-label-primary btn-sm">{{ __('Manual: Contactos') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <x-manual.role-compare section="contacts" />

        <div class="alert alert-secondary" role="alert">
            {{ __('Mockup estático del formulario real (contact/form). Los campos no envían datos.') }}
        </div>

        <h5 class="mb-3">{{ __('1. Datos personales') }}</h5>
        <div class="card border mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Nombre" required sample="María" />
                    <x-manual.mock-field label="Apellidos" sample="García López" />
                    <x-manual.mock-field label="Email" type="email" sample="maria@ejemplo.com" />
                    <x-manual.mock-field label="Teléfono" type="tel" sample="+34 600 000 000" />
                    <x-manual.mock-field label="Responsable / Asesor" type="select" sample="Usuario del equipo" admin-only hint="Solo admin asigna asesores de forma completa." />
                    <x-manual.mock-field label="Estado" type="select" sample="Activo" />
                    <x-manual.mock-field label="Usuario vinculado" type="select" sample="— Opcional —" hint="Cuenta de login asociada al contacto." />
                    <x-manual.mock-field label="Cumpleaños" type="date" sample="1990-05-12" />
                    <x-manual.mock-field label="Categorías" type="select" sample="Lead, Newsletter" />
                    <x-manual.mock-field label="Perfil" type="textarea" col="col-12" sample="Notas sobre el contacto…" />
                    <x-manual.mock-field label="Asistente WhatsApp IA" type="checkbox" sample="1" col="col-12" hint="Activar respuestas asistidas en chat." />
                </div>
            </div>
        </div>

        <h5 class="mb-3">{{ __('2. Redes sociales') }}</h5>
        <div class="card border mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Red" type="select" sample="LinkedIn" />
                    <x-manual.mock-field label="URL / valor" sample="https://linkedin.com/in/…" />
                </div>
            </div>
        </div>

        <h5 class="mb-3">{{ __('3. Empresa') }}</h5>
        <div class="card border mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Empresa existente" type="select" sample="— Elegir —" />
                    <x-manual.mock-field label="Departamento" type="select" sample="Ventas" />
                    <x-manual.mock-field label="Nombre empresa" sample="Acme SL" />
                    <x-manual.mock-field label="CIF / Código" sample="B12345678" />
                    <x-manual.mock-field label="Website" sample="https://acme.example" />
                    <x-manual.mock-field label="Email empresa" type="email" sample="info@acme.example" />
                    <x-manual.mock-field label="Teléfono empresa" type="tel" sample="+34 910 000 000" />
                    <x-manual.mock-field label="WhatsApp empresa" type="tel" sample="+34 600 111 222" />
                </div>
            </div>
        </div>

        <h5 class="mb-3">{{ __('4. Dirección') }}</h5>
        <div class="card border mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Dirección" sample="Calle Ejemplo 1" col="col-md-8" />
                    <x-manual.mock-field label="Código postal" sample="28001" col="col-md-4" />
                    <x-manual.mock-field label="Población" sample="Madrid" />
                    <x-manual.mock-field label="Provincia" sample="Madrid" />
                    <x-manual.mock-field label="País" type="select" sample="España" />
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" disabled>{{ __('Guardar') }}</button>
            <button type="button" class="btn btn-label-secondary" disabled>{{ __('Cancelar') }}</button>
            <span class="badge bg-label-danger align-self-center">{{ __('Eliminar: solo Admin') }}</span>
        </div>
    </div>
</div>
@endsection
