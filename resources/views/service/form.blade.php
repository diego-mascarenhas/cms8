@extends('layouts/layoutMaster')
@section('title', isset($data) ? __('Edit Service') : __('Create Service'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Services') }}/</span>
            {{ isset($data) ? __('Edit') : __('Create') }}
        </h4>
        <p class="text-muted">{{ __('Track your clients\' services') }}</p>
    </div>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

@if(session('success'))
<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif

<form id="serviceForm" method="POST" action="{{ isset($data) ? route('service.update', $data->id) : route('service.store') }}">
        @csrf
        @if(isset($data))
            @method('PUT')
            <input type="hidden" name="id" value="{{ $data->id }}">
        @endif

        <!-- Basic Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Basic Information') }}</h5>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-12 col-md-6">
                        <x-team-users-select
                            id="responsible_id"
                            label="Asesor"
                            :selected="old('responsible_id', $data->responsible_id ?? auth()->id())"
                            show-null="false"
                        />
                    </div>

                    <div class="col-12 col-md-6">
                        @php
                            $selectedCategoryId = (string) old('category_id', isset($data) ? ($data->category_id ?? '') : '');
                            $serviceCategoryOptions = collect($categoryOptions ?? []);
                        @endphp
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="category_id" class="form-label mb-0 me-1">{{ __('Category') }}</label>
                                @can('viewAny', \App\Models\Category::class)
                                    <span class="d-inline-flex align-items-center lh-1">
                                        @livewire(\App\Livewire\ModuleCategoriesManagerModal::class, [
                                            'moduleKey' => 'services',
                                            'linkedSelectId' => 'category_id',
                                        ], key('service-form-cat-mgr-services'))
                                    </span>
                                @endcan
                            </div>
                            <select
                                id="category_id"
                                name="category_id"
                                class="form-select select2-service-category @error('category_id') is-invalid @enderror"
                                data-placeholder="{{ __('Uncategorized') }}"
                                data-allow-clear="true"
                                data-module-key="services"
                                data-empty-text="{{ __('Uncategorized') }}"
                                data-show-empty-option="1"
                                data-allow-empty-select="1"
                            >
                                <option value="">{{ __('Uncategorized') }}</option>
                                @foreach ($serviceCategoryOptions->groupBy(fn ($option) => $option['group'] ?? '') as $groupLabel => $groupOptions)
                                    @if ($groupLabel !== '')
                                        <optgroup label="{{ $groupLabel }}">
                                    @endif
                                    @foreach ($groupOptions as $categoryOption)
                                        <option value="{{ $categoryOption['id'] }}" {{ $selectedCategoryId === (string) $categoryOption['id'] ? 'selected' : '' }}>
                                            {{ $categoryOption['name'] }}
                                        </option>
                                    @endforeach
                                    @if ($groupLabel !== '')
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                            @error('category_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                            <small class="text-muted d-block mt-1">{{ __('Required for a recurring purchase so the projection can group it.') }}</small>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <x-client-select
                            id="enterprise_id"
                            label="{{ __('Empresa') }} (*)"
                            :selected="old('enterprise_id', isset($data) ? $data->enterprise_id : ($enterprise_id ?? ''))"
                            :allowNull="false"
                        />
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="subscription_id" class="form-label mb-0">{{ __('Suscripción') }}</label>
                            </div>
                            <select id="subscription_id" name="subscription_id" class="form-select select2-subscription" data-allow-clear="true" data-placeholder="{{ __('Subscripción local') }}">
                                <option value="" {{ old('subscription_id', isset($data) ? $data->subscription_id : '') === '' ? 'selected' : '' }}>{{ __('Subscripción local') }}</option>
                                @php($serviceSyncOptions = $serviceSyncs ?? $stripeSubscriptions ?? collect())
                                @foreach($serviceSyncOptions as $serviceSync)
                                        <option value="{{ $serviceSync->id }}" {{ old('subscription_id', isset($data) ? $data->subscription_id : '') == $serviceSync->id ? 'selected' : '' }}>
                                            {{ $serviceSync->customer_name ?: $serviceSync->customer_email }} — {{ $serviceSync->plan_name ?: '—' }} ({{ $serviceSync->status ?? '—' }})
                                        </option>
                                    @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">
                                {{ __('Suscripciones de clientes en Stripe.') }}
                                @can('access-billing-modules')
                                <a href="{{ route('subscription.index') }}" target="_blank" rel="noopener">{{ __('Ver todas') }}</a>
                                @endcan
                            </small>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="operation" class="form-label mb-0">{{ __('Operación') }}</label>
                            </div>
                            <select id="operation" name="operation" class="select2 form-select" required data-placeholder="{{ __('Operación') }}">
                                @php($selectedOperation = old('operation', isset($data) ? $data->operation : 'buy'))
                                <option value="buy" {{ $selectedOperation === 'buy' ? 'selected' : '' }}>{{ __('Compra') }}</option>
                                <option value="sell" {{ $selectedOperation === 'sell' ? 'selected' : '' }}>{{ __('Venta') }}</option>
                            </select>
                            <small class="text-muted d-block mt-1">{{ __('A recurring purchase is a proforma until the supplier invoice for that month is recorded.') }}</small>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="status" class="form-label mb-0">{{ __('Status') }}</label>
                            </div>
                            <select id="status" name="status" class="select2 form-select" required data-placeholder="{{ __('Select Status') }}">
                                @php($selectedStatus = (string) old('status', isset($data) ? $data->status : '4'))
                                <option value="">{{ __('Select Status') }}</option>
                                <option value="1" {{ $selectedStatus === '1' ? 'selected' : '' }}>Suspendido</option>
                                <option value="2" {{ $selectedStatus === '2' ? 'selected' : '' }}>Suspender</option>
                                <option value="3" {{ $selectedStatus === '3' ? 'selected' : '' }}>Activar</option>
                                <option value="4" {{ $selectedStatus === '4' ? 'selected' : '' }}>Activo</option>
                                <option value="5" {{ $selectedStatus === '5' ? 'selected' : '' }}>Migrar</option>
                                <option value="6" {{ $selectedStatus === '6' ? 'selected' : '' }}>Migrando</option>
                                <option value="7" {{ $selectedStatus === '7' ? 'selected' : '' }}>Delegar</option>
                                <option value="8" {{ $selectedStatus === '8' ? 'selected' : '' }}>Analizar</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', isset($data) ? $data->description : '') }}</textarea>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="frequency" class="form-label">{{ __('Period') }}</label>
                            <select id="frequency" name="frequency" class="select2 form-select">
                                @php($selectedFrequency = (string) old('frequency', isset($data) ? $data->frequency : 1))
                                @foreach (\App\Models\Service::frequencyOptions() as $months => $label)
                                    <option value="{{ $months }}" {{ $selectedFrequency === (string) $months ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Financial Information') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="currency_id" class="form-label">{{ __('Currency') }} ({{ __('optional') }})</label>
                            <select id="currency_id" name="currency_id" class="select2 form-select" data-allow-clear="true" data-placeholder="{{ __('Select Currency') }}">
                                @php($selectedCurrencyId = (string) old('currency_id', isset($data) ? ($data->currency_id ?? '') : ''))
                                <option value="">{{ __('Select Currency') }}</option>
                                @foreach (($currencies ?? collect()) as $currency)
                                    <option value="{{ $currency->id }}" {{ $selectedCurrencyId === (string) $currency->id ? 'selected' : '' }}>
                                        {{ $currency->code }} - {{ $currency->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('If not selected, system default will be used') }}</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="price" class="form-label">{{ __('Price') }}</label>
                            <input type="number" id="price" name="price" class="form-control" step="0.01" value="{{ isset($data) ? $data->price : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="discount" class="form-label">{{ __('Discount') }} (%)</label>
                            <input type="number" id="discount" name="discount" class="form-control" step="1" max="30" value="{{ isset($data) ? $data->discount : '0' }}">
                            <small class="text-muted">{{ __('Maximum discount allowed: 30%') }}</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="next_billing" class="form-label">{{ __('Next Billing Date') }}</label>
                            <input type="text" id="next_billing" name="next_billing" class="form-control flatpickr-date" value="{{ isset($data) && $data->next_billing ? $data->next_billing->format('Y-m-d') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expires_at" class="form-label">{{ __('Expiration Date') }}</label>
                            <input type="text" id="expires_at" name="expires_at" class="form-control flatpickr-date" value="{{ isset($data) && $data->expires_at ? $data->expires_at->format('Y-m-d') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

<!-- Submit Buttons -->
<div class="row mt-4">
    <div class="col-md-12 text-start">
        <button type="submit" form="serviceForm" class="btn btn-primary">{{ __('Save') }}</button>
        <a href="{{ route('service-list') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(function () {
        'use strict';

        // Initialize Select2 (skip fields that init themselves)
        $('.select2').not('.select2-service-category').not('.select2-subscription').not('.select2-client-enterprise').each(function () {
            var $this = $(this);
            if ($this.hasClass('select2-hidden-accessible') || $this.attr('data-module-key')) {
                return;
            }
            $this.select2({
                placeholder: $this.data('placeholder') || 'Select',
                allowClear: String($this.data('allow-clear')) === 'true',
                dropdownParent: $(document.body),
                width: '100%'
            });
        });

        var $subscriptionSelect = $('#subscription_id');
        if ($subscriptionSelect.length && $.fn.select2 && ! $subscriptionSelect.hasClass('select2-hidden-accessible')) {
            $subscriptionSelect.select2({
                placeholder: $subscriptionSelect.data('placeholder') || @json(__('Subscripción local')),
                allowClear: true,
                width: '100%',
                dropdownParent: $(document.body),
            });
        }

        var $categorySelect = $('#category_id');
        if ($categorySelect.length && $.fn.select2) {
            var categoryPlaceholder = $categorySelect.data('empty-text') || @json(__('Uncategorized'));

            function initServiceCategorySelect() {
                if ($categorySelect.hasClass('select2-hidden-accessible')) {
                    $categorySelect.select2('destroy');
                }

                $categorySelect.select2({
                    placeholder: categoryPlaceholder,
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(document.body),
                });
            }

            initServiceCategorySelect();

            if (typeof Livewire !== 'undefined' && typeof Livewire.on === 'function') {
                Livewire.on('module-categories-refreshed', function (event) {
                    var detail = Array.isArray(event) ? event[0] : event;
                    if (! detail || detail.selectId !== 'category_id') {
                        return;
                    }

                    var moduleOptionsUrl = @json(route('categories.module-options'));
                    var categoryRequests = ['services', 'projects'].map(function (moduleKey) {
                        return $.getJSON(moduleOptionsUrl, { module_key: moduleKey }).then(
                            function (data) { return data; },
                            function () { return { groups: [] }; }
                        );
                    });

                    $.when.apply($, categoryRequests).done(function () {
                            var payloads = Array.prototype.slice.call(arguments);
                            var prevVal = $categorySelect.val();
                            $categorySelect.empty();
                            $categorySelect.append(new Option(categoryPlaceholder, '', false, false));

                            payloads.forEach(function (data) {
                                (data.groups || []).forEach(function (g) {
                                    if (g.type === 'option') {
                                        $categorySelect.append(new Option(g.label, String(g.id), false, false));
                                    } else if (g.type === 'group') {
                                        var og = $('<optgroup>').attr('label', g.label);
                                        (g.options || []).forEach(function (o) {
                                            og.append(new Option(o.label, String(o.id), false, false));
                                        });
                                        $categorySelect.append(og);
                                    }
                                });
                            });

                            initServiceCategorySelect();

                            if (prevVal && $categorySelect.find('option[value="' + String(prevVal).replace(/"/g, '\\"') + '"]').length) {
                                $categorySelect.val(String(prevVal)).trigger('change');
                            } else {
                                $categorySelect.val(null).trigger('change');
                            }
                        });
                });
            }
        }

        // Initialize Flatpickr
        $('.flatpickr-date').flatpickr({
            dateFormat: 'Y-m-d',
            allowInput: true
        });
    });
</script>
@endsection
