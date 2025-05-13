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

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>{{ __('Por aceptar') }}</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">237</h3>
                                <p class="text-success mb-0">(+42%)</p>
                            </div>
                            <p class="mb-0">{{ __('Último mes') }}</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-warning filter-status" data-status="1">
                                <i class="ti ti-user-plus ti-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>{{ __('Colaboradoras') }}</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">1,459</h3>
                                <p class="text-success mb-0">(+29%)</p>
                            </div>
                            <p class="mb-0">{{ __('Total') }}</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-primary filter-status" data-status="2">
                                <i class="ti ti-users ti-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>{{ __('Nuevos') }}</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">67</h3>
                                <p class="text-primary mb-0">(+18%)</p>
                            </div>
                            <p class="mb-0">{{ __('Última semana') }}</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-danger filter-status" data-status="5">
                                <i class="ti ti-user-plus ti-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>{{ __('Sin actualizar en 6m') }}</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">540</h3>
                                <p class="text-dark mb-0">(-14%)</p>
                            </div>
                            <p class="mb-0">{{ __('Últimos 6 meses') }}</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-success filter-status" data-status="6">
                                <i class="ti ti-user-check ti-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-bottom">
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('collaborator.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                        <i class="ti ti-plus me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Añadir colaborador</span>
                    </a>
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row gap-3">
                <div class="flex-grow-1">
                    <select id="IdiomaOrigen" class="form-select form-select-sm" >
                        <option value="">Idioma Origen</option>
                        <option value="ES">Español</option>
                        <option value="EN">Inglés</option>
                        <option value="FR">Francés</option>
                        <option value="DE">Alemán</option>
                        <option value="CA">Catalán</option>
                    </select>
                </div>
                <div class="flex-grow-1">
                    <select id="IdiomaDestino" class="form-select form-select-sm">
                        <option value="">Idioma Destino</option>
                        <option value="ES">Español</option>
                        <option value="EN">Inglés</option>
                        <option value="FR">Francés</option>
                        <option value="DE">Alemán</option>
                        <option value="CA">Catalán</option>
                    </select>
                </div>
                <div class="flex-grow-1">
                    <select id="Servicio" class="form-select form-select-sm">
                        <option value="">Servicio</option>
                        <option value="transcreacion">Transcreación</option>
                        <option value="documentos">Documentos</option>
                        <option value="subtitulado">Subtitulado</option>
                        <option value="traduccion-literaria">Traducción literaria</option>
                        <option value="interpretacion">Interpretación</option>
                    </select>
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
            // Filtros de tabla
            $(document).on('click', '.filter-status', function() {
                let status = $(this).data('status');
                $('#collaborator-table').DataTable().column(3).search(status).draw();
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
            
            // Filtros de idiomas y servicios
            $('#IdiomaOrigen, #IdiomaDestino, #Servicio').on('change', function() {
                let columna = $(this).attr('id') === 'IdiomaOrigen' ? 2 : 
                              $(this).attr('id') === 'IdiomaDestino' ? 3 : 4;
                
                let valor = $(this).val();
                $('#collaborator-table').DataTable().column(columna).search(valor).draw();
            });
        });
    </script>
@endpush
