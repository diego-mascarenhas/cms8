@extends('layouts/layoutMaster')

@section('title', isset($hosting) ? 'Editar Hosting' : 'Agregar Nuevo Hosting')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold py-3 mb-0">
        <span class="text-muted fw-light">Hosting /</span> {{ isset($hosting) ? 'Editar Hosting' : 'Agregar Nuevo Hosting' }}
    </h4>
    <a href="{{ route('hosting.index') }}" class="btn btn-secondary">
        <i class="ti ti-arrow-left me-1"></i>
        Volver a la Lista
    </a>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Detalles del Hosting</h5>
            </div>
            <div class="card-body">
                @if(isset($hosting))
                    <form method="POST" action="{{ route('hosting.update', $hosting->id) }}">
                    @method('PUT')
                @else
                    <form method="POST" action="{{ route('hosting.store') }}">
                @endif
                    @csrf
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="domain" class="form-label">Nombre de Dominio</label>
                            <input type="text" class="form-control @error('domain') is-invalid @enderror" 
                                id="domain" name="domain" 
                                value="{{ old('domain', $hosting->domain ?? '') }}" 
                                required placeholder="ejemplo.com">
                            @error('domain')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="server_id" class="form-label">Servidor</label>
                            <select class="form-select @error('server_id') is-invalid @enderror" id="server_id" name="server_id" required>
                                <option value="">Seleccionar Servidor</option>
                                @foreach ($servers as $server)
                                    <option value="{{ $server->id }}" 
                                        {{ old('server_id', $hosting->server_id ?? '') == $server->id ? 'selected' : '' }}>
                                        {{ $server->name }} ({{ $server->server_url }})
                                    </option>
                                @endforeach
                            </select>
                            @error('server_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control @error('username') is-invalid @enderror" 
                                id="username" name="username" 
                                value="{{ old('username', $hosting->username ?? '') }}" 
                                required placeholder="Usuario cPanel">
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="plan" class="form-label">Plan</label>
                            <input type="text" class="form-control @error('plan') is-invalid @enderror" 
                                id="plan" name="plan" 
                                value="{{ old('plan', $hosting->plan ?? '') }}" 
                                placeholder="Plan de hosting">
                            @error('plan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="site_type" class="form-label">Tipo de Sitio</label>
                            <select class="form-select @error('site_type') is-invalid @enderror" id="site_type" name="site_type">
                                <option value="">Seleccionar Tipo</option>
                                <option value="WordPress" {{ old('site_type', $hosting->site_type ?? '') == 'WordPress' ? 'selected' : '' }}>WordPress</option>
                                <option value="Laravel" {{ old('site_type', $hosting->site_type ?? '') == 'Laravel' ? 'selected' : '' }}>Laravel</option>
                                <option value="Static" {{ old('site_type', $hosting->site_type ?? '') == 'Static' ? 'selected' : '' }}>HTML Estático</option>
                                <option value="Other" {{ old('site_type', $hosting->site_type ?? '') == 'Other' ? 'selected' : '' }}>Otro</option>
                            </select>
                            @error('site_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="php_version" class="form-label">Versión PHP</label>
                            <select class="form-select @error('php_version') is-invalid @enderror" id="php_version" name="php_version">
                                <option value="">Seleccionar Versión</option>
                                <option value="8.2" {{ old('php_version', $hosting->php_version ?? '') == '8.2' ? 'selected' : '' }}>PHP 8.2</option>
                                <option value="8.1" {{ old('php_version', $hosting->php_version ?? '') == '8.1' ? 'selected' : '' }}>PHP 8.1</option>
                                <option value="8.0" {{ old('php_version', $hosting->php_version ?? '') == '8.0' ? 'selected' : '' }}>PHP 8.0</option>
                                <option value="7.4" {{ old('php_version', $hosting->php_version ?? '') == '7.4' ? 'selected' : '' }}>PHP 7.4</option>
                                <option value="7.3" {{ old('php_version', $hosting->php_version ?? '') == '7.3' ? 'selected' : '' }}>PHP 7.3</option>
                            </select>
                            @error('php_version')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                id="notes" name="notes" rows="3" 
                                placeholder="Notas adicionales sobre este hosting">{{ old('notes', $hosting->notes ?? '') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="suspended" name="suspended" 
                                    {{ old('suspended', $hosting->suspended ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="suspended">Suspendido</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="needs_update" name="needs_update" 
                                    {{ old('needs_update', $hosting->needs_update ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="needs_update">Necesita Actualización</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                {{ isset($hosting) ? 'Actualizar Hosting' : 'Crear Hosting' }}
                            </button>
                            <a href="{{ route('hosting.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection 