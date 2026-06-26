@extends('layouts/contentNavbarLayout')

@section('title', 'Detalle del dominio')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">Hosting/</span> {{ $domain->domain }}
        </h4>
        <p class="text-muted mb-0">Administra la cuenta de hosting, correo electrónico y registros DNS.</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
        <form action="{{ route('domain.refresh', $domain->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-label-primary waves-effect waves-light">
                <i class="ti ti-refresh me-1"></i> Actualizar
            </button>
        </form>
        <form action="{{ route('domain.toggle-suspension', $domain->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn {{ $domain->suspended ? 'btn-outline-success' : 'btn-outline-danger' }} waves-effect waves-light">
                <i class="ti ti-{{ $domain->suspended ? 'player-play' : 'player-pause' }} me-1"></i>
                {{ $domain->suspended ? 'Activar' : 'Suspender' }}
            </button>
        </form>
        <a href="{{ route('domain.edit', $domain->id) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-edit me-1"></i> Editar
        </a>
        <a href="{{ route('hosting.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

@include('hosting.partials.provisioning-notice')

@if (session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible mb-4" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible mb-4" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($accountIsSuspended ?? false)
    <div class="alert alert-warning mb-4">
        <i class="ti ti-player-pause me-1"></i>
        {{ \App\Services\ControlPanel\CpanelConnector::SUSPENDED_ACCOUNT_MESSAGE }}
    </div>
@endif

@if(empty($domain->data['last_refreshed']) && ($controlPanelType ?? null) === 'cpanel' && $domain->server?->hasToken())
    <div class="alert alert-info mb-4">
        <i class="ti ti-info-circle me-1"></i>
        Los datos del servidor no están sincronizados. Pulsa <strong>Actualizar</strong> para obtener la información más reciente.
    </div>
@endif

@php
    $sslValid = ($displayInfo['ssl_status']['valid'] ?? false) === true;
    $sslExpiry = $displayInfo['ssl_status']['expires'] ?? null;
    $hasObservations = $domain->suspended || $domain->needs_update || ! $domain->is_working;
@endphp

<!-- Status cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Dominio</h5>
                <p class="mb-1 fw-medium text-truncate" title="{{ $domain->domain }}">{{ $domain->domain }}</p>
                @if($domain->suspended)
                    <span class="badge bg-label-danger">Suspendido</span>
                @else
                    <span class="badge bg-label-success">Activo</span>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Servidor</h5>
                <p class="mb-0">{{ $domain->server ? $domain->server->server_url : 'N/D' }}</p>
                <small class="text-muted">Usuario: {{ $domain->username }}</small>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Sitio</h5>
                <p class="mb-0">Tipo: {{ filled($domain->site_type) ? $domain->site_type : '' }}</p>
                <p class="mb-0">Versión: {{ filled($domain->php_version) ? $domain->php_version : '' }}</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Capacidad</h5>
                @if($accountDisk)
                    @if($accountDisk['unlimited'] ?? false)
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ number_format($accountDisk['used_mb'] ?? 0, 1) }} MB usados</span>
                            <span class="text-muted">Ilimitado</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-label-primary" style="width: 100%;"></div>
                        </div>
                    @else
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ number_format($accountDisk['used_mb'] ?? 0, 1) }} / {{ number_format($accountDisk['limit_mb'] ?? 0, 0) }} MB</span>
                            <span class="text-muted">{{ $accountDisk['usage_percent'] ?? 0 }}%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            @php
                                $diskPercent = $accountDisk['usage_percent'] ?? 0;
                                $diskBarClass = $diskPercent >= 90 ? 'bg-danger' : ($diskPercent >= 75 ? 'bg-warning' : 'bg-primary');
                            @endphp
                            <div class="progress-bar {{ $diskBarClass }}" style="width: {{ $diskPercent }}%;"></div>
                        </div>
                    @endif
                @else
                    <small class="text-muted">Capacidad no disponible</small>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main domain details -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title mb-0">Detalle del dominio</h5>
                <small class="text-muted">Actualizado: {{ $domain->updated_at->diffForHumans() }}</small>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Dominio:</div>
                    <div class="col-md-8">{{ $domain->domain }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Servidor:</div>
                    <div class="col-md-8">{{ $domain->server ? $domain->server->server_url : 'N/D' }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Usuario cPanel:</div>
                    <div class="col-md-8">{{ $domain->username }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Plan:</div>
                    <div class="col-md-8">{{ $domain->plan ?? 'N/D' }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Tipo de sitio:</div>
                    <div class="col-md-8">{{ $domain->site_type ?? 'N/D' }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Versión PHP:</div>
                    <div class="col-md-8">{{ $domain->php_version ?? 'N/D' }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">IP web:</div>
                    <div class="col-md-8">{{ $displayInfo['web_ip'] ?? 'N/D' }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">IP correo:</div>
                    <div class="col-md-8">{{ $displayInfo['mail_ip'] ?? 'N/D' }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Estado SSL:</div>
                    <div class="col-md-8">
                        @if($sslValid)
                            <span class="badge bg-label-success">Válido</span>
                            @if($sslExpiry)
                                <small class="ms-2">Expira: {{ $sslExpiry }}</small>
                            @endif
                        @else
                            <span class="badge bg-label-danger">Inválido</span>
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Observaciones:</div>
                    <div class="col-md-8">
                        @if($hasObservations)
                            @if($domain->suspended)
                                <span class="badge bg-label-danger me-1">Suspendido</span>
                            @endif
                            @if($domain->needs_update)
                                <span class="badge bg-label-warning me-1">Requiere actualización</span>
                            @endif
                            @if(! $domain->is_working)
                                <span class="badge bg-label-danger me-1">No operativo</span>
                            @endif
                        @else
                            <span class="text-muted">Sin observaciones</span>
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Notas:</div>
                    <div class="col-md-8">{{ $domain->notes ?? 'Sin notas' }}</div>
                </div>
            </div>
        </div>

        @if(($controlPanelType ?? null) === 'cpanel' && $domain->server?->hasToken() && !($accountIsSuspended ?? false))
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Cuentas de correo</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createEmailModal">
                    <i class="ti ti-plus me-1"></i> Crear email
                </button>
            </div>
            <div class="card-body">
                @if(!empty($emailAccounts))
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Cuenta</th>
                                    <th style="min-width: 220px;">Capacidad</th>
                                    <th class="text-center" style="width: 100px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($emailAccounts as $account)
                                    <tr>
                                        <td>
                                            <span class="fw-medium">{{ $account['email'] }}</span>
                                        </td>
                                        <td>
                                            @if($account['unlimited'] ?? false)
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span>{{ number_format($account['diskused_mb'] ?? 0, 1) }} MB usados</span>
                                                    <span class="text-muted">Ilimitado</span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-label-primary" style="width: 100%;"></div>
                                                </div>
                                            @else
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span>{{ number_format($account['diskused_mb'] ?? 0, 1) }} / {{ number_format($account['diskquota_mb'] ?? 0, 0) }} MB</span>
                                                    <span class="text-muted">{{ $account['usage_percent'] ?? 0 }}%</span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    @php
                                                        $percent = $account['usage_percent'] ?? 0;
                                                        $barClass = $percent >= 90 ? 'bg-danger' : ($percent >= 75 ? 'bg-warning' : 'bg-primary');
                                                    @endphp
                                                    <div class="progress-bar {{ $barClass }}" style="width: {{ $percent }}%;"></div>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center align-items-center gap-1">
                                                @if($webmailUrl)
                                                    <a
                                                        href="{{ $domain->server->getWebmailUrl($account['email']) }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="text-body"
                                                        title="Abrir webmail"
                                                        aria-label="Abrir webmail"
                                                    >
                                                        <i class="ti ti-mail ti-sm"></i>
                                                    </a>
                                                @endif
                                                <a
                                                    href="javascript:;"
                                                    class="text-body change-email-password"
                                                    data-email="{{ $account['email'] }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#changeEmailPasswordModal"
                                                    title="Cambiar contraseña"
                                                    aria-label="Cambiar contraseña"
                                                >
                                                    <i class="ti ti-key ti-sm"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No hay cuentas de correo en este dominio.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- DNS and control panel management -->
    <div class="col-md-4">
        @if($controlPanelError && !($accountIsSuspended ?? false))
            <div class="alert alert-warning mb-4">
                <i class="ti ti-alert-triangle me-1"></i>{{ $controlPanelError }}
            </div>
        @endif

        @if(($controlPanelType ?? null) === 'cpanel' && !($accountIsSuspended ?? false))
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Plan de hosting</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <span class="text-muted">Plan actual:</span>
                    <span class="fw-medium">{{ $domain->plan ?? 'N/D' }}</span>
                </p>
                @if($domain->server?->hasToken())
                <form action="{{ route('domain.change-plan', $domain->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="plan" class="form-label">Cambiar plan</label>
                        <select name="plan" id="plan" class="form-select" required>
                            <option value="">Seleccionar plan</option>
                            @foreach($availablePlans as $planName)
                                <option value="{{ $planName }}" @selected($domain->plan === $planName)>{{ $planName }}</option>
                            @endforeach
                            @if($domain->plan && !in_array($domain->plan, $availablePlans, true))
                                <option value="{{ $domain->plan }}" selected>{{ $domain->plan }} (actual)</option>
                            @endif
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Actualizar plan</button>
                </form>
                @else
                    <p class="text-muted mb-0">Configura las credenciales del servidor para cambiar el plan.</p>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Registros MX</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('domain.mx-records', $domain->id) }}" method="POST">
                    @csrf
                    <div id="mx-records-list">
                        @forelse($mxRecords as $index => $record)
                            <div class="row g-2 mb-2 mx-record-row">
                                <div class="col-4">
                                    <input type="number" name="mx_records[{{ $index }}][priority]" class="form-control form-control-sm" value="{{ $record['priority'] }}" min="0" required>
                                </div>
                                <div class="col-8">
                                    <input type="text" name="mx_records[{{ $index }}][target]" class="form-control form-control-sm" value="{{ $record['target'] }}" required>
                                </div>
                            </div>
                        @empty
                            <div class="row g-2 mb-2 mx-record-row">
                                <div class="col-4">
                                    <input type="number" name="mx_records[0][priority]" class="form-control form-control-sm" value="10" min="0" required>
                                </div>
                                <div class="col-8">
                                    <input type="text" name="mx_records[0][target]" class="form-control form-control-sm" placeholder="mail.example.com" required>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    <button type="button" class="btn btn-label-secondary btn-sm mb-3" id="add-mx-record">Añadir MX</button>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Guardar registros MX</button>
                </form>
            </div>
        </div>
        @elseif(($controlPanelType ?? null) === 'plesk')
        <div class="card mb-4">
            <div class="card-body">
                <p class="text-muted mb-0">La gestión de cuentas Plesk estará disponible próximamente.</p>
            </div>
        </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Configuración DNS</h5>
            </div>
            <div class="card-body">
                @if(!empty($requiredNameservers) && ! ($nameserversMatch ?? false))
                    <div class="mb-3">
                        <h6 class="mb-2">Servidores DNS requeridos</h6>
                        <p class="text-muted small mb-2">Configura estos NS en el registrador del dominio:</p>
                        <ul class="list-unstyled mb-0 small">
                            @foreach($requiredNameservers as $nameserver)
                                <li><code>{{ strtoupper($nameserver) }}</code></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($currentNameservers))
                    @php
                        $dnsHasWarning = ! $nameserversMatch;
                    @endphp
                    <div class="mb-3">
                        <h6 @class([
                            'mb-2 d-inline-flex align-items-center gap-1',
                            'bg-label-warning rounded px-2 py-1' => $dnsHasWarning,
                        ])>
                            @if($dnsHasWarning)
                                <i class="ti ti-alert-triangle ti-xs"></i>
                            @endif
                            DNS actuales (públicos)
                        </h6>
                        <ul class="list-unstyled mb-0 small mt-2">
                            @foreach($currentNameservers as $ns)
                                <li><code>{{ $ns }}</code></li>
                            @endforeach
                        </ul>
                    </div>
                @elseif(!empty($requiredNameservers) && ! ($nameserversMatch ?? false))
                    <p class="text-muted small mb-3">Pulsa <strong>Actualizar</strong> para comprobar los DNS públicos del dominio.</p>
                @endif

                @php
                    $spfHasWarning = ! ($publicSpfCheck['exists'] ?? false) || ! ($publicSpfCheck['has_mailbaby'] ?? false);
                @endphp

                @if($spfHasWarning)
                    <div class="mb-3">
                        <h6 class="mb-2">SPF recomendado</h6>
                        <div class="bg-lighter rounded p-2">
                            <code class="small text-break">{{ $recommendedSpf }}</code>
                        </div>
                    </div>
                @endif

                <div class="mb-0">
                    <h6 @class([
                        'mb-2 d-inline-flex align-items-center gap-1',
                        'bg-label-warning rounded px-2 py-1' => $spfHasWarning,
                    ])>
                        @if($spfHasWarning)
                            <i class="ti ti-alert-triangle ti-xs"></i>
                        @endif
                        SPF público (DNS)
                    </h6>
                    @if($publicSpfCheck['exists'] ?? false)
                        <div class="bg-lighter rounded p-2 mt-2">
                            <code class="small text-break">{{ $publicSpfCheck['record'] }}</code>
                        </div>
                    @else
                        <p class="text-muted small mb-0 mt-2">No se encontró registro SPF público.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('modals')
@if(($controlPanelType ?? null) === 'cpanel' && $domain->server?->hasToken() && !($accountIsSuspended ?? false))
<div class="modal fade" id="createEmailModal" tabindex="-1" aria-labelledby="createEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('domain.emails.store', $domain->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createEmailModalLabel">Crear cuenta de correo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="create_email_local" class="form-label">Nombre de cuenta</label>
                        <div class="input-group">
                            <input type="text" name="email" id="create_email_local" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="info" pattern="[a-z0-9._-]+" required>
                            <span class="input-group-text">{{ '@'.$domain->domain }}</span>
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-0">
                        <label for="create_email_password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <input type="password" name="password" id="create_email_password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="12" required>
                            <button type="button" class="btn btn-outline-secondary" id="generate-email-password" title="Generar contraseña segura">
                                <i class="ti ti-wand"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear cuenta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="changeEmailPasswordModal" tabindex="-1" aria-labelledby="changeEmailPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('domain.email-password', $domain->id) }}" method="POST" id="changeEmailPasswordForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="changeEmailPasswordModalLabel">Cambiar contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="change_email_address" class="form-label">Cuenta</label>
                        <input type="email" name="email" id="change_email_address" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" readonly required>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-0">
                        <label for="change_email_password" class="form-label">Nueva contraseña</label>
                        <input type="password" name="password" id="change_email_password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="12" required>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.change-email-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const emailInput = document.getElementById('change_email_address');
            const passwordInput = document.getElementById('change_email_password');

            if (emailInput)
            {
                emailInput.value = button.dataset.email || '';
            }

            if (passwordInput)
            {
                passwordInput.value = '';
            }
        });
    });

    @if($errors->has('email') || $errors->has('password'))
        @if(old('email') && !str_contains(old('email'), '@'))
            const createModal = document.getElementById('createEmailModal');

            if (createModal)
            {
                bootstrap.Modal.getOrCreateInstance(createModal).show();
            }
        @else
            const passwordModal = document.getElementById('changeEmailPasswordModal');

            if (passwordModal)
            {
                bootstrap.Modal.getOrCreateInstance(passwordModal).show();
            }
        @endif
    @endif

    const generateEmailPasswordButton = document.getElementById('generate-email-password');
    const createEmailPasswordInput = document.getElementById('create_email_password');

    if (generateEmailPasswordButton && createEmailPasswordInput)
    {
        generateEmailPasswordButton.addEventListener('click', function () {
            const lowercase = 'abcdefghijklmnopqrstuvwxyz';
            const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const numbers = '0123456789';
            const symbols = '!@#$%^&*()-_=+';
            const all = lowercase + uppercase + numbers + symbols;
            const length = 16;
            let password = [
                lowercase[Math.floor(Math.random() * lowercase.length)],
                uppercase[Math.floor(Math.random() * uppercase.length)],
                numbers[Math.floor(Math.random() * numbers.length)],
                symbols[Math.floor(Math.random() * symbols.length)],
            ];

            for (let i = password.length; i < length; i++)
            {
                password.push(all[Math.floor(Math.random() * all.length)]);
            }

            password = password.sort(function () {
                return Math.random() - 0.5;
            }).join('');

            createEmailPasswordInput.type = 'text';
            createEmailPasswordInput.value = password;
        });
    }

    const list = document.getElementById('mx-records-list');
    const addButton = document.getElementById('add-mx-record');

    if (!list || !addButton) {
        return;
    }

    addButton.addEventListener('click', function () {
        const index = list.querySelectorAll('.mx-record-row').length;
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 mx-record-row';
        row.innerHTML = `
            <div class="col-4">
                <input type="number" name="mx_records[${index}][priority]" class="form-control form-control-sm" value="10" min="0" required>
            </div>
            <div class="col-8">
                <input type="text" name="mx_records[${index}][target]" class="form-control form-control-sm" placeholder="mail.example.com" required>
            </div>
        `;
        list.appendChild(row);
    });
});
</script>
@endpush
@endsection
