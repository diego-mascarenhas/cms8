@extends('layouts/layoutMaster')

@section('title', __('app.contacts'))

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
                <div class="toast-client">{{ session('success') }}</div>
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
                            <span>Leads</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalLeads ?? 0 }}</h3>
                                <p class="text-info mb-0">({{ $leadsPercentage ?? 0 }}%)</p>
                            </div>
                            <p class="mb-0">Total de Leads</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-info filter-status" data-status="1">
                                <i class="ti ti-target ti-sm"></i>
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
                            <span>En seguimiento</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalFollowUp ?? 0 }}</h3>
                                <p class="text-warning mb-0">({{ $followUpPercentage ?? 0 }}%)</p>
                            </div>
                            <p class="mb-0">Total en seguimiento</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-warning filter-status" data-status="2">
                                <i class="ti ti-arrows-left-right ti-sm"></i>
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
                            <span>Clientes</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalClients ?? 0 }}</h3>
                                <p class="text-success mb-0">({{ $clientsPercentage ?? 0 }}%)</p>
                            </div>
                            <p class="mb-0">Total de Clientes</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-success filter-status" data-status="5">
                                <i class="ti ti-user-check ti-sm"></i>
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
                            <span>Terminados</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalFinished ?? 0 }}</h3>
                                <p class="text-danger mb-0">({{ $finishedPercentage ?? 0 }}%)</p>
                            </div>
                            <p class="mb-0">Total terminados</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-danger filter-status" data-status="7">
                                <i class="ti ti-user-off ti-sm"></i>
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
                    <a href="{{ route('contact.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                        <i class="ti ti-plus me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Añadir contacto</span>
                    </a>
                    <button id="import-button" class="btn btn-outline-secondary btn-sm waves-effect">
                        <i class="ti ti-file-import me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Importar</span>
                    </button>
                    <!-- <button class="btn btn-outline-secondary btn-sm waves-effect">
                                    <i class="ti ti-file-export me-sm-1"></i>
                                    <span class="d-none d-sm-inline-block">Exportar</span>
                                </button> -->
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row gap-3">
                <div class="flex-grow-1">
                    <x-input-select id="EmotionalState" :options="$emotionalStates" :value="''"
                        placeholder="Selector de estado emocional" />
                </div>
                <div class="flex-grow-1">
                    <select id="ContractedService" class="form-select">
                        <option value=""> Selector de servicio contratado </option>
                    </select>
                </div>
                <div class="flex-grow-1">
                    <x-input-select id="EnterpriseState" :options="$enterpriseStatuses" :value="''"
                        placeholder="Selector de tipo de contacto" />
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
@endpush

@section('vendor-script')
    <script src="{{ asset('vendors/data-tables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
    <script src="{{ asset('vendors/fullcalendar/lib/moment.min.js') }}"></script>
    <script src="{{ asset('js/moment/' . app()->getLocale() . '.js') }}"></script>
@endsection

@push('modals')
    {{-- @include('_partials/_modals/modal-sentiment') --}}

    <!-- Modal Sentiment -->
    <div class="modal fade" id="updateSentimentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Actualizar Sentimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateSentimentForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="sentiment_id" class="form-label">Sentimiento</label>
                            <select class="form-select" id="sentiment_id" name="sentiment_id" required>
                                <option value="" selected disabled>Selecciona un estado emocional</option>
                                @foreach (App\Models\ContactSentiment::all() as $sentiment)
                                    <option value="{{ $sentiment->id }}">{{ $sentiment->name }} {{ $sentiment->emoji }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="sentiment_id_error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            <div class="invalid-feedback" id="notes_error"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Import -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Importar Contactos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">Archivo Excel</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            $(document).on('click', '.edit-sentiment', function() {
                var id = $(this).data('id');
                var url = "{{ route('contact.update-sentiment', ':id') }}";
                url = url.replace(':id', id);
                $('#updateSentimentForm').attr('action', url);
                $('#updateSentimentModal').modal('show');
            });

            $('#updateSentimentForm').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var url = form.attr('action');

                // Reset previous errors
                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.invalid-feedback').text('');

                $.ajax({
                    type: "POST",
                    url: url,
                    data: form.serialize(),
                    success: function(response) {
                        $('#updateSentimentModal').modal('hide');
                        toastr.success(response.message);
                        $('#updateSentimentModal').on('hidden.bs.modal', function() {
                            setTimeout(function() {
                                window.location.href =
                                    "{{ route('contact.show', '') }}/" +
                                    response.contactId;
                            }, 1000);
                        });
                        $('#updateSentimentModal').modal('hide');
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('#' + key).addClass('is-invalid');
                                $('#' + key + '_error').text(value[0]);
                            });
                        } else {
                            toastr.error('An error occurred. Please try again.');
                        }
                    }
                });
            });
        });

        $(function() {
            let table = $('.datatable').DataTable();

            $('#EmotionalState').on('change', function() {
                let selectedValue = $(this).val();
                table.column('.select-filter').search(selectedValue).draw();
            });

            $('#EnterpriseState').on('change', function() {
                let selectedValue = $(this).val();
                table.column('.enterprise-filter').search(selectedValue).draw();
            });

            $('.filter-status').on('click', function(e) {
                e.preventDefault();
                var status = $(this).data('status');
                table.column('status_id:name').search(status).draw();
            });

            $('#import-button').on('click', function() {
                $('#importModal').modal('show');
            });

            $('#importForm').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);

                $.ajax({
                    url: '{{ route('contact.import-excel') }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#importModal').modal('hide');
                        Swal.fire({
                            title: 'Importación completada',
                            text: `Importados: ${response.processed}, Actualizados: ${response.updated || 0}, Duplicados: ${response.duplicates || 0}`,
                            icon: 'success',
                            confirmButtonText: 'OK',
                            showCancelButton: false,
                            showDenyButton: false,
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                table.ajax.reload();
                            }
                        });
                    },
                    error: function(response) {
                        Swal.fire({
                            title: 'Error',
                            text: 'Hubo un problema al importar el archivo.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            showCancelButton: false,
                            showDenyButton: false,
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            }
                        });
                    }
                });
            });
        });

        function deleteRecord(id, element) {
            Swal.fire({
                title: '¿Estás seguro de que deseas eliminar este registro?',
                icon: 'warning',
                showCloseButton: false,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('contact.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok.');
                        }
                        return response.json();
                    }).then(data => {
                        console.log('Response data:', data);

                        const toastHTML = `
                    <div id="toast-container" class="toast-top-right">
                        <div class="toast toast-success" aria-live="polite" style="display: block;">
                            <div class="toast-client">${data.success}</div>
                        </div>
                    </div>
                `;
                        document.body.insertAdjacentHTML('beforeend', toastHTML);
                        var toastElement = document.getElementById('toast-container');
                        var toast = new bootstrap.Toast(toastElement, {
                            animation: true,
                            delay: 3000,
                            autohide: true
                        });
                        toast.show();

                        const row = element.closest('tr');
                        if (row) {
                            row.classList.add('fade-out');
                            row.addEventListener('transitionend', () => {
                                row.remove();
                            });
                        } else {
                            console.error('No se encontró la fila correspondiente.');
                        }
                    }).catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'Ha ocurrido un error al eliminar el registro', 'error');
                    });
                }
            });
        }
    </script>
@endpush
