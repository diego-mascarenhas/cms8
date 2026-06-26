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
                        
                        @include('hosting.partials.plan-select', [
                            'selectedPlan' => old('plan', $hosting->plan ?? ''),
                            'selectedServerId' => old('server_id', $hosting->server_id ?? ''),
                        ])
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

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const serverSelect = document.getElementById('server_id');
    const planSelect = document.getElementById('plan');
    const planHelp = document.getElementById('plan-help');
    const selectedPlan = @json(old('plan', isset($hosting) ? $hosting->plan : ''));
    const plansUrlTemplate = @json(route('server.plans', ['server' => '__SERVER__']));

    if (! serverSelect || ! planSelect) {
        return;
    }

    function setPlanHelp(message, show) {
        if (! planHelp) {
            return;
        }

        planHelp.textContent = message;
        planHelp.classList.toggle('d-none', ! show);
    }

    function renderPlanOptions(plans, currentPlan) {
        let html = '<option value="">Seleccionar plan</option>';

        plans.forEach(function (planName) {
            const selected = currentPlan === planName ? ' selected' : '';
            html += '<option value="' + planName + '"' + selected + '>' + planName + '</option>';
        });

        if (currentPlan && ! plans.includes(currentPlan)) {
            html += '<option value="' + currentPlan + '" selected>' + currentPlan + ' (actual)</option>';
        }

        planSelect.innerHTML = html;
        planSelect.disabled = false;
    }

    function loadPlans(serverId, currentPlan) {
        if (! serverId) {
            planSelect.innerHTML = '<option value="">Seleccionar servidor primero</option>';
            planSelect.disabled = true;
            setPlanHelp('', false);

            return;
        }

        planSelect.disabled = true;
        planSelect.innerHTML = '<option value="">Cargando planes...</option>';
        setPlanHelp('', false);

        const url = plansUrlTemplate.replace('__SERVER__', serverId);

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function ({ ok, data }) {
                if (! ok || ! data.success) {
                    planSelect.innerHTML = '<option value="">' + (data.message || 'No se pudieron cargar los planes') + '</option>';
                    planSelect.disabled = true;

                    return;
                }

                renderPlanOptions(data.plans || [], currentPlan);

                if (data.limited_to_account) {
                    setPlanHelp('Este servidor usa credenciales de cuenta cPanel. Para listar todos los planes del reseller, configure acceso WHM.', true);
                }
            })
            .catch(function () {
                planSelect.innerHTML = '<option value="">Error al cargar planes</option>';
                planSelect.disabled = true;
            });
    }

    serverSelect.addEventListener('change', function () {
        loadPlans(this.value, '');
    });

    if (serverSelect.value) {
        loadPlans(serverSelect.value, selectedPlan);
    }
});
</script>
@endsection 