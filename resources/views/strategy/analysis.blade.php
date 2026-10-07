@extends('layouts/layoutMaster')

@section('title', __('app.cfo_analysis_title'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('app.cfo_analysis_title') }}</h4>
            <p class="text-muted mb-0">{{ __('These suggestions stay with the team.') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('strategy.index') }}" class="btn btn-label-secondary">
                <i class="ti ti-target me-1"></i>{{ __('app.weekly_plan_strategy_link') }}
            </a>
            @if ($canAskCfo ?? false)
                <form method="POST" action="{{ route('finance-dashboard.cfo-brief') }}" id="cfo-brief-form">
                    @csrf
                    <input type="hidden" name="refresh" value="1">
                    <input type="hidden" name="year" value="{{ now()->year }}">
                    <button type="submit" class="btn btn-label-primary" id="cfo-brief-button">
                        <i class="ti ti-sparkles me-1"></i>{{ __('Ask the CFO') }}
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

    <div class="card mb-4" id="cfo-analysis">
        <div class="card-body">
            @php
                $analysis = is_array($cfoAnalysis ?? null) ? $cfoAnalysis : [];
                $dafo = is_array($analysis['dafo'] ?? null) ? $analysis['dafo'] : [];
                $hasDafo = implode('', $dafo) !== '';
                $hasPerspective = $hasDafo || filled($analysis['fifo'] ?? null) || filled($analysis['dagmar'] ?? null);
            @endphp

            @if ($hasDafo)
                <h6 class="mb-3">{{ __('app.cfo_analysis_dafo') }}</h6>
                <div class="row g-3 mb-4">
                    @foreach ([
                        'fortalezas' => 'cfo_analysis_strengths',
                        'debilidades' => 'cfo_analysis_weaknesses',
                        'oportunidades' => 'cfo_analysis_opportunities',
                        'amenazas' => 'cfo_analysis_threats',
                    ] as $key => $label)
                        @if (filled($dafo[$key] ?? null))
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <div class="text-muted small mb-1">{{ __('app.'.$label) }}</div>
                                    <div>{{ $dafo[$key] }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if (filled($analysis['fifo'] ?? null))
                <h6 class="mb-1">{{ __('app.cfo_analysis_fifo') }}</h6>
                <p class="text-muted small mb-2">{{ __('app.cfo_analysis_fifo_hint') }}</p>
                <p>{{ $analysis['fifo'] }}</p>
            @endif

            @if (filled($analysis['dagmar'] ?? null))
                <h6 class="mb-1">{{ __('app.cfo_analysis_dagmar') }}</h6>
                <p class="text-muted small mb-2">{{ __('app.cfo_analysis_dagmar_hint') }}</p>
                <p>{{ $analysis['dagmar'] }}</p>
            @endif

            @if (! empty($analysis['actions'] ?? []))
                <h6 class="mb-2">{{ __('app.cfo_analysis_actions') }}</h6>
                <ol class="mb-3">
                    @foreach ($analysis['actions'] as $action)
                        <li>{{ $action }}</li>
                    @endforeach
                </ol>
                <a href="{{ route('weekly-plan.index') }}">{{ __('app.weekly_plan_report') }}</a>
            @elseif (! $hasPerspective && filled($analysis['brief'] ?? null))
                <div style="white-space: pre-wrap;">{{ $analysis['brief'] }}</div>
            @else
                <p class="text-muted mb-0">{{ __('app.cfo_analysis_empty') }}</p>
            @endif
        </div>
    </div>

    @if ($canAskCfo ?? false)
        <script>
            document.getElementById('cfo-brief-form')?.addEventListener('submit', function (event) {
                const button = document.getElementById('cfo-brief-button');

                if (!button) {
                    return;
                }

                if (button.dataset.loading === '1') {
                    event.preventDefault();
                    return;
                }

                button.dataset.loading = '1';
                button.setAttribute('aria-busy', 'true');
                window.setTimeout(function () {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + @json(__('Asking the CFO...'));
                }, 0);
            });
        </script>
    @endif
@endsection
