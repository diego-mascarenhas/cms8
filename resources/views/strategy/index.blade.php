@extends('layouts/layoutMaster')

@section('title', __('Strategic Growth Framework'))

@section('content')
@php
    $steps = $steps ?? config('strategy.steps', []);
    $currentLevel = (int) ($currentLevel ?? 1);
    $currentStep = $currentStep ?? null;
    $strategyValues = $strategyValues ?? [];
    $stepsProgress = $stepsProgress ?? [];
    $canEdit = (bool) ($canEdit ?? false);
    $groupBorder = [
        'foundation' => 'border-success',
        'systems' => 'border-warning',
        'scale' => 'border-primary',
    ];
@endphp

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h2 class="mb-1">{{ config('strategy.title', 'Strategic Growth Framework') }}</h2>
            <p class="text-muted mb-0">{{ __('app.weekly_plan_strategy_current', ['level' => $currentLevel]) }}</p>
        </div>
        @if(!empty($canAdvance) && $canEdit)
            <form method="POST" action="{{ route('strategy.advance') }}" class="mt-3 mt-md-0">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-arrow-up me-1"></i>{{ __('app.weekly_plan_strategy_advance') }}
                </button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($canEdit && is_array($currentStep) && !empty($currentStep['fields']))
        <div class="card mb-4 border border-primary">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0">{{ __('app.weekly_plan_strategy_edit_title', ['level' => $currentLevel, 'title' => $currentStep['title']]) }}</h5>
                    @if (!empty($currentStep['tip']))
                        <p class="text-muted small mb-0 mt-1">{{ $currentStep['tip'] }}</p>
                    @endif
                </div>
                <span class="badge bg-label-primary">
                    {{ __('app.weekly_plan_strategy_progress', ['filled' => $currentStep['filled'] ?? 0, 'total' => $currentStep['total'] ?? 0]) }}
                </span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('strategy.update') }}">
                    @csrf
                    <div class="row g-3">
                        @foreach ($currentStep['fields'] as $field)
                            <div class="col-md-6">
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

    <div class="row">
        @foreach ($steps as $step)
            @php
                $number = (int) ($step['number'] ?? 0);
                $isCurrent = $number === $currentLevel;
                $isDone = $number < $currentLevel;
                $progress = $stepsProgress[$number] ?? ['filled' => 0, 'total' => 0, 'complete' => false];
            @endphp
            <div class="col-md-4 mb-4">
                <div class="card text-center border {{ $isCurrent ? 'border-primary border-2' : ($groupBorder[$step['group'] ?? ''] ?? 'border-secondary') }} mb-3 {{ $isDone ? 'bg-label-success' : '' }}" style="height: 100%;">
                    <div class="card-body">
                        <div class="d-flex justify-content-center gap-1 mb-2 flex-wrap">
                            @if ($isDone)
                                <span class="badge bg-success">{{ __('app.weekly_plan_strategy_done') }}</span>
                            @endif
                            @if ($isCurrent)
                                <span class="badge bg-primary">{{ __('app.weekly_plan_strategy_here') }}</span>
                            @endif
                            @if (($progress['total'] ?? 0) > 0)
                                <span class="badge bg-label-secondary">{{ $progress['filled'] }}/{{ $progress['total'] }}</span>
                            @endif
                        </div>
                        <h5 class="card-title">{{ $step['number'] }}. {{ $step['title'] }}</h5>
                        <ul class="list-unstyled mb-0 text-start">
                            @foreach ($step['fields'] ?? [] as $field)
                                @php
                                    $key = (string) ($field['key'] ?? '');
                                    $filled = $key !== '' && trim((string) ($strategyValues[$key] ?? '')) !== '';
                                @endphp
                                <li class="mb-1">
                                    <i class="ti {{ $filled ? 'ti-circle-check text-success' : 'ti-circle text-muted' }} ti-xs me-1"></i>
                                    {{ $field['label'] ?? '' }}
                                </li>
                            @endforeach
                        </ul>
                        @if ($isCurrent && !empty($step['tip']))
                            <p class="text-muted small mt-3 mb-0">{{ $step['tip'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
