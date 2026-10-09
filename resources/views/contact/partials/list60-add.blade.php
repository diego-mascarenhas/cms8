@if (auth()->user()->currentTeam?->hasModule('list60'))
    @push('modals')
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
    @endpush

    @push('scripts')
        <script>
            $(function() {
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
            });

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
                        if (iconElement && element.classList.contains('btn')) {
                            element.className = 'btn btn-success waves-effect waves-light';
                            element.removeAttribute('onclick');
                            element.disabled = true;
                        } else if (iconElement) {
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
@endif
