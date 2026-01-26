@extends('layouts/layoutMaster')

@section('title', isset($contentFieldConfig) ? __('app.Edit Field Configuration') : __('app.Create Field Configuration'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ isset($contentFieldConfig) ? __('app.Edit Field Configuration') : __('app.Create Field Configuration') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ isset($contentFieldConfig) ? route('content-field-configs.update', $contentFieldConfig->id) : route('content-field-configs.store') }}">
                    @csrf
                    @if(isset($contentFieldConfig))
                        @method('PUT')
                    @endif

                    <div class="mb-3">
                        <label for="section_category_id" class="form-label">{{ __('app.Section') }} (*)</label>
                        <select id="section_category_id" name="section_category_id" class="form-select select2" required>
                            <option value="">{{ __('app.Select Section') }}</option>
                            @foreach($sectionCategories as $sectionCategory)
                                <option value="{{ $sectionCategory->id }}"
                                    {{ old('section_category_id', $contentFieldConfig->section_category_id ?? $sectionId ?? '') == $sectionCategory->id ? 'selected' : '' }}>
                                    {{ $sectionCategory->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('section_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="field_key" class="form-label">{{ __('app.Field Key') }} (*)</label>
                            <input type="text" class="form-control @error('field_key') is-invalid @enderror"
                                id="field_key" name="field_key"
                                value="{{ old('field_key', $contentFieldConfig->field_key ?? '') }}" required
                                pattern="[a-z0-9_]+" title="{{ __('app.Only lowercase letters, numbers and underscores') }}">
                            @error('field_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('app.Used as the key in the data JSON field') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="field_type" class="form-label">{{ __('app.Field Type') }} (*)</label>
                            <select id="field_type" name="field_type" class="form-select @error('field_type') is-invalid @enderror" required>
                                <option value="">{{ __('app.Select Type') }}</option>
                                @foreach($fieldTypes as $type => $label)
                                    <option value="{{ $type }}"
                                        {{ old('field_type', $contentFieldConfig->field_type ?? '') == $type ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('field_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="field_label" class="form-label">{{ __('app.Field Label') }} (*)</label>
                        <input type="text" class="form-control @error('field_label') is-invalid @enderror"
                            id="field_label" name="field_label"
                            value="{{ old('field_label', $contentFieldConfig->field_label ?? '') }}" required>
                        @error('field_label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="required" name="required" value="1"
                                    {{ old('required', isset($contentFieldConfig) && $contentFieldConfig->required) ? 'checked' : '' }}>
                                <label class="form-check-label" for="required">{{ __('app.Required') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                    {{ old('is_active', isset($contentFieldConfig) && $contentFieldConfig->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">{{ __('app.Active') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="order" class="form-label">{{ __('app.Order') }}</label>
                            <input type="number" class="form-control" id="order" name="order"
                                value="{{ old('order', $contentFieldConfig->order ?? 0) }}" min="0">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">{{ __('app.Save') }}</button>
                        <a href="{{ route('content-field-configs.index') }}" class="btn btn-outline-secondary">{{ __('app.Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#section_category_id, #field_type').select2();
});
</script>
@endsection
