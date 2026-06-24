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
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    <script src="{{ asset('vendors/data-tables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
    <script src="{{ asset('vendors/fullcalendar/lib/moment.min.js') }}"></script>
    <script src="{{ asset('js/moment/' . app()->getLocale() . '.js') }}"></script>
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
            {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
        </div>
    </div>

    <!-- Next contact date modal -->
    <div class="modal fade" id="assignResponsibleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Próximo contacto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="list60_id">
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
                        <input type="text" id="date_next_input" class="form-control" autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="saveDateNext()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Outreach modal -->
    <div class="modal fade" id="list60OutreachModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('app.list60_outreach_modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="outreach_list60_id">
                    <div class="mb-3">
                        <p class="mb-1 fw-medium" id="outreach_contact_name"></p>
                        <div id="outreach_contact_categories" class="d-flex flex-wrap gap-1"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">{{ __('app.list60_outreach_channel_label') }}</label>
                        <div class="btn-group" role="group" id="outreach_channel_group">
                            <input type="radio" class="btn-check" name="outreach_channel" id="outreach_channel_whatsapp" value="whatsapp" autocomplete="off">
                            <label class="btn btn-outline-primary" for="outreach_channel_whatsapp">
                                <i class="ti ti-brand-whatsapp me-1"></i>{{ __('app.list60_outreach_channel_whatsapp') }}
                            </label>
                            <input type="radio" class="btn-check" name="outreach_channel" id="outreach_channel_email" value="email" autocomplete="off">
                            <label class="btn btn-outline-primary" for="outreach_channel_email">
                                <i class="ti ti-mail me-1"></i>{{ __('app.list60_outreach_channel_email') }}
                            </label>
                        </div>
                    </div>
                    <div class="mb-3" id="outreach_subject_wrap">
                        <label class="form-label" for="outreach_subject">{{ __('app.list60_outreach_subject') }}</label>
                        <input type="text" id="outreach_subject" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="outreach_message">{{ __('app.list60_outreach_message') }}</label>
                        <textarea id="outreach_message" class="form-control" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="outreach_send_btn" onclick="sendOutreach()">
                        <i class="ti ti-send me-1"></i>{{ __('app.list60_outreach_send') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush

@php
    $jsLocale = \App\Support\ApplicationLocales::javascriptLocale();
    $flatpickrAltFormat = match ($jsLocale) {
        'en' => 'Y-m-d',
        'es', 'fr' => 'd-m-Y',
        'de' => 'd.m.Y',
        'it', 'pt' => 'd/m/Y',
        default => 'Y-m-d',
    };
@endphp

@push('scripts')
    <script>
        let dateNextPicker = null;

        function loadFlatpickrLocale(locale, callback) {
            if (locale === 'en') {
                callback();
                return;
            }

            const script = document.createElement('script');
            script.src = `https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/${locale}.js`;
            script.onload = callback;
            script.onerror = () => callback();
            document.head.appendChild(script);
        }

        function initDateNextPicker() {
            if (dateNextPicker) {
                return;
            }

            const locale = @json($jsLocale);
            const altFormat = @json($flatpickrAltFormat);

            loadFlatpickrLocale(locale, function () {
                if (locale !== 'en' && flatpickr.l10ns[locale]) {
                    flatpickr.localize(flatpickr.l10ns[locale]);
                }

                dateNextPicker = flatpickr('#date_next_input', {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    altInput: true,
                    altFormat: altFormat,
                    monthSelectorType: 'static',
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initDateNextPicker();

            document.querySelectorAll('input[name="outreach_channel"]').forEach(function (input) {
                input.addEventListener('change', toggleOutreachSubject);
            });

            document.addEventListener('click', function (event) {
                const outreachTrigger = event.target.closest('.js-list60-outreach');
                if (outreachTrigger) {
                    event.preventDefault();
                    openOutreachModal(
                        outreachTrigger.dataset.list60Id,
                        outreachTrigger.dataset.contactName,
                        outreachTrigger.dataset.categories,
                        outreachTrigger.dataset.canWhatsapp === '1',
                        outreachTrigger.dataset.canEmail === '1'
                    );
                    return;
                }

                const dateTrigger = event.target.closest('.js-list60-date');
                if (dateTrigger) {
                    event.preventDefault();
                    openDateModal(dateTrigger.dataset.list60Id);
                }
            });
        });

        function toggleOutreachSubject() {
            const channel = document.querySelector('input[name="outreach_channel"]:checked')?.value;
            const subjectWrap = document.getElementById('outreach_subject_wrap');
            if (subjectWrap) {
                subjectWrap.style.display = channel === 'email' ? '' : 'none';
            }
        }

        function openOutreachModal(list60Id, contactName, categoriesJson, canWhatsapp, canEmail) {
            document.getElementById('outreach_list60_id').value = list60Id;
            document.getElementById('outreach_contact_name').textContent = contactName || '';
            document.getElementById('outreach_subject').value = '';
            document.getElementById('outreach_message').value = '';

            const categoriesEl = document.getElementById('outreach_contact_categories');
            categoriesEl.innerHTML = '';
            let categories = [];
            try {
                categories = categoriesJson ? JSON.parse(categoriesJson) : [];
            } catch (e) {
                categories = [];
            }
            if (Array.isArray(categories) && categories.length > 0) {
                categories.forEach(function (name) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-label-primary';
                    badge.textContent = name;
                    categoriesEl.appendChild(badge);
                });
            }

            const whatsappInput = document.getElementById('outreach_channel_whatsapp');
            const emailInput = document.getElementById('outreach_channel_email');
            const whatsappLabel = document.querySelector('label[for="outreach_channel_whatsapp"]');
            const emailLabel = document.querySelector('label[for="outreach_channel_email"]');

            whatsappInput.disabled = !canWhatsapp;
            emailInput.disabled = !canEmail;
            if (whatsappLabel) {
                whatsappLabel.classList.toggle('disabled', !canWhatsapp);
            }
            if (emailLabel) {
                emailLabel.classList.toggle('disabled', !canEmail);
            }

            if (canWhatsapp) {
                whatsappInput.checked = true;
            } else if (canEmail) {
                emailInput.checked = true;
            }

            toggleOutreachSubject();
            new bootstrap.Modal(document.getElementById('list60OutreachModal')).show();
        }

        function sendOutreach() {
            const list60Id = document.getElementById('outreach_list60_id').value;
            const channel = document.querySelector('input[name="outreach_channel"]:checked')?.value;
            const message = document.getElementById('outreach_message').value.trim();
            const subject = document.getElementById('outreach_subject').value.trim();
            const sendBtn = document.getElementById('outreach_send_btn');

            if (!channel) {
                toastr.error(@json(__('app.list60_outreach_error_invalid_channel')));
                return;
            }

            if (!message) {
                toastr.error(@json(__('validation.required', ['attribute' => __('app.list60_outreach_message')])));
                return;
            }

            sendBtn.disabled = true;

            fetch(`/list60/${list60Id}/send-outreach`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    channel: channel,
                    message: message,
                    subject: subject || null
                })
            })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok) {
                    const errorMessage = data.message
                        || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                        || data.error
                        || @json(__('whatsapp.send.error.generic'));
                    throw new Error(errorMessage);
                }
                return data;
            })
            .then((data) => {
                bootstrap.Modal.getInstance(document.getElementById('list60OutreachModal'))?.hide();
                Swal.fire({
                    icon: 'success',
                    title: @json(__('Success')),
                    text: data.success,
                    customClass: {
                        confirmButton: 'btn btn-success'
                    },
                    buttonsStyling: false
                }).then(function () {
                    location.reload();
                });
            })
            .catch((error) => {
                toastr.error(error.message || @json(__('whatsapp.send.error.generic')));
            })
            .finally(() => {
                sendBtn.disabled = false;
            });
        }

        function openDateModal(id) {
            document.getElementById('list60_id').value = id;
            const row = document.getElementById(id);
            const dateCell = row ? row.querySelector('[data-field="date_next"]') : null;
            const dateValue = dateCell && dateCell.dataset.value ? dateCell.dataset.value : '';

            if (dateNextPicker) {
                if (dateValue) {
                    dateNextPicker.setDate(dateValue, true);
                } else {
                    dateNextPicker.clear();
                }
            }

            new bootstrap.Modal(document.getElementById('assignResponsibleModal')).show();
        }

        function saveDateNext() {
            const id = document.getElementById('list60_id').value;
            const dateNext = dateNextPicker ? dateNextPicker.input.value : document.getElementById('date_next_input').value;

            fetch(`/list60/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ date_next: dateNext })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    location.reload();
                } else {
                    toastr.error(resp.error || 'Error al actualizar la fecha');
                }
            });
        }

        function updateList60Responsible(select) {
            const id = select.dataset.list60Id;
            const responsibleId = select.value;
            const previousValue = select.dataset.previousValue || responsibleId;

            fetch(`/list60/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ responsible_id: responsibleId })
            })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) {
                    select.value = previousValue;
                    toastr.error(resp.error || 'Error al asignar responsable');
                }
            })
            .catch(() => {
                select.value = previousValue;
                toastr.error('Error al asignar responsable');
            });
        }

        function formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function quickDate(code) {
            const base = new Date();
            switch (code) {
                case 'today':
                    break;
                case 'd1': base.setDate(base.getDate() + 1); break;
                case 'd2': base.setDate(base.getDate() + 2); break;
                case 'd3': base.setDate(base.getDate() + 3); break;
                case 'd4': base.setDate(base.getDate() + 4); break;
                case 'd5': base.setDate(base.getDate() + 5); break;
                case 'w1': base.setDate(base.getDate() + 7); break;
                case 'w2': base.setDate(base.getDate() + 14); break;
                case 'm1': base.setMonth(base.getMonth() + 1); break;
            }

            if (dateNextPicker) {
                dateNextPicker.setDate(formatDate(base), true);
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
