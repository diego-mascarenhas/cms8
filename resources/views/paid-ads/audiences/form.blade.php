@extends('layouts/layoutMaster')

@section('title', isset($data->id) ? __('Edit audience') : __('Create audience'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Audiences') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
    </div>
    <a href="{{ route('paid-ads.audiences.index') }}" class="btn btn-label-secondary mt-3 mt-md-0">{{ __('Back to list') }}</a>
</div>

@if ($errors->any())
<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form action="{{ isset($data->id) ? route('paid-ads.audiences.update', $data->id) : route('paid-ads.audiences.store') }}" method="POST">
    @csrf
    @isset($data->id)@method('PUT')@endisset
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">{{ __('Name') }} (*)</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $data->name ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="type">{{ __('Type') }} (*)</label>
                    <select id="type" name="type" class="form-select" required>
                        @foreach (['saved' => __('Saved'), 'custom' => __('Custom'), 'lookalike' => __('Lookalike'), 'retargeting' => __('Retargeting')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $data->type ?? 'saved') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="locations">{{ __('Locations') }}</label>
                    <input type="text" id="locations" name="targeting_rules[locations]" class="form-control" value="{{ old('targeting_rules.locations', data_get($data->targeting_rules, 'locations')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="interests">{{ __('Interests') }}</label>
                    <input type="text" id="interests" name="targeting_rules[interests]" class="form-control" value="{{ old('targeting_rules.interests', data_get($data->targeting_rules, 'interests')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="age_min">{{ __('Min age') }}</label>
                    <input type="number" min="13" max="99" id="age_min" name="targeting_rules[age_min]" class="form-control" value="{{ old('targeting_rules.age_min', data_get($data->targeting_rules, 'age_min')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="age_max">{{ __('Max age') }}</label>
                    <input type="number" min="13" max="99" id="age_max" name="targeting_rules[age_max]" class="form-control" value="{{ old('targeting_rules.age_max', data_get($data->targeting_rules, 'age_max')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="estimated_size">{{ __('Estimated size') }}</label>
                    <input type="number" min="0" id="estimated_size" name="estimated_size" class="form-control" value="{{ old('estimated_size', $data->estimated_size ?? '') }}">
                </div>
            </div>
            <div class="pt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('paid-ads.audiences.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </div>
    </div>
</form>
@endsection
