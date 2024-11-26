@extends('layouts/layoutMaster')

@section('title', __('app.clients'))

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
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Clientes/</span>
                {{ isset($data->id) ? 'Editar' : 'Crear' }}</h4>
            <p class="text-muted">Gestiona y personaliza a tus clientes</p>
        </div>
    </div>

    <!-- Modern -->
    <div class="row">
        <!-- Modern Icons Wizard -->
        <div class="col-12 mb-4">
            <div class="bs-stepper wizard-icons wizard-modern wizard-modern-icons-example mt-2">
                <div class="bs-stepper-header">
                    <div class="step" data-target="#account-details-modern">
                        <button type="button" class="step-trigger">
                            <span class="bs-stepper-icon">
                                <i class="ti ti-building"></i>
                            </span>
                            <span class="bs-stepper-label">Detalle de la Empresa</span>
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
                </div>
                <div class="bs-stepper-content">
                    <form id="clientForm" class="card-body" action="{{ route('client.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="id" value="{{ $data->id ?? '' }}">
                        <!-- Account Details -->
                        <div id="account-details-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Detalle de la Empresa</h6>
                                <small>Datos de la empresa</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <x-input-general id="name" label="Nombre de la empresa (*)"
                                        value="{{ old('name', $data->name ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-enterprise-status-select :value="old('status_id', $data->status_id ?? '')" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="email" label="Email (*)"
                                        value="{{ old('email', $data->email ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="website" label="Website"
                                        value="{{ old('website', $data->website ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="phone" label="Teléfono"
                                        value="{{ old('phone', $data->phone ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="whatsapp" label="WhatsApp"
                                        value="{{ old('whatsapp', $data->whatsapp ?? '') }}" />
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                    <button type="reset" class="btn btn-label-secondary"
                                        onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
                                </div>
                            </div>
                        </div>
                        <!-- Personal Info -->
                        <div id="personal-info-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Información Personal</h6>
                                <small>Ingresa la información del contacto principal</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <x-input-general id="contact_name" label="Nombre del contacto"
                                        value="{{ old('contact_name', $data->contact_name ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    <x-input-general id="contact_last_name" label="Apellido del contacto"
                                        value="{{ old('contact_last_name', $data->contact_last_name ?? '') }}" />
                                </div>
                                <div class="col-sm-6">
                                    Idioma
                                </div>
                                <div class="col-sm-6">
                                    País
                                </div>
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                    <button type="reset" class="btn btn-label-secondary"
                                        onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
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
                                        onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
                                </div>
                            </div>
                        </div>
                        <!-- Social Links -->
                        <div id="social-links-modern" class="content">
                            <div class="content-header mb-3">
                                <h6 class="mb-0">Redes Sociales</h6>
                                <small>Ingresa el link de tus redes sociales</small>
                            </div>
                            <div class="row g-3">
                                @foreach (['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'pinterest', 'snapchat'] as $network)
                                    <div class="col-sm-6">
                                        <x-input-general id="{{ $network }}" label="{{ ucfirst($network) }}"
                                            value="{{ old($network, $data->{$network} ?? '') }}" />
                                    </div>
                                @endforeach
                                <div class="col-12 d-flex">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                                    <button type="reset" class="btn btn-label-secondary"
                                        onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
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
    fetch(`/client/end-action/${trackingId}`, {
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
        document.getElementById('clientForm').addEventListener('submit', function(e) {
            e.preventDefault();
            endActionTracking(trackingId);
            this.submit();
        });

        window.addEventListener('beforeunload', function() {
            endActionTracking(trackingId);
        });

        const cancelButton = document.querySelector('button[onclick*="client-list"]');
        if (cancelButton) {
            cancelButton.addEventListener('click', function(e) {
                e.preventDefault();
                endActionTracking(trackingId);
                location.href = '{{ route('client-list') }}';
            });
        }
    }
});
</script>
@endpush