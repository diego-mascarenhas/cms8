@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('Crear tu negocio'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-misc.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('content')
<div class="container-xxl container-p-y">
    <div class="mb-3">
        <h2 class="mb-1">{{ __('Crear tu negocio') }}</h2>
        <p class="text-muted mb-0">
            {{ __('Configura los datos de tu negocio en pocos pasos: datos básicos, información personal, dirección, redes sociales y revisión.') }}
        </p>
    </div>
    @livewire('landing.business-wizard', ['token' => $token ?? null])
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script>
document.addEventListener('livewire:init', function() {
    function initBusinessWizardSelect2() {
        if (typeof $ === 'undefined') return;
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
            el._flatpickr.destroy();
        }
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            allowInput: false,
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
