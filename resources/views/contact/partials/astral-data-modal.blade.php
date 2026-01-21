<!-- Modal para completar datos astrológicos -->
<div class="modal fade" id="astralDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-stars me-2"></i>
                    Completar Datos Astrológicos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="astralDataForm" method="POST" action="{{ route('contact.update-astral-data', $data->id) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-4">
                        Para un cálculo exacto del ascendente y diseño humano, necesitamos la fecha, hora y lugar de nacimiento.
                    </p>

                    <div class="row g-3">
                        <!-- Fecha y Hora en una sola fila -->
                        <div class="col-md-6">
                            <label for="birth_date" class="form-label">
                                <i class="ti ti-calendar ti-xs me-1"></i>
                                Fecha de Nacimiento
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="birth_date"
                                name="birth_date"
                                value="{{ old('birth_date', $data->birthday ? \Carbon\Carbon::parse($data->birthday)->format('Y-m-d') : '') }}"
                                placeholder="Selecciona la fecha"
                                autocomplete="off"
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="birth_time" class="form-label">
                                <i class="ti ti-clock ti-xs me-1"></i>
                                Hora de Nacimiento
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="birth_time"
                                name="birth_time"
                                value="{{ optional($data->astralProfile)->birth_time ? \Carbon\Carbon::parse(optional($data->astralProfile)->birth_time)->format('H:i') : '' }}"
                                placeholder="Selecciona la hora"
                                autocomplete="off"
                            >
                        </div>

                        <!-- Ciudad de Nacimiento -->
                        <div class="col-12">
                            <label for="birth_city" class="form-label">
                                <i class="ti ti-map-pin ti-xs me-1"></i>
                                Ciudad de Nacimiento
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="birth_city"
                                name="birth_city"
                                value="{{ old('birth_city', optional($data->astralProfile)->birth_city) }}"
                                placeholder="Ej: Madrid, España"
                            >
                        </div>

                        {{-- Coordenadas (ocultas temporalmente) --}}
                        {{-- <div class="col-md-6">
                            <label for="birth_latitude" class="form-label">
                                Latitud <small class="text-muted">(opcional)</small>
                            </label>
                            <input
                                type="number"
                                step="0.0000001"
                                class="form-control"
                                id="birth_latitude"
                                name="birth_latitude"
                                value="{{ old('birth_latitude', optional($data->astralProfile)->birth_latitude) }}"
                                placeholder="40.4168"
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="birth_longitude" class="form-label">
                                Longitud <small class="text-muted">(opcional)</small>
                            </label>
                            <input
                                type="number"
                                step="0.0000001"
                                class="form-control"
                                id="birth_longitude"
                                name="birth_longitude"
                                value="{{ old('birth_longitude', optional($data->astralProfile)->birth_longitude) }}"
                                placeholder="-3.7038"
                            >
                        </div> --}}
                    </div>

                    <div class="alert alert-info mt-3 mb-0" role="alert">
                        <i class="ti ti-info-circle me-1"></i>
                        <strong>Nota:</strong> Estos datos son necesarios para cálculos astrológicos precisos (ascendente, diseño humano exacto, casas astrológicas).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Guardar y Recalcular
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endpush

@push('scripts')
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
$(document).ready(function() {
    var birthDatePicker = null;
    var birthTimePicker = null;
    var originalValues = {};

    $('#astralDataModal').on('shown.bs.modal', function() {
        // Destroy existing pickers if they exist
        if (birthDatePicker) birthDatePicker.destroy();
        if (birthTimePicker) birthTimePicker.destroy();

        // Initialize Flatpickr for birth_date (date picker)
        birthDatePicker = flatpickr('#birth_date', {
            dateFormat: 'Y-m-d',
            allowInput: true,
            altInput: true,
            altFormat: 'd-m-Y',
            locale: 'es',
            maxDate: 'today',
            disableMobile: true
        });

        // Initialize Flatpickr for birth_time (time picker)
        birthTimePicker = flatpickr('#birth_time', {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            time_24hr: true,
            allowInput: true,
            disableMobile: true
        });

        // Store current values
        originalValues = {
            birth_date: $('#birth_date').val(),
            birth_time: $('#birth_time').val(),
            birth_city: $('#birth_city').val()
        };

        console.log('Modal opened with values:', originalValues);
    });

    $('#astralDataForm').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var url = form.attr('action');
        var submitBtn = form.find('button[type="submit"]');
        var originalText = submitBtn.html();

        // Get current form data
        var formData = {};
        form.serializeArray().forEach(function(item) {
            formData[item.name] = item.value;
        });

        // If fields are empty but had original values, restore them
        ['birth_date', 'birth_time', 'birth_city'].forEach(function(field) {
            if (!formData[field] && originalValues[field]) {
                formData[field] = originalValues[field];
            }
        });

        console.log('Submitting data:', formData);

        // Reset previous errors
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').remove();

        // Disable submit button
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando...');

        $.ajax({
            type: "POST",
            url: url,
            data: formData,
            success: function(response) {
                $('#astralDataModal').modal('hide');
                toastr.success(response.message || 'Datos guardados correctamente. Recalculando perfil astrológico...');

                // Reload page after 1 second to show updated profile
                setTimeout(function() {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html(originalText);

                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        var input = $('#' + key);
                        input.addClass('is-invalid');
                        input.after('<div class="invalid-feedback">' + value[0] + '</div>');
                    });
                    toastr.error('Por favor corrige los errores del formulario.');
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Error al guardar los datos. Intenta nuevamente.');
                }
            }
        });
    });
});
</script>
@endpush
