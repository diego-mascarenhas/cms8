@extends('layouts/layoutMaster')

@section('title', __('Notifications'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('content')
<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Notifications') }}</h4>
        <p class="text-muted">{{ __('Manage all system notifications') }}</p>
    </div>
    @can('notification.create')
    <div class="mt-3 mt-md-0">
        <a href="{{ route('notification.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('Create Notification') }}
        </a>
    </div>
    @endcan
</div>

<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select class="form-select" id="statusFilter">
                    <option value="">Todos</option>
                    <option value="sent">Enviados</option>
                    <option value="unsent">Pendientes</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select class="form-select" id="typeFilter">
                    <option value="">Todos</option>
                    @foreach(\App\Models\NotificationType::getActiveOptions() as $type)
                        <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha desde</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                    <input type="text" class="form-control flatpickr-input" id="dateFromFilter" placeholder="dd/mm/yyyy" readonly>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha hasta</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                    <input type="text" class="form-control flatpickr-input" id="dateToFilter" placeholder="dd/mm/yyyy" readonly>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <button type="button" class="btn btn-primary me-2" onclick="applyFilters()">
                    <i class="ti ti-search me-1"></i>Filtrar
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                    <i class="ti ti-x me-1"></i>Limpiar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notifications DataTable -->
<div class="card">
    <div class="card-header border-bottom">
        <h5 class="card-title mb-3">Lista de Notificaciones</h5>
        <div class="d-flex justify-content-between align-items-center row pb-2 gap-3 gap-md-0">
            <div class="col-md-4 user_role"></div>
            <div class="col-md-4 user_plan"></div>
            <div class="col-md-4 user_status"></div>
        </div>
    </div>
    <div class="card-datatable table-responsive">
        {{ $dataTable->table(['class' => 'table table-hover']) }}
    </div>
</div>

@endsection

@section('page-script')
{{ $dataTable->scripts() }}

<script>
$(document).ready(function() {
    // Make DataTable variable global
    window.LaravelDataTables = window.LaravelDataTables || {};
    
    // Initialize filters
    initializeFilters();
    
    // Initialize date pickers
    initializeDatePickers();
});

function initializeFilters() {
    $('#statusFilter, #typeFilter, #dateFromFilter, #dateToFilter').on('change', function() {
        // Auto-apply filters when changed
        // applyFilters();
    });
}

function initializeDatePickers() {
    // Initialize flatpickr for date filters
    flatpickr('#dateFromFilter', {
        dateFormat: 'd/m/Y',
        locale: 'es',
        allowInput: true,
        onChange: function(selectedDates, dateStr, instance) {
            // Optional: auto-apply filters when date changes
        }
    });
    
    flatpickr('#dateToFilter', {
        dateFormat: 'd/m/Y',
        locale: 'es',
        allowInput: true,
        onChange: function(selectedDates, dateStr, instance) {
            // Optional: auto-apply filters when date changes
        }
    });
}

function applyFilters() {
    if (window.LaravelDataTables && window.LaravelDataTables['notifications-table']) {
        var table = window.LaravelDataTables['notifications-table'];
        
        // Add filter parameters
        var params = {
            status: $('#statusFilter').val(),
            type: $('#typeFilter').val(),
            date_from: $('#dateFromFilter').val(),
            date_to: $('#dateToFilter').val()
        };
        
        // Apply filters and reload
        table.ajax.reload();
    }
}

function clearFilters() {
    $('#statusFilter, #typeFilter, #dateFromFilter, #dateToFilter').val('');
    applyFilters();
}

// Notification actions
function sendNotification(id) {
    Swal.fire({
        title: '¿Enviar notificación?',
        text: 'Esta acción enviará la notificación por email al contacto.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            $.ajax({
                url: `/notification/${id}/send`,
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Enviado',
                            text: response.message,
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                        
                        // Reload DataTable
                        if (window.LaravelDataTables && window.LaravelDataTables['notifications-table']) {
                            window.LaravelDataTables['notifications-table'].ajax.reload(null, false);
                        }
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                error: function (response) {
                    Swal.fire({
                        title: 'Error',
                        text: response.responseJSON?.message || 'Ha ocurrido un error',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}

function resendNotification(id) {
    Swal.fire({
        title: '¿Reenviar notificación?',
        text: 'Esta acción volverá a enviar la notificación por email.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, reenviar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-warning me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            $.ajax({
                url: `/notification/${id}/resend`,
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Reenviado',
                            text: response.message,
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                        
                        // Reload DataTable
                        if (window.LaravelDataTables && window.LaravelDataTables['notifications-table']) {
                            window.LaravelDataTables['notifications-table'].ajax.reload(null, false);
                        }
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                error: function (response) {
                    Swal.fire({
                        title: 'Error',
                        text: response.responseJSON?.message || 'Ha ocurrido un error',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}

function deleteRecord(id, element) {
    Swal.fire({
        title: '¿Eliminar notificación?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            $(element).closest('form').submit();
        }
    });
}
</script>
@endsection 