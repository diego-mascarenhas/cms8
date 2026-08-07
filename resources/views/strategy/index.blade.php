@extends('layouts/layoutMaster')

@section('title', __('Strategic Growth Framework'))

@section('content')
@php
    $steps = config('strategy.steps', []);
    $groupBorder = [
        'foundation' => 'border-success',
        'systems' => 'border-warning',
        'scale' => 'border-primary',
    ];
@endphp

    <h2 class="mb-4">{{ config('strategy.title', 'Strategic Growth Framework') }}</h2>

    <div class="row">
        @foreach ($steps as $step)
            <div class="col-md-4 mb-4">
                <div class="card text-center border {{ $groupBorder[$step['group'] ?? ''] ?? 'border-secondary' }} mb-3" style="height: 100%;">
                    <div class="card-body">
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
