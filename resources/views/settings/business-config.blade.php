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
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
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
            $el.off('change.businessWizardSelect2');
            $el.select2({
                placeholder: $el.data('placeholder') || 'Seleccionar',
                allowClear: true,
                width: '100%'
            });
            var prop = this.id === 'business-wizard-country' ? 'config.country' : (this.id === 'business-wizard-language' ? 'config.language' : null);
            if (prop) {
                $el.on('change.businessWizardSelect2', function() {
                    var value = $(this).val();
                    var root = this.closest('[wire\\:id]');
                    if (root) {
                        var comp = window.Livewire.find(root.getAttribute('wire:id'));
                        if (comp) comp.set(prop, value || '');
                    }
                });
            }
        });
    }
    function initBusinessWizardFlatpickr() {
        var el = document.getElementById('business-wizard-birth-date');
        if (!el || typeof flatpickr === 'undefined') return;
        if (el._flatpickr) {
            var fp = el._flatpickr;
            var altInDoc = fp.altInput && document.body.contains(fp.altInput);
            if (altInDoc) {
                if (el.value) {
                    fp.setDate(el.value, false);
                }
                return;
            }
            fp.destroy();
            delete el._flatpickr;
        }
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd-m-Y',
            locale: 'es',
            allowInput: false,
            defaultDate: el.value || null,
            onChange: function(selectedDates, dateStr) {
                var root = el.closest('[wire\\:id]');
                if (root) {
                    var comp = window.Livewire.find(root.getAttribute('wire:id'));
                    if (comp) comp.set('config.birth_date', dateStr || '');
                }
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
