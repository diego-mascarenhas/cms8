@extends('layouts/layoutMaster')

@section('title', 'Configuración del negocio')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Ajustes/</span> Configuración del negocio</h4>
        <p class="text-muted">Configura los datos de tu negocio paso a paso. Los datos se guardan al cambiar de paso.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i> Volver a Ajustes
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        @livewire('settings.business-config-wizard', ['team' => $team])
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('livewire:init', function() {
    function initBusinessWizardSelect2() {
        $('.select2-select').each(function() {
            var $el = $(this);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
            $el.select2({
                placeholder: $el.data('placeholder') || 'Seleccionar',
                allowClear: true,
                width: '100%'
            });
        });
    }
    function initBusinessWizardFlatpickr() {
        var el = document.getElementById('business-wizard-birth-date');
        if (!el || typeof flatpickr === 'undefined') return;
        if (el._flatpickr) {
            el._flatpickr.destroy();
        }
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            allowInput: false,
            onChange: function(selectedDates, dateStr) {
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }
    initBusinessWizardSelect2();
    initBusinessWizardFlatpickr();
    Livewire.hook('morph.updated', function() {
        setTimeout(initBusinessWizardSelect2, 0);
        setTimeout(initBusinessWizardFlatpickr, 0);
    });
});
</script>
@endpush
@endsection
