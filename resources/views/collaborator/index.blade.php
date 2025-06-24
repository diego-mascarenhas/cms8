@extends('layouts/layoutMaster')

@section('title', __('Collaborators'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
@endsection

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }
</style>

@section('content')
    @if (session('success'))
        <div id="toast-container" class="toast-top-right">
            <div class="toast toast-success" aria-live="polite" style="display: block;">
                <div class="toast-message">{{ session('success') }}</div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var toastElement = document.getElementById('toast-container');
                var toast = new bootstrap.Toast(toastElement, {
                    animation: true,
                    delay: 1000,
                    autohide: true
                });
                toast.show();
            });
        </script>
    @endif

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-0">{{ __('Por aceptar') }}</h6>
                            <div class="d-flex align-items-center mt-1">
                                <h3 class="mb-0 me-1">237</h3>
                                <small class="text-success">(+42%)</small>
                            </div>
                            <small class="text-muted">{{ __('Último mes') }}</small>
                        </div>
                        <div class="avatar bg-label-warning rounded p-2">
                            <i class="ti ti-search"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-0">{{ __('Colaboradoras') }}</h6>
                            <div class="d-flex align-items-center mt-1">
                                <h3 class="mb-0 me-1">1,459</h3>
                                <small class="text-success">(+29%)</small>
                            </div>
                            <small class="text-muted">{{ __('Total') }}</small>
                        </div>
                        <div class="avatar bg-label-primary rounded p-2">
                            <i class="ti ti-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-0">{{ __('Nuevos') }}</h6>
                            <div class="d-flex align-items-center mt-1">
                                <h3 class="mb-0 me-1">67</h3>
                                <small class="text-success">(+18%)</small>
                            </div>
                            <small class="text-muted">{{ __('Última semana') }}</small>
                        </div>
                        <div class="avatar bg-label-danger rounded p-2">
                            <i class="ti ti-user-plus"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-0">{{ __('Sin actualizar') }}</h6>
                            <div class="d-flex align-items-center mt-1">
                                <h3 class="mb-0 me-1">540</h3>
                                <small class="text-danger">(-14%)</small>
                            </div>
                            <small class="text-muted">{{ __('Últimos 6 meses') }}</small>
                        </div>
                        <div class="avatar bg-label-success rounded p-2">
                            <i class="ti ti-user-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">            
            <h5 class="mb-3">Filtros</h5>
            <div class="row g-3 mb-3">
                <div class="col">
                    <x-variant-language-select 
                        name="idioma-origen" 
                        id="idioma-origen" 
                        label="" 
                        :required="false"
                        placeholder="{{ __('Idioma origen') }}"
                    />
                </div>
                <div class="col">
                    <x-variant-language-select 
                        name="idioma-destino" 
                        id="idioma-destino" 
                        label="" 
                        :required="false"
                        placeholder="{{ __('Idioma destino') }}"
                    />
                </div>
                <div class="col">
                    <x-fare-select 
                        name="servicio" 
                        id="servicio" 
                        label="" 
                        :required="false"
                        placeholder="{{ __('Servicio') }}"
                    />
                </div>
                <div class="col">
                    <select class="form-select" id="dias">
                        <option value="" selected>{{ __('Días') }}</option>
                        <option value="5">5 días</option>
                        <option value="10">10 días</option>
                        <option value="15">15 días</option>
                        <option value="30">30 días</option>
                    </select>
                </div>
                <div class="col">
                    <select class="form-select" id="fecha-entrega">
                        <option value="" selected>{{ __('Fecha entrega') }}</option>
                        <option value="today">Hoy</option>
                        <option value="week">Esta semana</option>
                        <option value="month">Este mes</option>
                    </select>
                </div>
            </div>
            <div class="row align-items-center mb-4">
                <div class="col-md-1">
                    <select class="form-select" id="entries-length">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-6"></div>
                <div class="col-md-5 d-flex justify-content-end align-items-center gap-2">
                    <input type="text" class="form-control w-auto me-2" id="search" placeholder="{{ __('Buscar') }}" style="width: 350px;">
                    <button class="btn btn-outline-primary me-2" style="height: 40px; min-width: 110px;">
                        <i class="ti ti-download me-1"></i>
                        <span style="white-space: nowrap;">{{ __('Exportar') }}</span>
                    </button>
                    <a href="{{ route('collaborator.create') }}" class="btn btn-primary ms-2 d-flex align-items-center gap-1" style="height: 40px; min-width: 170px;">
                        <i class="ti ti-plus"></i>
                        <span style="white-space: nowrap;">{{ __('Añadir nuevo') }}</span>
                    </a>
                </div>
            </div>
            
            <hr>

            {{ $dataTable->table(['class' => 'table table-hover table-striped dt-responsive nowrap w-100']) }}
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

    <script>
        $(document).ready(function() {
            // Eliminar el buscador duplicado
            $('.dataTables_filter').hide();
            
            // Filtros de tabla
            $('#idioma-origen, #idioma-destino, #servicio, #dias, #fecha-entrega').on('change', function() {
                var table = $('#collaborator-table').DataTable();
                
                // Get current filter values
                var sourceLanguage = $('#idioma-origen').val();
                var targetLanguage = $('#idioma-destino').val();
                var servicio = $('#servicio').val();
                var dias = $('#dias').val();
                var fechaEntrega = $('#fecha-entrega').val();
                
                // Add parameters to ajax request
                table.settings()[0].ajax.data = function(d) {
                    d.source_language = sourceLanguage;
                    d.target_language = targetLanguage;
                    d.servicio = servicio;
                    d.dias = dias;
                    d.fecha_entrega = fechaEntrega;
                };
                
                // Reload table with new parameters
                table.draw();
            });
            

            
            // Longitud de entradas
            $('#entries-length').on('change', function() {
                $('#collaborator-table').DataTable().page.len($(this).val()).draw();
            });
            
            // Búsqueda
            $('#search').on('keyup', function() {
                $('#collaborator-table').DataTable().search($(this).val()).draw();
            });

            // Función para eliminar un colaborador
            function deleteRecord(id, element) {
                event.preventDefault();
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¿Deseas eliminar este colaborador?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    customClass: {
                        confirmButton: 'btn btn-primary me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: route('collaborator.destroy', id),
                            type: 'DELETE',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                            },
                            success: function(response) {
                                $('#collaborator-table').DataTable().ajax.reload();
                                toastr['success']('', response.message, {
                                    closeButton: true,
                                    tapToDismiss: false,
                                    rtl: false
                                });
                            },
                            error: function(response) {
                                Swal.fire({
                                    title: 'Error',
                                    text: response.responseJSON.message || 'Ha ocurrido un error',
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

            // Delegación de eventos para botones de acción
            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                deleteRecord(id, this);
            });
        });

        // Función para marcar como ojo
        function markAsWatch(id) {
            Swal.fire({
                title: '¿Marcar como ojo?',
                text: "Este colaborador será marcado para supervisión especial",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, marcar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-warning me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/collaborator/' + id + '/mark-as-watch',
                        type: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                        },
                        success: function(response) {
                            toastr['success']('', response.message, {
                                closeButton: true,
                                tapToDismiss: false,
                                rtl: false
                            });
                            $('#collaborator-table').DataTable().ajax.reload();
                        },
                        error: function(response) {
                            Swal.fire({
                                title: 'Error',
                                text: response.responseJSON.message || 'Ha ocurrido un error',
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

        // Función para enviar a lista negra
        function sendToBlacklist(id) {
            Swal.fire({
                title: '¿Enviar a lista negra?',
                text: "Este colaborador será bloqueado y no podrá participar en proyectos",
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Sí, bloquear',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/collaborator/' + id + '/send-to-blacklist',
                        type: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                        },
                        success: function(response) {
                            toastr['success']('', response.message, {
                                closeButton: true,
                                tapToDismiss: false,
                                rtl: false
                            });
                            $('#collaborator-table').DataTable().ajax.reload();
                        },
                        error: function(response) {
                            Swal.fire({
                                title: 'Error',
                                text: response.responseJSON.message || 'Ha ocurrido un error',
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

        // Función para enviar notificación
        function sendNotification(id) {
            Swal.fire({
                title: 'Enviar notificación',
                input: 'textarea',
                inputLabel: 'Mensaje de notificación',
                inputPlaceholder: 'Escribe tu mensaje aquí...',
                inputAttributes: {
                    'aria-label': 'Escribe tu mensaje de notificación'
                },
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Debes escribir un mensaje'
                    }
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/collaborator/' + id + '/send-notification',
                        type: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            message: result.value
                        },
                        success: function(response) {
                            toastr['success']('', response.message, {
                                closeButton: true,
                                tapToDismiss: false,
                                rtl: false
                            });
                        },
                        error: function(response) {
                            Swal.fire({
                                title: 'Error',
                                text: response.responseJSON.message || 'Ha ocurrido un error',
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
