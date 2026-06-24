@extends('layouts/layoutMaster')

@section('title', __('app.contacts'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
@endsection

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }

    .contact-list-toolbar {
        display: flex;
        flex-flow: row nowrap;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
    }

    .contact-list-toolbar .btn {
        flex-shrink: 0;
    }

    .contact-list-toolbar__filter {
        flex: 1 1 0;
        min-width: 9rem;
    }

    .contact-list-toolbar .form-group {
        margin-bottom: 0;
    }

    .contact-list-toolbar .select2-container {
        width: 100% !important;
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
                                <p class="text-success mb-0">({{ $leadsPercentage ?? 0 }}%)</p>
                            </div>
                            <p class="mb-0">Total de leads</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-success filter-status" data-status="1">
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
                                <p class="text-primary mb-0">({{ $clientsPercentage ?? 0 }}%)</p>
                            </div>
                            <p class="mb-0">Total de clientes</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-primary filter-status" data-status="5">
                                <i class="ti ti-user-heart ti-sm"></i>
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
                            <span>Finalizados</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalFinished ?? 0 }}</h3>
                                <p class="text-dark mb-0">({{ $finishedPercentage ?? 0 }}%)</p>
                            </div>
                            <p class="mb-0">Total finalizados</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-dark filter-status" data-status="6">
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
            <div class="contact-list-toolbar">
                <a href="{{ route('contact.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                    <i class="ti ti-plus me-sm-1"></i>
                    <span class="d-none d-sm-inline-block">Añadir contacto</span>
                </a>
                <a href="{{ route('contact.import-mapping') }}" class="btn btn-outline-secondary btn-sm waves-effect">
                    <i class="ti ti-file-import me-sm-1"></i>
                    <span class="d-none d-sm-inline-block">Importar</span>
                </a>
                @can('create', \App\Models\Contact::class)
                    @if (auth()->user()->currentTeam?->hasModule('prospecting'))
                        <a href="{{ route('prospect.search') }}" class="btn btn-outline-secondary btn-sm waves-effect">
                            <i class="ti ti-target me-sm-1"></i>
                            <span class="d-none d-sm-inline-block">Buscar clientes</span>
                        </a>
                    @endif
                @endcan
                <div class="contact-list-toolbar__filter">
                    <x-input-select id="EmotionalState" :options="$emotionalStates" :value="''"
                        placeholder="Selector de estado emocional" />
                </div>
                <div class="contact-list-toolbar__filter">
                    <x-module-categories-select
                        id="CategoryFilter"
                        label=""
                        moduleKey="contacts"
                        :selected="''"
                        :listingFilter="true"
                    />
                </div>
                <div class="d-none">
                    <x-input-select id="EnterpriseState" :options="$enterpriseStatuses" :value="''"
                        placeholder="Selector de tipo de contacto" />
                </div>
            </div>
        </div>
        <div class="card-body">
            {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

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
                                var showUrl = "{{ route('contact.show', '__ID__') }}".replace('__ID__', response.contactId);
                                window.location.href = showUrl;
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

            @if (auth()->user()->currentTeam?->hasModule('list60'))
            $('#addToList60Modal').on('shown.bs.modal', function() {
                initList60ModalSelects();

                const data = window.list60PrefillData || {};
                $('#list60_notes').val(data.notes || '');

                const $categories = $('#list60_category_ids');
                if ($categories.length) {
                    $categories.val((data.category_ids || []).map(String)).trigger('change');
                }

                @if (auth()->user()->hasRole('admin'))
                    $('#list60_responsible_id').val('{{ auth()->id() }}').trigger('change');
                @endif
            });

            $('#addToList60Form').on('submit', function(e) {
                e.preventDefault();
                submitAddToList60();
            });
            @endif
        });

        $(function() {
            $('#EnterpriseState').on('change', function() {
                let table = $('#contact-table').DataTable();
                let selectedValue = $(this).val();
                table.column('.enterprise-filter').search(selectedValue).draw();
            });

            $('#import-button').on('click', function() {
                $('#importModal').modal('show');
            });

            $('#importForm').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);

                $.ajax({
                    url: '{{ route('contact.upload-file') }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        window.location.href = '{{ route('contact.import') }}';
                    },
                    error: function(response) {
                        Swal.fire({
                            title: 'Error',
                            text: 'Hubo un problema al importar el archivo.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });
        });

        function deleteRecord(id, element) {
            event.preventDefault();
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¿Deseas eliminar este contacto?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.value) {
                    fetch("{{ route('contact.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        const row = element.closest('tr');
                        if (row) {
                            row.classList.add('fade-out');
                            row.addEventListener('transitionend', () => {
                                row.remove();
                            });
                        }

                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: data.success,
                            customClass: {
                                confirmButton: 'btn btn-success'
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ha ocurrido un error al eliminar el registro',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            }
                        });
                    });
                }
            });
        }

        function addToList(id, element) {
            event.preventDefault();

            window.list60AddTrigger = element;

            fetch("{{ route('list60.prefill', ['contact' => ':ID']) }}".replace(':ID', id), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('No se pudo cargar el contacto');
                }
                return response.json();
            })
            .then(data => {
                $('#list60_contact_id').val(data.id);
                $('#addToList60ContactName').text(data.name);
                window.list60PrefillData = data;

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addToList60Modal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo abrir el formulario de la Lista de 60',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            });
        }

        function initList60ModalSelects() {
            const $modal = $('#addToList60Modal');

            @if (auth()->user()->hasRole('admin'))
                const $responsible = $('#list60_responsible_id');
                if ($responsible.length && $.fn.select2) {
                    if ($responsible.hasClass('select2-hidden-accessible')) {
                        $responsible.select2('destroy');
                    }
                    $responsible.select2({
                        dropdownParent: $modal,
                        width: '100%',
                    });
                }
            @endif

            const $categories = $('#list60_category_ids');
            if ($categories.length && $.fn.select2) {
                if ($categories.hasClass('select2-hidden-accessible')) {
                    $categories.select2('destroy');
                }
                $categories.select2({
                    dropdownParent: $modal,
                    width: '100%',
                    allowClear: true,
                    closeOnSelect: false,
                    placeholder: '{{ __('Seleccione una categoría') }}',
                });
            }
        }

        function submitAddToList60() {
            const id = $('#list60_contact_id').val();
            const element = window.list60AddTrigger;

            const payload = {
                contact_id: parseInt(id, 10),
                notes: $('#list60_notes').val(),
                category_ids: $('#list60_category_ids').val() || [],
            };

            @if (auth()->user()->hasRole('admin'))
                payload.responsible_id = parseInt($('#list60_responsible_id').val(), 10);
            @endif

            fetch("{{ route('list60.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error,
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                    return;
                }

                const modal = bootstrap.Modal.getInstance(document.getElementById('addToList60Modal'));
                if (modal) {
                    modal.hide();
                }

                if (element) {
                    const iconElement = element.querySelector('i.ti-list-check');
                    if (iconElement) {
                        element.className = 'text-success';
                        element.removeAttribute('href');
                        element.removeAttribute('onclick');
                    }
                }

                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: data.success,
                    customClass: {
                        confirmButton: 'btn btn-success'
                    }
                });
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ha ocurrido un error al procesar la solicitud',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            });
        }

    </script>
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
                    <h5 class="modal-title">Añadir estado emocional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateSentimentForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="sentiment_id" class="form-label">Estado emocional</label>
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
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if (auth()->user()->currentTeam?->hasModule('list60'))
    <div class="modal fade" id="addToList60Modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('app.list60_add_modal_title') }}: <span id="addToList60ContactName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addToList60Form">
                    <div class="modal-body">
                        <input type="hidden" id="list60_contact_id" name="contact_id">

                        @if (auth()->user()->hasRole('admin'))
                            <div class="mb-3">
                                <label for="list60_responsible_id" class="form-label">{{ __('Responsible') }} (*)</label>
                                <select id="list60_responsible_id" name="responsible_id" class="form-select" required>
                                    @foreach ($list60TeamUsers as $userId => $userName)
                                        <option value="{{ $userId }}" @selected($userId === auth()->id())>{{ $userName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="mb-3">
                                <label class="form-label">{{ __('Responsible') }}</label>
                                <p class="mb-0 text-body">{{ auth()->user()->name }}</p>
                            </div>
                        @endif

                        <div class="mb-3">
                            <x-module-categories-select
                                id="list60_category_ids"
                                name="list60_category_ids[]"
                                :label="__('Categories')"
                                moduleKey="contacts"
                                :multiple="true"
                                :allowEmpty="true"
                                :showNull="false"
                            />
                        </div>

                        <div class="mb-0">
                            <label for="list60_notes" class="form-label">{{ __('Notes') }}</label>
                            <textarea id="list60_notes" name="notes" class="form-control" rows="8" placeholder="{{ __('app.list60_notes_placeholder') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.list60_add_confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

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
                            <label for="file" class="form-label">Archivo</label>
                            <input type="file" class="form-control" id="file" name="file" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> -->
                        <button type="submit" class="btn btn-primary">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush
