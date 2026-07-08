@extends('layouts/layoutMaster')

@section('title', isset($data->id) ? __('Edit campaign') : __('Create campaign'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Paid Ads') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Configure objective, budget, targeting, creatives and platforms') }}</p>
    </div>
    <a href="{{ route('paid-ads.index') }}" class="btn btn-label-secondary mt-3 mt-md-0">{{ __('Back to list') }}</a>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form action="{{ isset($data->id) ? route('paid-ads.update', $data->id) : route('paid-ads.store') }}" method="POST">
    @csrf
    @isset($data->id)@method('PUT')@endisset

    <div class="row">
        <div class="col-lg-8">
            {{-- Step 1: Basics --}}
            <div class="card mb-4">
                <h5 class="card-header">{{ __('Objective & budget') }}</h5>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="name">{{ __('Campaign name') }} (*)</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $data->name ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="objective">{{ __('Objective') }} (*)</label>
                            <select id="objective" name="objective" class="form-select" required>
                                @foreach ($objectives as $objective)
                                    <option value="{{ $objective->value }}" @selected(old('objective', $data->objective?->value) === $objective->value)>{{ $objective->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="budget_type">{{ __('Budget type') }} (*)</label>
                            <select id="budget_type" name="budget_type" class="form-select" required>
                                <option value="daily" @selected(old('budget_type', $data->budget_type ?? 'daily') === 'daily')>{{ __('Daily') }}</option>
                                <option value="lifetime" @selected(old('budget_type', $data->budget_type ?? '') === 'lifetime')>{{ __('Lifetime') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="budget_amount">{{ __('Budget') }}</label>
                            <input type="number" step="0.01" min="0" id="budget_amount" name="budget_amount" class="form-control" value="{{ old('budget_amount', $data->budget_amount ?? '') }}">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label" for="currency">{{ __('Cur.') }}</label>
                            <input type="text" maxlength="3" id="currency" name="currency" class="form-control text-uppercase" value="{{ old('currency', $data->currency ?? 'EUR') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="start_at">{{ __('Start date') }}</label>
                            <input type="date" id="start_at" name="start_at" class="form-control" value="{{ old('start_at', optional($data->start_at)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="end_at">{{ __('End date') }}</label>
                            <input type="date" id="end_at" name="end_at" class="form-control" value="{{ old('end_at', optional($data->end_at)->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2: Targeting --}}
            <div class="card mb-4">
                <h5 class="card-header">{{ __('Targeting') }}</h5>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="targeting_locations">{{ __('Locations') }}</label>
                            <input type="text" id="targeting_locations" name="targeting[locations]" class="form-control" placeholder="{{ __('e.g. Spain, Madrid') }}" value="{{ old('targeting.locations', data_get($data->targeting, 'locations')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="age_min">{{ __('Min age') }}</label>
                            <input type="number" min="13" max="99" id="age_min" name="targeting[age_min]" class="form-control" value="{{ old('targeting.age_min', data_get($data->targeting, 'age_min')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="age_max">{{ __('Max age') }}</label>
                            <input type="number" min="13" max="99" id="age_max" name="targeting[age_max]" class="form-control" value="{{ old('targeting.age_max', data_get($data->targeting, 'age_max')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="interests">{{ __('Interests') }}</label>
                            <input type="text" id="interests" name="targeting[interests]" class="form-control" value="{{ old('targeting.interests', data_get($data->targeting, 'interests')) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 3: Creative --}}
            <div class="card mb-4">
                <h5 class="card-header">{{ __('Creative') }}</h5>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="headline">{{ __('Headline') }}</label>
                            <input type="text" id="headline" name="creative[headline]" class="form-control" value="{{ old('creative.headline', data_get($data->creative, 'headline')) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="body">{{ __('Ad copy') }}</label>
                            <textarea id="body" name="creative[body]" rows="3" class="form-control">{{ old('creative.body', data_get($data->creative, 'body')) }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="url">{{ __('Destination URL') }}</label>
                            <input type="url" id="url" name="creative[url]" class="form-control" placeholder="https://" value="{{ old('creative.url', data_get($data->creative, 'url')) }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Step 4: Platforms --}}
            <div class="card mb-4">
                <h5 class="card-header">{{ __('Platforms') }}</h5>
                <div class="card-body">
                    @forelse ($connections as $connection)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="platforms[]" value="{{ $connection->id }}" id="conn-{{ $connection->id }}"
                                @checked(in_array($connection->id, old('platforms', $selectedConnectionIds), true))>
                            <label class="form-check-label" for="conn-{{ $connection->id }}">
                                <i class="{{ $connection->platform->icon() }} me-1"></i>{{ $connection->platform->label() }}
                                <span class="text-muted d-block small">{{ $connection->ad_account_name ?? $connection->ad_account_id }}</span>
                            </label>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('No active connections.') }} <a href="{{ route('paid-ads.connections') }}">{{ __('Connect a platform') }}</a></p>
                    @endforelse
                </div>
            </div>

            {{-- Step 5: Audiences --}}
            <div class="card mb-4">
                <h5 class="card-header">{{ __('Audiences') }}</h5>
                <div class="card-body">
                    @forelse ($audiences as $audience)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="audiences[]" value="{{ $audience->id }}" id="aud-{{ $audience->id }}"
                                @checked(in_array($audience->id, old('audiences', $selectedAudienceIds), true))>
                            <label class="form-check-label" for="aud-{{ $audience->id }}">{{ $audience->name }}</label>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('No audiences yet.') }} <a href="{{ route('paid-ads.audiences.create') }}">{{ __('Create one') }}</a></p>
                    @endforelse
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    <a href="{{ route('paid-ads.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
