<!-- Edit User Modal -->
<div class="modal fade" id="editUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-edit-user">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-4">
                    <h3 class="mb-2">Editar Información del Usuario</h3>
                    <p class="text-muted">La actualización de los detalles del usuario recibirá una auditoría de
                        privacidad.</p>
                </div>
                <form id="editUserForm" class="row g-3" action="{{ route('contact.update', $data->id) }}"
                    method="POST">
                    @csrf
                    @method('PUT')
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="modalEditUserFirstName">Nombre</label>
                        <input type="text" id="modalEditUserFirstName" name="name" class="form-control"
                            placeholder="John" value="{{ $data->name }}" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="modalEditUserStatus">Estado</label>
                        <x-input-select-array id="status_id" :options="$enterpriseStatuses" :value="''"
                            placeholder="Tipo de contacto" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="modalEditUserEmail">Email</label>
                        <input type="email" id="modalEditUserEmail" name="email" class="form-control"
                            placeholder="example@domain.com" value="{{ $data->email }}" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="modalEditUserPhone">Teléfono</label>
                        <div class="input-group">
                            <span class="input-group-text">US (+1)</span>
                            <input type="text" id="modalEditUserPhone" name="phone"
                                class="form-control phone-number-mask" placeholder="202 555 0111"
                                value="{{ $data->phone }}" />
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="modalEditUserLanguage">Idioma</label>
                        <select id="modalEditUserLanguage" name="language" class="select2 form-select">
                            <option value="en" {{ $data->language == 'en' ? 'selected' : '' }}>Inglés</option>
                            <option value="es" {{ $data->language == 'es' ? 'selected' : '' }}>Español</option>
                            <option value="fr" {{ $data->language == 'fr' ? 'selected' : '' }}>Francés</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="modalEditUserCountry">País</label>
                        <select id="modalEditUserCountry" name="country" class="select2 form-select"
                            data-allow-clear="true">
                            <option value="">Seleccionar</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->code }}"
                                    {{ $data->country == $country->code ? 'selected' : '' }}>{{ $country->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-primary me-sm-3 me-1">Enviar</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal"
                            aria-label="Close">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--/ Edit User Modal -->

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded');

            // Evento para cuando se abre el modal
            $('#editUser').on('show.bs.modal', function (e) {
                console.log('Modal is about to be shown');
                const modal = $(this);
                console.log('Modal content:', modal.html());
                
                // Log de los valores iniciales del formulario
                const form = modal.find('#editUserForm');
                if (form.length) {
                    console.log('Form found in modal');
                    const formData = new FormData(form[0]);
                    console.log('Initial form data:');
                    for (let [key, value] of formData.entries()) {
                        console.log(key, value);
                    }
                } else {
                    console.error('Form not found in modal');
                }
            });

            // Evento para el clic en el botón de envío
            $(document).on('click', '#editUserForm button[type="submit"]', function(e) {
                e.preventDefault();
                console.log('Submit button clicked');
                $('#editUserForm').submit();
            });

            // Evento para el envío del formulario
            $(document).on('submit', '#editUserForm', function(e) {
                e.preventDefault();
                console.log('Form submit event triggered');
                
                const form = $(this);
                const formData = new FormData(form[0]);
                
                console.log('Form data being submitted:');
                for (let [key, value] of formData.entries()) {
                    console.log(key, value);
                }

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        console.log('AJAX request successful:', response);
                        if (response.success) {
                            toastr.success(response.message);
                            $('#editUser').modal('hide');
                            updatePageContent(response.data);
                        } else {
                            toastr.error(response.message || 'An error occurred');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX request failed:', status, error);
                        console.log('Response:', xhr.responseText);
                        toastr.error('An error occurred. Please try again.');
                    }
                });
            });
        });

        function updatePageContent(data) {
            console.log('Updating page content with:', data);
            // Actualiza los elementos relevantes de la página con los nuevos datos
            if (data.name) $('.user-info h4').text(data.name);
            // Actualiza otros campos según sea necesario
        }
    </script>
@endpush
