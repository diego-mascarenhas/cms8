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
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contactos/</span>
                {{ isset($data->id) ? 'Editar' : 'Crear' }}</h4>
            <p class="text-muted">Gestiona y personaliza a tus clientes</p>
        </div>
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
                            <span class="bs-stepper-icon"></span>
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
                                <small>Ingresa la información del contacto principal</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-8">
                                    <x-input-general id="name" label="Nombre (*)"
                                        value="{{ old('name', $data->name ?? '') }}" />
                                </div>
                                <div class="col-sm-4">
                                    <label for="status_id" class="form-label">Tipo de contacto</label>
                                    <x-input-select id="status_id" :options="$enterpriseStatuses" :value="old('status_id', $data->status_id ?? '')"
                                        placeholder="Selector de tipo de contacto" />
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
                                    <x-input-textarea id="profile" label="Profile" rows="3"
                                        value="{{ old('profile', $data->profile ?? '') }}" />
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
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Redes Sociales</h6>
                                <small>Ingresa el link de tus redes sociales</small>
                            </div>
                            <div class="row g-3" id="social-links-container">
                                @foreach ($data->sources as $source)
                                    <div class="col-sm-6">
                                        <label for="social_network_{{ $source->id }}" class="form-label">Red Social</label>
                                        <select id="social_network_{{ $source->id }}" class="form-select" name="sources[{{ $source->id }}]">
                                            <option value="">Selecciona una red social</option>
                                            <option value="whatsapp" {{ $source->id == 3 ? 'selected' : '' }}>WhatsApp</option>
                                            <option value="facebook" {{ $source->id == 4 ? 'selected' : '' }}>Facebook</option>
                                            <option value="instagram" {{ $source->id == 5 ? 'selected' : '' }}>Instagram</option>
                                            <option value="twitter" {{ $source->id == 6 ? 'selected' : '' }}>Twitter</option>
                                            <option value="linkedin" {{ $source->id == 7 ? 'selected' : '' }}>LinkedIn</option>
                                            <option value="youtube" {{ $source->id == 8 ? 'selected' : '' }}>YouTube</option>
                                            <option value="tiktok" {{ $source->id == 9 ? 'selected' : '' }}>TikTok</option>
                                            <option value="pinterest" {{ $source->id == 10 ? 'selected' : '' }}>Pinterest</option>
                                            <option value="snapchat" {{ $source->id == 11 ? 'selected' : '' }}>Snapchat</option>
                                            <option value="telegram" {{ $source->id == 12 ? 'selected' : '' }}>Telegram</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <x-input-general id="social_link_{{ $source->id }}" label="Enlace de la red social" value="{{ $source->pivot->value ?? '' }}" name="pivot_value[{{ $source->id }}]" />
                                    </div>
                                @endforeach
                            </div>
                            <div class="col-12 d-flex">
                                <button type="button" class="btn btn-secondary me-1" id="add-social-link">Agregar otra red social</button>
                            </div>
                            <div class="col-12 d-flex">
                                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                <button type="reset" class="btn btn-label-secondary"
                                    onclick="location.href='{{ route('contact-list') }}'">Cancelar</button>
                            </div>
                        </div>

                        <script>
                            document.getElementById('add-social-link').addEventListener('click', function() {
                                const container = document.getElementById('social-links-container');
                                const newInput = document.createElement('div');
                                newInput.classList.add('row', 'g-3');
                                newInput.innerHTML = `
                                    <div class="col-sm-6">
                                        <label for="social_network" class="form-label">Red Social</label>
                                        <select id="social_network" class="form-select">
                                            <option value="">Selecciona una red social</option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="facebook">Facebook</option>
                                            <option value="instagram">Instagram</option>
                                            <option value="twitter">Twitter</option>
                                            <option value="linkedin">LinkedIn</option>
                                            <option value="youtube">YouTube</option>
                                            <option value="tiktok">TikTok</option>
                                            <option value="pinterest">Pinterest</option>
                                            <option value="snapchat">Snapchat</option>
                                            <option value="telegram">Telegram</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <x-input-general id="social_link" label="Enlace de la red social" value="" />
                                    </div>
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-danger remove-social-link">Eliminar</button>
                                    </div>
                                `;
                                container.appendChild(newInput);

                                // Agregar evento para eliminar la fila
                                newInput.querySelector('.remove-social-link').addEventListener('click', function() {
                                    container.removeChild(newInput);
                                });
                            });
                        </script>
                        <!-- Account Details -->
                        <div id="account-details-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Detalle de la Empresa</h6>
                                <small>Datos de la empresa</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[name]" name="enterprise[name]"
                                        label="Nombre de la empresa"
                                        value="{{ old('enterprise.name', $data->enterprise->name ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[website]" name="enterprise[website]" label="Website"
                                        value="{{ old('enterprise.website', $data->enterprise->website ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[phone]" name="enterprise[phone]" label="Teléfono"
                                        value="{{ old('enterprise.phone', $data->enterprise->phone ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[email]" name="enterprise[email]" label="Email"
                                        value="{{ old('enterprise.email', $data->enterprise->email ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="enterprise[whatsapp]" name="enterprise[whatsapp]"
                                        label="WhatsApp"
                                        value="{{ old('enterprise.whatsapp', $data->enterprise->whatsapp ?? '') }}" />
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                    <button type="reset" class="btn btn-label-secondary"
                                        onclick="location.href='{{ route('contact-list') }}'">Cancelar</button>
                                </div>
                            </div>
                        </div>
                        <!-- Address -->
                        <div id="address-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Domicilio</h6>
                                <small>Ingresa tu domicilio</small>
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
                </div>
            </div>
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
    </script>
@endpush

