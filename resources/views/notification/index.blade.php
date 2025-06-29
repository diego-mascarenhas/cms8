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

<div class="card">
    <div class="card-header border-bottom">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
            <div class="d-flex gap-2">
                @can('notification.create')
                <a href="{{ route('notification.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                    <i class="ti ti-plus me-sm-1"></i>
                    <span class="d-none d-sm-inline-block">{{ __('Create Notification') }}</span>
                </a>
                @endcan
            </div>
        </div>
        <div class="d-flex flex-column flex-md-row gap-3">
            <div class="flex-grow-1">
                <select class="form-select" id="statusFilter">
                    <option value="">Todos los estados</option>
                    <option value="sent">Enviados</option>
                    <option value="unsent">Pendientes</option>
                </select>
            </div>
            <div class="flex-grow-1">
                <select class="form-select" id="typeFilter">
                    <option value="">Todos los tipos</option>
                    @foreach(\App\Models\NotificationType::getActiveOptions() as $type)
                        <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-grow-1">
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                    <input type="text" class="form-control flatpickr-input" id="dateFromFilter" placeholder="Desde" readonly>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@push('scripts')
{{ $dataTable->scripts(attributes: ['type' => 'module']) }}

<script>
$(document).ready(function() {
    // Initialize date picker
    $('.flatpickr-input').flatpickr({
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        locale: {
            firstDayOfWeek: 1,
            weekdays: {
                shorthand: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
            },
            months: {
                shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
            }
        }
    });

    // Get DataTable instance
    let table = $('.datatable').DataTable();

    // Status filter
    $('#statusFilter').on('change', function() {
        let selectedValue = $(this).val();
        table.column(4).search(selectedValue).draw(); // Status column
    });

    // Type filter  
    $('#typeFilter').on('change', function() {
        let selectedValue = $(this).val();
        table.column(3).search(selectedValue).draw(); // Type column
    });

    // Date filter
    $('#dateFromFilter').on('change', function() {
        let selectedValue = $(this).val();
        table.column(7).search(selectedValue).draw(); // Created at column
    });
});

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
                        $('.datatable').DataTable().ajax.reload(null, false);
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
                        $('.datatable').DataTable().ajax.reload(null, false);
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
</script>
@endpush 