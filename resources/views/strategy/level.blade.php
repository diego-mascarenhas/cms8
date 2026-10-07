@extends('layouts/layoutMaster')

@section('title', __('app.weekly_plan_strategy_edit_title', ['level' => $currentLevel, 'title' => $currentStep['title'] ?? '']))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('app.weekly_plan_strategy_edit_title', ['level' => $currentLevel, 'title' => $currentStep['title'] ?? '']) }}</h4>
            @if (!empty($currentStep['tip']))
                <p class="text-muted mb-0">{{ $currentStep['tip'] }}</p>
            @endif
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('strategy.index') }}" class="btn btn-label-secondary">
                <i class="ti ti-target me-1"></i>{{ __('app.weekly_plan_strategy_link') }}
            </a>
            @if (!empty($canAdvance))
                <form method="POST" action="{{ route('strategy.advance') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary waves-effect waves-light">
                        <i class="ti ti-arrow-up me-1"></i>{{ __('app.weekly_plan_strategy_advance') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (is_array($currentStep) && !empty($currentStep['fields']))
        <div class="card mb-4 border border-primary">
            <div class="card-body">
                <form method="POST" action="{{ route('strategy.update') }}">
                    @csrf
                    <div class="row g-3">
                        @foreach ($currentStep['fields'] as $field)
                            <div class="col-12">
                                <label class="form-label" for="strategy-{{ $field['key'] }}">{{ $field['label'] }}</label>
                                <textarea
                                    id="strategy-{{ $field['key'] }}"
                                    name="strategy[{{ $field['key'] }}]"
                                    class="form-control @error('strategy.'.$field['key']) is-invalid @enderror"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="{{ __('app.weekly_plan_strategy_field_placeholder', ['field' => $field['label']]) }}"
                                >{{ old('strategy.'.$field['key'], $field['value'] ?? '') }}</textarea>
                                @error('strategy.'.$field['key'])
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>{{ __('app.weekly_plan_strategy_save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
