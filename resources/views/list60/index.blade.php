@extends('layouts/layoutMaster')

@section('title', __('app.list60'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
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

    <div class="card">
        <!-- <div class="card-header border-bottom">
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('contact.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                        <i class="ti ti-plus me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Añadir cliente</span>
                    </a>
                </div>
            </div>
        </div> -->
        <div class="card-body">
            {{ $dataTable->table() }}
        </div>
    </div>

    <!-- Assign Responsible Modal -->
    <div class="modal fade" id="assignResponsibleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar responsable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="list60_id">
                    <div class="mb-3">
                        <label class="form-label">Responsable</label>
                        <select id="responsible_select" class="form-select"></select>
                    </div>
                    <div>
                        <label class="form-label">Próximo contacto</label>
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('today')">Hoy</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('d1')">+1 día</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('d2')">+2 días</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('d3')">+3 días</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('d4')">+4 días</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('d5')">+5 días</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('w1')">+1 semana</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('w2')">+2 semanas</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickDate('m1')">+1 mes</button>
                        </div>
                        <input type="date" id="date_next_input" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="saveAssignment()">Guardar</button>
                </div>
            </div>
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

@push('scripts')
    <script>
        function openAssignModal(id, currentId) {
            document.getElementById('list60_id').value = id;
            const select = document.getElementById('responsible_select');
            select.innerHTML = '<option value="">Cargando...</option>';
            // Prefill date from current row if present
            const row = document.querySelector(`[data-entry-id='${id}']`);
            const dateInput = document.getElementById('date_next_input');
            if (row) {
                const dateCell = row.querySelector('[data-field="date_next"]');
                if (dateCell && dateCell.dataset.value) {
                    dateInput.value = dateCell.dataset.value;
                } else {
                    dateInput.value = '';
                }
            } else {
                dateInput.value = '';
            }

            fetch('/api/team-users?roles=admin,collaborator,employee')
                .then(r => r.json())
                .then(data => {
                    select.innerHTML = '';
                    data.users.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id; opt.textContent = u.name + ' (' + u.role + ')';
                        if (currentId && String(currentId) === String(u.id)) { opt.selected = true; }
                        select.appendChild(opt);
                    });
                });

            new bootstrap.Modal(document.getElementById('assignResponsibleModal')).show();
        }

        function saveAssignment() {
            const id = document.getElementById('list60_id').value;
            const responsibleId = document.getElementById('responsible_select').value;
            const dateNext = document.getElementById('date_next_input').value;
            fetch(`/list60/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ responsible_id: responsibleId, date_next: dateNext })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    location.reload();
                } else {
                    toastr.error(resp.error || 'Error al asignar responsable');
                }
            });
        }

        function formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function quickDate(code) {
            const input = document.getElementById('date_next_input');
            const base = new Date();
            switch (code) {
                case 'today':
                    input.value = formatDate(base);
                    break;
                case 'd1': base.setDate(base.getDate() + 1); input.value = formatDate(base); break;
                case 'd2': base.setDate(base.getDate() + 2); input.value = formatDate(base); break;
                case 'd3': base.setDate(base.getDate() + 3); input.value = formatDate(base); break;
                case 'd4': base.setDate(base.getDate() + 4); input.value = formatDate(base); break;
                case 'd5': base.setDate(base.getDate() + 5); input.value = formatDate(base); break;
                case 'w1': base.setDate(base.getDate() + 7); input.value = formatDate(base); break;
                case 'w2': base.setDate(base.getDate() + 14); input.value = formatDate(base); break;
                case 'm1': base.setMonth(base.getMonth() + 1); input.value = formatDate(base); break;
            }
        }
        function deleteRecord(id, element) {
            event.preventDefault();
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¿Deseas eliminar este contacto de la Lista de 60?",
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
                    fetch("{{ route('list60.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
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
    </script>
@endpush
