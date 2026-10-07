@extends('layouts/layoutMaster')

@section('title', __('Strategic Growth Framework'))

@section('content')
@php
    $steps = $steps ?? config('strategy.steps', []);
    $currentLevel = (int) ($currentLevel ?? 1);
    $canEdit = (bool) ($canEdit ?? false);
    $strategyValues = $strategyValues ?? [];
    $reviewApproved = $reviewApproved ?? [];
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
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('strategy.analysis') }}" class="btn btn-label-primary">
                <i class="ti ti-chart-dots me-1"></i>{{ __('app.cfo_analysis_title') }}
            </a>
            @include('strategy.partials.evaluate-button')
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        @foreach ($steps as $step)
            @php
                $cardClass = 'card text-center border '.($groupBorder[$step['group'] ?? ''] ?? 'border-secondary').' mb-3 text-body';
            @endphp
            <div class="col-md-4 mb-4">
                @if ($canEdit)
                    <a href="{{ route('strategy.level', ['level' => $step['number']]) }}" class="{{ $cardClass }} text-decoration-none d-block" style="height: 100%;">
                @else
                    <div class="{{ $cardClass }}" style="height: 100%;">
                @endif
                    <div class="card-body">
                        <i class="ti {{ $step['icon'] ?? 'ti-circle' }} fs-2 mb-3"></i>
                        <h5 class="card-title">{{ $step['number'] }}. {{ $step['title'] }}</h5>
                        <ul class="list-unstyled mb-0">
                            @foreach ($step['fields'] ?? [] as $field)
                                @php
                                    $key = (string) ($field['key'] ?? '');
                                    $value = trim((string) ($strategyValues[$key] ?? ''));
                                    $approved = trim((string) ($reviewApproved[$key] ?? ''));
                                    $checked = $key !== '' && $value !== '' && $value === $approved;
                                @endphp
                                <li>
                                    {{ $field['label'] ?? '' }}
                                    @if ($checked)
                                        <i class="ti ti-circle-check text-success ms-1"></i>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @if ($canEdit)
                    </a>
                @else
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endsection
