@extends('layouts/layoutMaster')

@section('title', __('app.weekly_plan_report'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">
                <i class="ti ti-calendar-week text-primary me-1"></i>{{ $report['title'] }}
            </h4>
            <p class="text-muted mb-0">{{ $report['week_label'] }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            @if($report['previous_week'])
                <a href="{{ route('weekly-plan.index', ['week' => $report['previous_week']]) }}" class="btn btn-sm btn-label-secondary">
                    <i class="ti ti-chevron-left me-1"></i>{{ __('app.weekly_plan_previous') }}
                </a>
            @endif
            @if($report['next_week'])
                <a href="{{ route('weekly-plan.index', ['week' => $report['next_week']]) }}" class="btn btn-sm btn-label-secondary">
                    {{ __('app.weekly_plan_next') }}<i class="ti ti-chevron-right ms-1"></i>
                </a>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if(filled($report['challenge']))
                <p class="text-muted">{{ __('app.weekly_plan_challenge', ['challenge' => $report['challenge']]) }}</p>
            @endif

            @if($report['items'] === [])
                <p class="mb-0">{{ __('app.weekly_plan_empty_week') }}</p>
            @else
                <ul class="list-unstyled mb-0">
                    @foreach($report['items'] as $item)
                        <li class="d-flex align-items-start gap-2 mb-2">
                            <i class="ti {{ !empty($item['done']) ? 'ti-circle-check text-success' : 'ti-checkbox text-primary' }} mt-1"></i>
                            <div>
                                <span class="badge bg-label-{{ ($item['scope'] ?? 'team') === 'user' ? 'info' : 'secondary' }} me-1">
                                    {{ ($item['scope'] ?? 'team') === 'user' ? __('app.weekly_plan_scope_user') : __('app.weekly_plan_scope_team') }}
                                </span>
                                @if(!empty($item['href']))
                                    <a href="{{ $item['href'] }}" class="text-body">{{ $item['label'] }}</a>
                                @else
                                    {{ $item['label'] }}
                                @endif
                                @if(!empty($item['details']))
                                    <ul class="list-unstyled ms-1 mt-1 mb-0">
                                        @foreach($item['details'] as $detail)
                                            <li class="mb-1">
                                                @if(!empty($detail['href']))
                                                    <a href="{{ $detail['href'] }}" class="text-body">{{ $detail['label'] }}</a>
                                                @else
                                                    {{ $detail['label'] }}
                                                @endif
                                                @if(!empty($detail['note']))
                                                    <div class="text-muted small">{{ $detail['note'] }}</div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
