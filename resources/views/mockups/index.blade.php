@extends('layouts/layoutManual')

@section('title', __('Mockups'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Mockups y diagramas') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead mb-2">{{ __('Representaciones visuales de formularios y flujos de Humano. Sirven para entender cómo se ven las pantallas y qué campos usa cada rol.') }}</p>
                <p class="mb-0 text-muted">{{ __('Los formularios de ejemplo están deshabilitados: no guardan datos. Consulta el manual para el texto explicativo y estos mockups para la forma de la UI.') }}</p>
            </div>
        </div>
    </div>

    @foreach ($mockups as $item)
        <div class="col-md-6 col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="ti {{ $item['icon'] }} ti-lg text-primary"></i>
                        <h5 class="mb-0">{{ __($item['title']) }}</h5>
                    </div>
                    <p class="text-muted">{{ __($item['description']) }}</p>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <span class="badge bg-label-{{ $item['type'] === 'form' ? 'info' : 'warning' }}">{{ $item['type'] === 'form' ? __('Formulario') : __('Flujo') }}</span>
                        @foreach ($item['roles'] as $role)
                            <span class="badge bg-label-{{ $role === 'admin' ? 'primary' : 'secondary' }}">{{ ucfirst($role) }}</span>
                        @endforeach
                    </div>
                    <a href="{{ route($item['route']) }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-eye me-1"></i>{{ __('Ver mockup') }}
                    </a>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
