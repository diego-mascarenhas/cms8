@extends('layouts/layoutManual')

@section('title', __('Sitio web y contenidos'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Sitio web y contenidos') }}</h4>
    </div>
    <div class="card-body">
        <p>{{ __('Módulos para publicar y gestionar presencia digital desde Humano.') }}</p>

        <h5 class="mt-4">{{ __('Landing pages') }}</h5>
        <p>{{ __('Editor de páginas de aterrizaje para campañas y captación.') }}</p>

        <h5 class="mt-4">{{ __('CMS: páginas, posts y media') }}</h5>
        <p>{{ __('Gestiona entradas y páginas en Humano y sincronízalas con WordPress mediante plugins IDONEO.') }}</p>
        <p class="mb-2">
            <a href="{{ \App\Support\GuidePresentation::url('cms-wordpress') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Presentación CMS / WordPress') }}
            </a>
            <a href="{{ route('help.plugins') }}" class="btn btn-sm btn-label-secondary">{{ __('Plugins en Ayuda') }}</a>
        </p>

        <h5 class="mt-4">{{ __('Multimedia') }}</h5>
        <p>{{ __('Galería de imágenes y archivos reutilizables en contenidos y comunicaciones.') }}</p>

        <h5 class="mt-4">{{ __('Academia') }}</h5>
        <p>{{ __('Contenidos formativos internos del equipo (si el módulo está activo en tu plan).') }}</p>

        <x-manual.role-compare section="website" />
    </div>
</div>
@endsection
