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

    <div class="card mb-4">
        <h5 class="card-header">{{ isset($data->id) ? 'Editar Cliente' : 'Crear Cliente' }}</h5>
        <form class="card-body" id="clientForm" action="{{ route('client.store') }}" method="POST">
            @csrf
            <input type="hidden" name="id" value="{{ $data->id ?? '' }}">
            <input type="hidden" name="link_subscription_id" value="{{ $data->link_subscription_id ?? '' }}">

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <x-input-general id="name" label="Nombre de la empresa (*)"
                        value="{{ old('name', $data->name ?? '') }}" />
                </div>
                <div class="col-12 col-md-6">
                    <x-input-select
                        id="type_id"
                        label="Tipo de empresa (*)"
                        :options="$enterpriseTypeOptions"
                        value="{{ old('type_id', $data->type_id ?? 1) }}"
                        :required="true"
                        :allowClear="false"
                    />
                </div>
                <div class="col-12 col-md-6">
                    <x-input-general id="code" label="Stripe Customer ID (cus_...)"
                        value="{{ old('code', $data->code ?? '') }}" />
                </div>
                <div class="col-12 col-md-6">
                    <x-input-select
                        id="referred_by"
                        :label="__('Referrer enterprise')"
                        :options="$referrerEnterpriseOptions"
                        value="{{ old('referred_by', $referredBySelectValue ?? '') }}"
                        :placeholder="__('No referrer')"
                        :helpText="__('Referrer select help')"
                    />
                </div>
                <div class="col-md-6">
                    <x-input-general id="email" label="Email (*)"
                        value="{{ old('email', $data->email ?? '') }}" />
                </div>
                <div class="col-md-6">
                    <x-input-general id="website" label="{{ __('Website') }}"
                        value="{{ old('website', $data->website ?? '') }}" />
                </div>
                <div class="col-md-6">
                    <x-input-general id="phone" label="{{ __('Phone') }}"
                        value="{{ old('phone', $data->phone ?? '') }}" />
                </div>
                <div class="col-md-6">
                    <x-input-general id="whatsapp" label="WhatsApp"
                        value="{{ old('whatsapp', $data->whatsapp ?? '') }}" />
                </div>
                <div class="col-md-6">
                    <x-enterprise-status-select
                        :enterprise-type-id="\App\Models\EnterpriseStatus::resolveFormEnterpriseTypeId(old('type_id', $data->type_id ?? 1))"
                        :value="old('status_id', $data->status_id ?? '')" />
                </div>
            </div>

            <div class="pt-4">
                <div class="col-12 d-flex">
                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                    <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ isset($data->id) && $data->id ? route('client.show', $data->id) : route('client-list') }}'">Cancelar</button>
                </div>
            </div>
        </form>
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
