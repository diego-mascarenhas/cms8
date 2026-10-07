@extends('layouts/layoutMaster')

@section('title', __('app.strategy_review_title'))

@section('content')
    @php
        $review = is_array($review ?? null) ? $review : null;
        $groups = [];
        foreach ($review['items'] ?? [] as $item) {
            $number = (int) ($item['level'] ?? 0);
            $groups[$number]['title'] = $item['title'] ?? '';
            $groups[$number]['items'][] = $item;
        }
        $statusClass = [
            'validated' => 'bg-label-success',
            'weak' => 'bg-label-warning',
            'missing' => 'bg-label-secondary',
        ];
        $statusLabel = [
            'validated' => __('app.strategy_review_validated'),
            'weak' => __('app.strategy_review_weak'),
            'missing' => __('app.strategy_review_missing'),
        ];
    @endphp

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('app.strategy_review_title') }}</h4>
            <p class="text-muted mb-0">
                @if ($review)
                    {{ __('app.weekly_plan_strategy_current', ['level' => $review['level']]) }}
                @else
                    {{ __('app.strategy_review_empty') }}
                @endif
            </p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('strategy.index') }}" class="btn btn-label-secondary">
                <i class="ti ti-target me-1"></i>{{ __('app.weekly_plan_strategy_link') }}
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

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($review)
        <div class="card mb-4">
            <div class="card-body">
                <p class="mb-0">{{ $review['summary'] }}</p>
            </div>
        </div>

        @foreach ($groups as $number => $group)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ $number }}. {{ $group['title'] }}</h5>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($group['items'] as $item)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <span>{{ $item['label'] }}</span>
                                <span class="badge {{ $statusClass[$item['status']] ?? 'bg-label-secondary' }}">{{ $statusLabel[$item['status']] ?? '' }}</span>
                            </div>
                            @if (($item['note'] ?? '') !== '')
                                <p class="text-muted small mb-0 mt-1">{{ $item['note'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
@endsection
