@extends('layouts/layoutMaster')


@section('title', __('app.contacts'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/form-layouts.js') }}"></script>
    <script src="{{ asset('assets/js/cms-form-client.js') }}"></script>
@endsection

@section('content')
    @php
        // Toggle category selector mode for contacts.
        // false = single select (same visual behavior as product form)
        // true  = multiple select
        $useMultipleCategories = true;
        $contactCategoryIds = isset($data->categories) ? $data->categories->pluck('id')->toArray() : old('categories', []);
        if (!is_array($contactCategoryIds)) {
            $contactCategoryIds = [];
        }
        $contactCategorySelected = $useMultipleCategories
            ? $contactCategoryIds
            : (old('categories.0') ?? ($contactCategoryIds[0] ?? ''));
    @endphp

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contactos/</span>
                {{ isset($data->id) ? 'Editar' : 'Crear' }}</h4>
            <p class="text-muted">Gestiona y personaliza a tus clientes</p>
        </div>
        @if(isset($data->id))
            @can('delete', $data)
                <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
                    <form action="{{ route('contact.destroy', $data->id) }}" method="POST"
                          onsubmit="return confirm('¿Seguro que deseas eliminar este contacto? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="ti ti-trash me-1"></i> Eliminar contacto
                        </button>
                    </form>
                </div>
            @endcan
        @endif
    </div>


    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <i class="ti ti-alert-circle"></i> Se encontraron errores en el formulario
            @if (!app()->environment('production'))
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        @if (!str_contains($error, 'country'))
                            <li>{{ $error }}</li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if(request()->has('debug') && config('app.debug'))
        <div class="alert" role="alert" style="background-color: black; color: #00ff00; font-family: monospace; border: 1px solid #00ff00;">
            <h4 class="alert-heading" style="color: #00ff00;"><i class="ti ti-bug"></i> Debug Information</h4>
            <p>This information is visible because the 'debug' parameter is present in the URL and the application is running locally.</p>
            <hr style="border-color: #00ff00;">
            <pre class="mb-0" style="white-space: pre-wrap; word-break: break-all; background-color: black; color: #00ff00; border: none;">
{{ var_export($data->toArray(), true) }}
            </pre>
        </div>
    @endif

    <!-- Modern -->
    <div class="row">
        <!-- Modern Icons Wizard -->
        <div class="col-12 mb-4">
            @if(!isset($data->id))
            <div class="card mb-4">
                <div class="card-body">
            @endif
            @if(isset($data->id))
            <div class="bs-stepper wizard-icons wizard-modern wizard-modern-icons-example mt-2">
                <div class="bs-stepper-header">
                    <div class="step" data-target="#personal-info-modern">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-icon">
                                <i class="ti ti-user"></i>
                            </span>
                            <span class="bs-stepper-label">Información Personal</span>
                        </button>
                    </div>
                    <div class="line">
                        <i class="ti ti-chevron-right"></i>
                    </div>
                    <div class="step" data-target="#social-links-modern">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-icon">
                                <i class="ti ti-share"></i>
                            </span>
                            <span class="bs-stepper-label">Redes Sociales</span>
                        </button>
                    </div>
                    <div class="line">
                        <i class="ti ti-chevron-right"></i>
                    </div>
                    <div class="step" data-target="#account-details-modern">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-icon">
                                <i class="ti ti-building"></i>
                            </span>
                            <span class="bs-stepper-label">Datos de la Empresa</span>
                        </button>
                    </div>
                    <div class="line">
                        <i class="ti ti-chevron-right"></i>
                    </div>
                    <div class="step" data-target="#address-modern">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-icon">
                                <i class="ti ti-map-pin"></i>
                            </span>
                            <span class="bs-stepper-label">Domicilio</span>
                        </button>
                    </div>
                </div>
                <div class="bs-stepper-content">
            @endif
                    <form action="{{ isset($data->id) ? route('contact.update', $data->id) : route('contact.store') }}"
                        method="POST">
                        @csrf
                        @if (isset($data->id))
                            @method('PUT')
                        @endif

                        <!-- Personal Info -->
                        <div id="personal-info-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Información Personal</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <x-input-general id="name" label="Nombre (*)"
                                        value="{{ old('name', $data->name ?? '') }}" />
                                </div>
                                <div class="col-sm-4">
                                    <x-input-general id="surname" label="Apellidos"
                                        value="{{ old('surname', $data->surname ?? '') }}" />
                                </div>
                                <div class="col-sm-4">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $data->email ?? '') }}">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-4">
                                    <label for="phone" class="form-label">{{ __('Teléfono') }}</label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $data->phone ?? '') }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-4">
                                    <x-team-users-select
                                        id="responsible_id"
                                        label="Asesor"
                                        :selected="old('responsible_id', $data->responsible_id ?? auth()->id())"
                                        show-null="false"
                                    />
                                </div>
                                <div class="col-sm-4">
                                    <label for="status_id" class="form-label">Tipo de contacto</label>
                                    <x-input-select id="status_id" :options="$enterpriseStatuses" :value="old('status_id', $data->status_id ?? '')" />
                                </div>
                                <div class="col-sm-4" style="display: none;">
                                    <label for="user_id" class="form-label">Usuario vinculado</label>
                                    <select id="user_id" name="contact[user_id]" class="form-select select2">
                                        <option value="">-- Seleccionar usuario --</option>
                                        @foreach(\App\Models\User::orderBy('name')->get() as $user)
                                            <option value="{{ $user->id }}" {{ (old('contact.user_id', $data->user_id ?? '') == $user->id) ? 'selected' : '' }}>
                                                {{ $user->name }} ({{ $user->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted" style="font-size: 0.7rem;">Vincular este contacto con un usuario del sistema</small>
                                </div>
                                <div class="col-sm-4">
                                    <x-input-date id="birthday" label="Cumpleaños"
                                        value="{{ old('birthday', $data->birthday ?? '') }}" />
                                </div>
                                <div class="col-sm-4">
                                    <x-language-select :value="$data->language ?? old('language')" />
                                </div>
                                <div class="col-sm-4">
                                    <x-country-select :value="$data->country ?? old('country')" />
                                    @error('country')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-12">
                                    <x-module-categories-select
                                        id="categories"
                                        name="categories[]"
                                        errorKey="categories"
                                        :label="$useMultipleCategories ? 'Categorías' : 'Categoría (*)'"
                                        :selected="$contactCategorySelected"
                                        moduleKey="contacts"
                                        :multiple="$useMultipleCategories"
                                        :allowEmpty="true"
                                        emptyText="Seleccione una categoría"
                                    />
                                </div>
                                <div class="col-sm-12">
                                    <x-input-textarea id="profile" label="{{ __('Perfil') }}" rows="3"
                                        value="{{ old('profile', $data->profile ?? '') }}" />
                                </div>
                                @php
                                    $waAssistantEnabled = true;
                                    $oldAssistant = old('chat_assistant_ai_enabled');
                                    if ($oldAssistant !== null) {
                                        $waAssistantEnabled = filter_var($oldAssistant, FILTER_VALIDATE_BOOLEAN);
                                    } elseif (isset($data->id) && is_object($data->data ?? null) && property_exists($data->data, 'chat_assistant_ai_enabled')) {
                                        $waAssistantEnabled = filter_var($data->data->chat_assistant_ai_enabled, FILTER_VALIDATE_BOOLEAN);
                                    }
                                @endphp
                                <div class="col-sm-12">
                                    <input type="hidden" name="chat_assistant_ai_enabled" value="0">
                                    <div class="form-check form-switch mb-0">
                                        <input type="checkbox" class="form-check-input" id="chat_assistant_ai_enabled" name="chat_assistant_ai_enabled" value="1" @checked($waAssistantEnabled)>
                                        <label class="form-check-label" for="chat_assistant_ai_enabled">{{ __('Contact assistant auto-reply (WhatsApp)') }}</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">{{ __('When off, inbound WhatsApp messages from this number will not receive automatic assistant replies. The team assistant-replies setting must still be on.') }}</small>
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                    <button type="reset" class="btn btn-label-secondary"
                                        onclick="location.href='{{ route('contact-list') }}'">Cancelar</button>
                                </div>
                            </div>
                        </div>

                        <!-- Social Links -->
                        <div id="social-links-modern" class="content">
                            <div class="content-header mb-3 d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Redes Sociales</h6>
                                    <small>Link a redes sociales</small>
                                </div>
                                <button type="button" id="add-social-link" class="btn btn-primary btn-sm add-sentiment-btn">
                                    + Añadir red social
                                </button>
                            </div>
                            <div class="row g-3 mt-2 mb-2" id="social-links-container">
                                @if(isset($data->sources) && $data->sources->count() > 0)
                                    @foreach ($data->sources as $source)
                                        <x-social-link :source="$source" :socialSources="$socialSources" />
                                    @endforeach
                                @endif
                            </div>
                            <div class="col-12 d-flex">
                                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                <button type="reset" class="btn btn-label-secondary"
                                    onclick="location.href='{{ route('contact-list') }}'">Cancelar</button>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const container = document.getElementById('social-links-container');

                                document.getElementById('add-social-link').addEventListener('click', function() {
                                    const newRow = document.createElement('div');
                                    newRow.classList.add('row', 'mb-2');
                                    newRow.innerHTML = `
                                        <div class="col-sm-4">
                                            <label for="social_network_new" class="form-label">Red Social</label>
                                            <select id="social_network_new" class="form-select" name="source_id[]">
                                                <option value="">Selecciona una red social</option>
                                                @foreach ($socialSources as $socialSource)
                                                    <option value="{{ $socialSource['id'] }}">{{ $socialSource['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="social_link_new" class="form-label">Enlace de la red social</label>
                                            <input type="text" class="form-control" id="social_link_new" name="source_value[]" value="">
                                        </div>
                                        <div class="col-sm-2">
                                            <br><button type="button" class="btn btn-danger remove-social-link">Eliminar</button>
                                        </div>
                                    `;
                                    container.appendChild(newRow);
                                });

                                container.addEventListener('click', function(e) {
                                    if (e.target.matches('.remove-social-link')) {
                                        const row = e.target.closest('.row');
                                        container.removeChild(row);
                                    }
                                });
                            });
                        </script>

                        <!-- Account Details -->
                        <div id="account-details-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Datos de la Empresa</h6>
                                <small>Elegí una empresa del equipo o completá los campos para crear o actualizar datos de empresa.</small>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <label class="form-label" for="enterprise_enterprise_id">Empresa existente</label>
                                    <select name="enterprise[enterprise_id]" id="enterprise_enterprise_id" class="form-select">
                                        <option value="">— Sin seleccionar (usar campos de abajo) —</option>
                                        @foreach ($teamEnterprises ?? collect() as $ent)
                                            <option value="{{ $ent->id }}" @selected((string) old('enterprise.enterprise_id', isset($data->id) ? ($data->current_enterprise_id ?? '') : '') === (string) $ent->id)>
                                                {{ $ent->name }}@if ($ent->code) ({{ $ent->code }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted d-block mt-1">Si elegís una empresa, al guardar se vincula el contacto a esa empresa y no se crea otra desde los campos de abajo.</small>
                                </div>
                            </div>
                            <div id="enterprise-manual-fields" class="row g-3">
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[name]" name="enterprise[name]"
                                        label="Nombre de la empresa"
                                        value="{{ old('enterprise.name', $data->currentEnterprise->name ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[code]" name="enterprise[code]"
                                        label="Código de Stripe"
                                        value="{{ old('enterprise.code', $data->currentEnterprise->code ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[website]" name="enterprise[website]" label="Website"
                                        value="{{ old('enterprise.website', $data->currentEnterprise->website ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[email]" name="enterprise[email]" label="Email"
                                        value="{{ old('enterprise.email', $data->currentEnterprise->email ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[phone]" name="enterprise[phone]" label="Teléfono"
                                        value="{{ old('enterprise.phone', $data->currentEnterprise->phone ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[whatsapp]" name="enterprise[whatsapp]"
                                        label="WhatsApp"
                                        value="{{ old('enterprise.whatsapp', $data->currentEnterprise->whatsapp ?? '') }}" />
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                    <button type="reset" class="btn btn-label-secondary"
                                        onclick="location.href='{{ route('contact-list') }}'">Cancelar</button>
                                </div>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    var sel = document.getElementById('enterprise_enterprise_id');
                                    var manual = document.getElementById('enterprise-manual-fields');
                                    if (!sel || !manual) return;
                                    function toggleManualEnterpriseFields() {
                                        var disabled = !!sel.value;
                                        manual.querySelectorAll('input, textarea, select').forEach(function (el) {
                                            if (!el.name || el.name.indexOf('enterprise[') !== 0) return;
                                            el.disabled = disabled;
                                        });
                                        manual.classList.toggle('opacity-50', disabled);
                                    }
                                    sel.addEventListener('change', toggleManualEnterpriseFields);
                                    toggleManualEnterpriseFields();
                                });
                            </script>
                        </div>
                        <!-- Address -->
                        <div id="address-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Domicilio</h6>
                                <small>Domicilio de la empresa</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <x-input-general id="address" label="Dirección"
                                        value="{{ old('address', $data->address ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="postal_code" label="Código Postal"
                                        value="{{ old('postal_code', $data->postal_code ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="locality" label="Población"
                                        value="{{ old('locality', $data->locality ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="province" label="Provincia"
                                        value="{{ old('province', $data->province ?? '') }}" />
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                    <button type="reset" class="btn btn-label-secondary"
                                        onclick="location.href='{{ route('contact-list') }}'">Cancelar</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-between mt-4">
                            <button type="submit" class="btn btn-success btn-submit d-none">Guardar</button>
                        </div>
                    </form>
            @if(isset($data->id))
                </div>
            </div>
            @endif
            @if(!isset($data->id))
                </div>
            </div>
            @endif
        </div>
        <!-- /Modern Icons Wizard -->
    </div>
@endsection

@push('scripts')
    <script>
        function endActionTracking(trackingId) {
            fetch(`/contact/end-action/${trackingId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Acción finalizada correctamente');
                    } else {
                        console.error('Error al finalizar el seguimiento de la acción');
                    }
                }).catch(error => {
                    console.error('Error:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const trackingId = {{ $trackingId ?? 'null' }};

            if (trackingId) {
                document.getElementById('contactForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    endActionTracking(trackingId);
                    this.submit();
                });

                window.addEventListener('beforeunload', function() {
                    endActionTracking(trackingId);
                });

                const cancelButton = document.querySelector('button[onclick*="contact-list"]');
                if (cancelButton) {
                    cancelButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        endActionTracking(trackingId);
                        location.href = '{{ route('contact-list') }}';
                    });
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('social-links-container');

            // Delegar el evento de clic al contenedor
            container.addEventListener('click', function(e) {
                // Verificar si el elemento clicado es un botón de eliminar
                if (e.target.matches('.remove-social-link')) {
                    // Encontrar la fila correspondiente
                    const row = e.target.closest('.row'); // Cambia esto si la estructura es diferente
                    container.removeChild(row); // Eliminar la fila
                }
            });
        });
    </script>
    @if(!isset($data->id))
    <style>
        /* Show content in creation mode (no stepper) */
        #personal-info-modern {
            display: block !important;
        }
        #social-links-modern,
        #account-details-modern,
        #address-modern {
            display: none !important;
        }
    </style>
    @endif
@endpush






