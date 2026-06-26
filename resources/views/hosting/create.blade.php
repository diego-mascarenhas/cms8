@extends('layouts/layoutMaster')

@section('title', isset($hosting) ? 'Editar Hosting' : 'Agregar Nuevo Hosting')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">Hosting/</span>
            {{ isset($hosting) ? 'Editar' : 'Agregar Nuevo Hosting' }}
        </h4>
        <p class="text-muted">Provisiona y administra cuentas de hosting para tus clientes.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('hosting.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>
            Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Detalles del Hosting</h5>
            </div>
            <div class="card-body">
                @if(isset($hosting))
                    <form method="POST" action="{{ route('hosting.update', $hosting->id) }}" novalidate>
                    @method('PUT')
                @else
                    <form method="POST" action="{{ route('hosting.store') }}" id="hosting-form" novalidate>
                @endif
                    @csrf

                    @error('provision')
                        <div class="alert alert-danger">
                            <strong>No se pudo crear la cuenta en cPanel</strong>
                            <div class="mb-0 mt-1" style="white-space: pre-wrap;">{{ $message }}</div>
                        </div>
                    @enderror
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <x-input-general
                                id="domain"
                                label="Nombre de Dominio (*)"
                                value="{{ old('domain', $hosting->domain ?? '') }}"
                            />
                        </div>

                        @if(! isset($hosting))
                        <div class="col-md-6">
                            <x-client-select
                                id="enterprise_id"
                                label="Empresa (*)"
                                :selected="old('enterprise_id', $enterpriseId ?? '')"
                                :allowNull="false"
                            />
                        </div>
                        @else
                        <div class="col-md-6">
                            <label for="service_id" class="form-label">Servicio</label>
                            <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id">
                                <option value="">Sin servicio vinculado</option>
                                @foreach ($services ?? [] as $service)
                                    <option value="{{ $service->id }}"
                                        data-enterprise-id="{{ $service->enterprise_id }}"
                                        @selected((string) old('service_id', $hosting->service_id ?? '') === (string) $service->id)>
                                        {{ $service->enterprise?->name ?? '—' }}
                                        — {{ $service->description ?: ($service->serviceType?->name ?? 'Servicio') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                    </div>

                    @if(! isset($hosting))
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="service_id" class="form-label">Servicio existente</label>
                            <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id">
                                <option value="">Crear servicio nuevo al guardar</option>
                                @foreach ($services ?? [] as $service)
                                    <option value="{{ $service->id }}"
                                        data-enterprise-id="{{ $service->enterprise_id }}"
                                        @selected((string) old('service_id', $serviceId ?? '') === (string) $service->id)>
                                        {{ $service->enterprise?->name ?? '—' }}
                                        — {{ $service->description ?: ($service->serviceType?->name ?? 'Servicio') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Opcional. Si no eliges uno, se creará un servicio de hosting para la empresa.</div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="server_id" class="form-label">Servidor (*)</label>
                            <select class="form-select @error('server_id') is-invalid @enderror" id="server_id" name="server_id">
                                @if($servers->count() !== 1)
                                    <option value="" disabled @selected(! old('server_id', isset($hosting) ? $hosting->server_id : ''))>Seleccionar servidor</option>
                                @endif
                                @foreach ($servers as $server)
                                    @php
                                        $selectedServerId = old('server_id', isset($hosting) ? $hosting->server_id : ($servers->count() === 1 ? $server->id : ''));
                                    @endphp
                                    <option value="{{ $server->id }}" 
                                        {{ (string) $selectedServerId === (string) $server->id ? 'selected' : '' }}>
                                        {{ $server->name }} ({{ $server->server_url }})
                                    </option>
                                @endforeach
                            </select>
                            @error('server_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <x-input-general
                                id="username"
                                label="Nombre de Usuario (*)"
                                value="{{ old('username', $hosting->username ?? '') }}"
                                maxlength="16"
                            />
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        @include('hosting.partials.plan-select', [
                            'selectedPlan' => old('plan', isset($hosting) ? $hosting->plan : ''),
                            'selectedServerId' => old('server_id', isset($hosting) ? $hosting->server_id : ''),
                            'required' => true,
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
                    
                    @if(isset($hosting))
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="needs_update" name="needs_update"
                                    {{ old('needs_update', $hosting->needs_update ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="needs_update">Necesita actualización</label>
                            </div>
                            <div class="form-text">Marca sitios pendientes de actualizar CMS, plugins o dependencias.</div>
                        </div>
                    </div>
                    @else
                    <div class="alert alert-info mb-3">
                        La contraseña cPanel se generará automáticamente y se mostrará una sola vez al crear la cuenta.
                        @if(! empty($hostingContactEmail))
                            <span class="d-block mt-1">Email de contacto en cPanel: <strong>{{ $hostingContactEmail }}</strong></span>
                        @endif
                    </div>
                    @endif
                    
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
    const domainInput = document.getElementById('domain');
    const usernameInput = document.getElementById('username');
    const selectedPlan = @json(old('plan', isset($hosting) ? $hosting->plan : ''));
    const plansUrlTemplate = @json(route('server.plans', ['server' => '__SERVER__']));
    let usernameManuallyEdited = @json((bool) old('username'));

    function suggestUsername(domain) {
        domain = (domain || '').trim().toLowerCase().replace(/^https?:\/\//, '').split('/')[0];
        let label = domain.split('.')[0] || domain;
        let username = label.replace(/[-.]/g, '').replace(/[^a-z0-9_]/g, '');

        if (! username || ! /^[a-z]/.test(username)) {
            username = 'u' + label.replace(/[^a-z0-9_]/g, '');
        }

        username = username.slice(0, 16);

        return username || 'site';
    }

    function applySuggestedUsername() {
        if (! usernameManuallyEdited && usernameInput && domainInput && domainInput.value) {
            usernameInput.value = suggestUsername(domainInput.value);
        }
    }

    if (domainInput) {
        domainInput.addEventListener('input', applySuggestedUsername);
        domainInput.addEventListener('blur', function () {
            this.value = this.value.trim().toLowerCase();
            applySuggestedUsername();
        });
    }

    if (usernameInput) {
        usernameInput.addEventListener('input', function () {
            usernameManuallyEdited = true;
        });
    }

    applySuggestedUsername();

    const enterpriseSelect = document.getElementById('enterprise_id');
    const serviceSelect = document.getElementById('service_id');

    function filterServicesByEnterprise() {
        if (! enterpriseSelect || ! serviceSelect) {
            return;
        }

        const enterpriseId = enterpriseSelect.value;

        Array.from(serviceSelect.options).forEach(function (option) {
            if (! option.value) {
                option.hidden = false;

                return;
            }

            option.hidden = enterpriseId !== '' && option.dataset.enterpriseId !== enterpriseId;
        });

        if (serviceSelect.value) {
            const selected = serviceSelect.selectedOptions[0];

            if (selected && selected.hidden) {
                serviceSelect.value = '';
            }
        }
    }

    if (enterpriseSelect) {
        enterpriseSelect.addEventListener('change', filterServicesByEnterprise);
        filterServicesByEnterprise();
    }

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
                    setPlanHelp('Solo se pudo obtener el plan actual de la cuenta. Si es reseller, verifique que el usuario tenga acceso WHM.', true);
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

    const hostingForm = document.getElementById('hosting-form') || document.querySelector('form');

    if (hostingForm) {
        hostingForm.addEventListener('submit', function () {
            planSelect.disabled = false;
        });
    }
});
</script>
@endsection 