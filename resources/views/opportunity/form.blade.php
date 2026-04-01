@extends('layouts/layoutMaster')

@section('title', __('Opportunities'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('content')
@php($isEdit = isset($data->id))
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Opportunities') }}/</span> {{ $isEdit ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('CRM opportunities') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('opportunity.index') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Opportunity') }}</h5>
    <form class="card-body" method="POST" action="{{ $isEdit ? route('opportunity.update', $data->id) : route('opportunity.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">{{ __('Name') }} (*)</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $data->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contact_id">{{ __('Contact') }} (*)</label>
                <select name="contact_id" id="contact_id" class="form-select @error('contact_id') is-invalid @enderror" required>
                    <option value="">{{ __('Choose') }}</option>
                    @foreach ($contacts as $contact)
                        <option value="{{ $contact->id }}" @selected(old('contact_id', $data->contact_id) == $contact->id)>
                            {{ $contact->name }} {{ $contact->surname }} ({{ $contact->email }})
                        </option>
                    @endforeach
                </select>
                @error('contact_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="opened_at">{{ __('Opened') }} (*)</label>
                <input type="text" name="opened_at" id="opened_at" class="form-control flatpickr-opportunity-date @error('opened_at') is-invalid @enderror"
                    value="{{ old('opened_at', $data->opened_at instanceof \Carbon\Carbon ? $data->opened_at->format('Y-m-d') : ($data->opened_at ?? '')) }}" autocomplete="off" required>
                @error('opened_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="opportunity_stage_id">{{ __('Stage') }} (*)</label>
                <select name="opportunity_stage_id" id="opportunity_stage_id" class="form-select @error('opportunity_stage_id') is-invalid @enderror" required>
                    @foreach ($stages as $stage)
                        <option value="{{ $stage->id }}" @selected(old('opportunity_stage_id', $data->opportunity_stage_id) == $stage->id)>{{ $stage->localizedName() }}</option>
                    @endforeach
                </select>
                @error('opportunity_stage_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="responsible_id">{{ __('Responsible') }}</label>
                <select name="responsible_id" id="responsible_id" class="form-select">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(old('responsible_id', $data->responsible_id) == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="estimated_amount">{{ __('Estimated amount') }}</label>
                <input type="number" step="0.01" min="0" name="estimated_amount" id="estimated_amount" class="form-control"
                    value="{{ old('estimated_amount', $data->estimated_amount) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="currency_id">{{ __('Currency') }}</label>
                <select name="currency_id" id="currency_id" class="form-select">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected(old('currency_id', $data->currency_id) == $currency->id)>{{ $currency->code }} — {{ $currency->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="probability">{{ __('Probability') }} (%)</label>
                <input type="number" min="0" max="100" name="probability" id="probability" class="form-control"
                    value="{{ old('probability', $data->probability) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="expected_close_at">{{ __('Expected close') }}</label>
                <input type="text" name="expected_close_at" id="expected_close_at" class="form-control flatpickr-opportunity-date"
                    value="{{ old('expected_close_at', $data->expected_close_at instanceof \Carbon\Carbon ? $data->expected_close_at->format('Y-m-d') : ($data->expected_close_at ?? '')) }}" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="offering_kind">{{ __('Offering') }}</label>
                <select name="offering_kind" id="offering_kind" class="form-select">
                    <option value="none" @selected(old('offering_kind', $offeringKind) === 'none')>{{ __('None (text only)') }}</option>
                    <option value="product" @selected(old('offering_kind', $offeringKind) === 'product')>{{ __('Catalog product') }}</option>
                    <option value="service" @selected(old('offering_kind', $offeringKind) === 'service')>{{ __('Service') }}</option>
                </select>
            </div>
            <div class="col-md-6 offering-product d-none">
                <label class="form-label" for="product_id">{{ __('Product') }}</label>
                <select name="product_id" id="product_id" class="form-select">
                    <option value="">{{ __('Choose') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id', $productId) == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
                @error('product_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 offering-service d-none">
                <label class="form-label" for="service_id">{{ __('Service') }}</label>
                <select name="service_id" id="service_id" class="form-select">
                    <option value="">{{ __('Choose') }}</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(old('service_id', $serviceId) == $service->id)>#{{ $service->id }} — {{ \Illuminate\Support\Str::limit($service->description ?? '', 60) }}</option>
                    @endforeach
                </select>
                @error('service_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="offering_summary">{{ __('Offering summary') }}</label>
                <textarea name="offering_summary" id="offering_summary" class="form-control" rows="2">{{ old('offering_summary', $data->offering_summary) }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">{{ __('Description') }}</label>
                <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $data->description) }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">{{ __('Notes') }}</label>
                <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $data->notes) }}</textarea>
            </div>
        </div>
        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
            <a href="{{ route('opportunity.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#contact_id, #responsible_id, #opportunity_stage_id, #currency_id, #product_id, #service_id').select2({ width: '100%', allowClear: true });
    }
    function toggleOffering() {
        var v = document.getElementById('offering_kind').value;
        document.querySelectorAll('.offering-product').forEach(function (el) { el.classList.toggle('d-none', v !== 'product'); });
        document.querySelectorAll('.offering-service').forEach(function (el) { el.classList.toggle('d-none', v !== 'service'); });
    }
    document.getElementById('offering_kind').addEventListener('change', toggleOffering);
    toggleOffering();

    if (typeof flatpickr !== 'undefined') {
        var locale = @json(app()->getLocale());
        var altFmt = locale === 'en' ? 'Y-m-d' : 'd/m/Y';
        document.querySelectorAll('.flatpickr-opportunity-date').forEach(function (input) {
            flatpickr(input, {
                dateFormat: 'Y-m-d',
                allowInput: true,
                altInput: true,
                altFormat: altFmt,
                monthSelectorType: 'static'
            });
        });
    }
});
</script>
@endpush
