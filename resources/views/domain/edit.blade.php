@extends('layouts/contentNavbarLayout')

@section('title', 'Editar dominio')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">Hosting/</span> {{ $domain->domain }}
        </h4>
        <p class="text-muted mb-0">Editar datos del dominio</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
        <a href="{{ route('domain.show', $domain->id) }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Dominio</h5>
    <form class="card-body" method="POST" action="{{ route('domain.update', $domain->id) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-6">
                <label for="domain" class="form-label">Nombre del dominio (*)</label>
                <input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain" value="{{ old('domain', $domain->domain) }}" required placeholder="ejemplo.com">
                @error('domain')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="server_id" class="form-label">Servidor (*)</label>
                <select class="form-select @error('server_id') is-invalid @enderror" id="server_id" name="server_id" required>
                    <option value="">Seleccionar servidor</option>
                    @foreach ($servers as $server)
                        <option value="{{ $server->id }}" {{ old('server_id', $domain->server_id) == $server->id ? 'selected' : '' }}>
                            {{ $server->name }}
                        </option>
                    @endforeach
                </select>
                @error('server_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="username" class="form-label">Usuario (*)</label>
                <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $domain->username) }}" required placeholder="Usuario cPanel">
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="plan" class="form-label">Plan</label>
                <input type="text" class="form-control @error('plan') is-invalid @enderror" id="plan" name="plan" value="{{ old('plan', $domain->plan) }}" placeholder="Plan de hosting">
                @error('plan')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="site_type" class="form-label">Tipo de sitio</label>
                <select class="form-select @error('site_type') is-invalid @enderror" id="site_type" name="site_type">
                    <option value="">Seleccionar tipo</option>
                    <option value="WordPress" {{ old('site_type', $domain->site_type) == 'WordPress' ? 'selected' : '' }}>WordPress</option>
                    <option value="Laravel" {{ old('site_type', $domain->site_type) == 'Laravel' ? 'selected' : '' }}>Laravel</option>
                    <option value="Static" {{ old('site_type', $domain->site_type) == 'Static' ? 'selected' : '' }}>HTML estático</option>
                    <option value="Other" {{ old('site_type', $domain->site_type) == 'Other' ? 'selected' : '' }}>Otro</option>
                </select>
                @error('site_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="php_version" class="form-label">Versión PHP</label>
                <select class="form-select @error('php_version') is-invalid @enderror" id="php_version" name="php_version">
                    <option value="">Seleccionar versión</option>
                    <option value="8.2" {{ old('php_version', $domain->php_version) == '8.2' ? 'selected' : '' }}>PHP 8.2</option>
                    <option value="8.1" {{ old('php_version', $domain->php_version) == '8.1' ? 'selected' : '' }}>PHP 8.1</option>
                    <option value="8.0" {{ old('php_version', $domain->php_version) == '8.0' ? 'selected' : '' }}>PHP 8.0</option>
                    <option value="7.4" {{ old('php_version', $domain->php_version) == '7.4' ? 'selected' : '' }}>PHP 7.4</option>
                    <option value="7.3" {{ old('php_version', $domain->php_version) == '7.3' ? 'selected' : '' }}>PHP 7.3</option>
                </select>
                @error('php_version')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="notes" class="form-label">Notas</label>
                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="Notas adicionales sobre este dominio">{{ old('notes', $domain->notes) }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="suspended" name="suspended" {{ old('suspended', $domain->suspended) ? 'checked' : '' }}>
                    <label class="form-check-label" for="suspended">Suspender</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="needs_update" name="needs_update" {{ old('needs_update', $domain->needs_update) ? 'checked' : '' }}>
                    <label class="form-check-label" for="needs_update">Requiere actualización</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="is_working" name="is_working" {{ old('is_working', $domain->is_working) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_working">Funcionando</label>
                </div>
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary waves-effect waves-light">Guardar</button>
                <button type="reset" class="btn btn-label-secondary waves-effect" onclick="location.href='{{ route('domain.show', $domain->id) }}'">Cancelar</button>
            </div>
        </div>
    </form>
</div>
@endsection
