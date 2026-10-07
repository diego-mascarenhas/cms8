@extends('layouts/layoutMaster')

@section('title', __('Strategic Growth Framework'))

@section('content')
@php
    $steps = $steps ?? config('strategy.steps', []);
    $currentLevel = (int) ($currentLevel ?? 1);
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
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('strategy.analysis') }}" class="btn btn-label-primary">
                <i class="ti ti-chart-dots me-1"></i>{{ __('app.cfo_analysis_title') }}
            </a>
            @if(!empty($canAdvance) && $canEdit)
                <a href="{{ route('strategy.level') }}" class="btn btn-primary waves-effect waves-light">
                    <i class="ti ti-arrow-up me-1"></i>{{ __('app.weekly_plan_strategy_advance') }}
                </a>
            @endif
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
            <div class="col-md-4 mb-4">
                <div class="card text-center border {{ $groupBorder[$step['group'] ?? ''] ?? 'border-secondary' }} mb-3" style="height: 100%;">
                    <div class="card-body">
                        <i class="ti {{ $step['icon'] ?? 'ti-circle' }} fs-2 mb-3"></i>
                        <h5 class="card-title">{{ $step['number'] }}. {{ $step['title'] }}</h5>
                        <ul class="list-unstyled mb-0">
                            @foreach ($step['points'] ?? [] as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
