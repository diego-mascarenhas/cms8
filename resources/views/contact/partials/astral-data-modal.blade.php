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
                        Para un cálculo exacto del ascendente y diseño humano, necesitamos la hora y lugar de nacimiento.
                    </p>

                    <div class="row g-3">
                        <!-- Hora de Nacimiento -->
                        <div class="col-12">
                            <label for="birth_time" class="form-label">
                                <i class="ti ti-clock ti-xs me-1"></i>
                                Hora de Nacimiento
                            </label>
                            <input
                                type="time"
                                class="form-control"
                                id="birth_time"
                                name="birth_time"
                                value="{{ optional($data->astralProfile)->birth_time ? \Carbon\Carbon::parse(optional($data->astralProfile)->birth_time)->format('H:i') : '' }}"
                            >
                            <small class="text-muted">Hora exacta (formato 24h)</small>
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

                        <!-- Coordenadas (opcional, auto-completar si tienes API) -->
                        <div class="col-md-6">
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
                        </div>
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

@push('scripts')
<script>
$(document).ready(function() {
    // Store original values when modal opens
    var originalValues = {};

    $('#astralDataModal').on('show.bs.modal', function() {
        // Store current values
        originalValues = {
            birth_time: $('#birth_time').val(),
            birth_city: $('#birth_city').val(),
            birth_latitude: $('#birth_latitude').val(),
            birth_longitude: $('#birth_longitude').val()
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
        ['birth_time', 'birth_city', 'birth_latitude', 'birth_longitude'].forEach(function(field) {
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
