@extends('layouts.contentNavbarLayout')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
@php($totalDomains = ($activeDomains ?? 0) + ($suspendedDomains ?? 0))
<div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Servidores/</span> {{ $server->name }}</h4>
            <p class="text-muted">Detalle del servidor de hosting</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            @if($server->control_panel === 'cpanel' && $server->hasToken())
                <button type="button" class="btn btn-outline-primary waves-effect waves-light" id="test-connection-btn">
                    <i class="ti ti-world me-1"></i> Probar
                </button>
                <button type="button" class="btn btn-outline-success waves-effect waves-light" id="sync-domains-btn">
                    <i class="ti ti-refresh me-1"></i> Sincronizar
                </button>
            @endif
            <a href="{{ route('server.index') }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>
            @can('server.edit')
            <a href="{{ route('server.edit', $server->id) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i> Editar
            </a>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información del servidor</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th style="width: 30%;">Nombre</th>
                            <td>{{ $server->name }}</td>
                        </tr>
                        <tr>
                            <th>Dirección IP</th>
                            <td>{{ $server->ip ?: 'No especificada' }}</td>
                        </tr>
                        <tr>
                            <th>Servidor</th>
                            <td>{{ $server->hostname }}</td>
                        </tr>
                        <tr>
                            <th>Usuario</th>
                            <td>{{ $server->username }}</td>
                        </tr>
                        <tr>
                            <th>Panel</th>
                            <td>{{ $server->control_panel_name }}</td>
                        </tr>
                        @if($server->team)
                        <tr>
                            <th>Equipo</th>
                            <td>{{ $server->team->name }}</td>
                        </tr>
                        @endif
                        <tr>
                            <th>Credencial</th>
                            <td>
                                @if($server->hasToken())
                                    <span class="badge bg-success">Configurada</span>
                                @else
                                    <span class="badge bg-warning">Sin configurar</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Estado</th>
                            <td>
                                <span class="badge bg-{{ $server->status_id->color() }}">
                                    {{ $server->status_id->name() }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Creado</th>
                            <td>{{ $server->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Actualización</th>
                            <td>{{ $server->updated_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            @can('server.destroy')
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Acciones</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('server.destroy', $server->id) }}" method="POST"
                          onsubmit="return confirm('¿Seguro que quieres eliminar este servidor?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="ti ti-trash me-1"></i> Eliminar
                        </button>
                    </form>
                </div>
            </div>
            @endcan

            @if($totalDomains > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Dominios</h5>
                </div>
                <div class="card-body">
                    <div id="server-domains-chart"></div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">{{ $totalDomains }} dominio(s) sincronizados</small>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @if($server->control_panel === 'cpanel')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Dominios en cPanel</h5>
                </div>
                <div class="card-body">
                    @if(!$server->hasToken())
                        <div class="alert alert-warning mb-0">
                            <i class="ti ti-alert-triangle me-2"></i>
                            Credencial no configurada. Edita el servidor para añadir la contraseña o token de cPanel.
                        </div>
                    @elseif($totalDomains === 0)
                        <div class="alert alert-info mb-0">
                            <i class="ti ti-info-circle me-2"></i>
                            No hay dominios sincronizados. Pulsa «Sincronizar» para importarlos desde cPanel.
                        </div>
                    @else
                        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
@if($totalDomains > 0)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartEl = document.querySelector('#server-domains-chart');

    if (chartEl && typeof ApexCharts !== 'undefined') {
        new ApexCharts(chartEl, {
            chart: {
                type: 'donut',
                height: 260,
                fontFamily: 'Public Sans'
            },
            series: [{{ (int) ($activeDomains ?? 0) }}, {{ (int) ($suspendedDomains ?? 0) }}],
            labels: ['Activos', 'Suspendidos'],
            colors: ['#71dd37', '#ff3e1d'],
            stroke: { width: 0 },
            dataLabels: { enabled: false },
            legend: {
                show: true,
                position: 'bottom',
                horizontalAlign: 'center',
                fontSize: '12px',
                markers: { width: 10, height: 10, offsetX: -3 }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { show: false },
                            value: {
                                show: true,
                                fontSize: '22px',
                                fontWeight: 600,
                                color: '#566a7f',
                                offsetY: 4,
                                formatter: function(val) {
                                    return val;
                                }
                            },
                            total: {
                                show: true,
                                label: 'Total',
                                fontSize: '13px',
                                color: '#a1acb8',
                                formatter: function(w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            }
        }).render();
    }
});
</script>
@endif

@if(($totalDomains ?? 0) > 0 && isset($dataTable))
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    const syncDomainsBtn = document.getElementById('sync-domains-btn');

    function handleSyncDomains(btn) {
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i> Sincronizando…';

        fetch(`{{ route('server.syncDomains', $server->id) }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('danger', data.message || 'No se pudieron sincronizar los dominios');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Ocurrió un error al sincronizar los dominios');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    if (syncDomainsBtn) {
        syncDomainsBtn.addEventListener('click', function() {
            handleSyncDomains(this);
        });
    }

    const testConnectionBtn = document.getElementById('test-connection-btn');
    if (testConnectionBtn) {
        testConnectionBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const btn = this;
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i> Probando…';

            fetch(`{{ route('server.testConnection', $server->id) }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                }
            })
            .then(response => {
                if (response.ok) {
                    showAlert('success', 'Conexión correcta.');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', 'No se pudo probar la conexión');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Ocurrió un error al probar la conexión');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    }

    function showAlert(type, message) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="ti ti-${type === 'success' ? 'check' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.container');
        container.insertBefore(alert, container.firstChild);

        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
});
</script>
@endpush
