@extends('layouts/layoutMaster')

@section('title', __('app.cfo_analysis_title'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@php
    $projection = is_array($projection ?? null) ? $projection : [];
    $projectionPoints = $projection['points'] ?? [];
@endphp

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">
                {{ __('app.cfo_analysis_title') }}
                <a href="{{ route('help.cfo-analysis') }}" target="_blank" rel="noopener" class="text-muted ms-1" title="{{ __('help_cfo.icon_title') }}">
                    <i class="ti ti-info-circle"></i>
                </a>
            </h4>
            <p class="text-muted mb-0">{{ __('These suggestions stay with the team.') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('strategy.index') }}" class="btn btn-label-secondary">
                <i class="ti ti-target me-1"></i>{{ __('app.weekly_plan_strategy_link') }}
            </a>
            @if ($canAskCfo ?? false)
                <form method="POST" action="{{ route('strategy.analysis.refresh') }}" id="cfo-refresh-form">
                    @csrf
                    <button type="submit" class="btn btn-label-secondary" id="cfo-refresh-button">
                        <i class="ti ti-refresh me-1"></i>{{ __('app.cfo_analysis_refresh') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('finance-dashboard.cfo-brief') }}" id="cfo-brief-form">
                    @csrf
                    <input type="hidden" name="refresh" value="1">
                    <input type="hidden" name="year" value="{{ now()->year }}">
                    <button type="submit" class="btn btn-label-primary" id="cfo-brief-button">
                        <i class="ti ti-sparkles me-1"></i>{{ __('Ask the CFO') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('strategy.analysis.cmo-brief') }}" id="cmo-brief-form">
                    @csrf
                    <input type="hidden" name="refresh" value="1">
                    <input type="hidden" name="year" value="{{ now()->year }}">
                    <button type="submit" class="btn btn-label-primary" id="cmo-brief-button">
                        <i class="ti ti-speakerphone me-1"></i>{{ __('Ask the CMO') }}
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

    @include('strategy.partials.subsistence', ['subsistence' => $subsistence ?? null])

    <div class="card mb-4" id="cfo-analysis">
        <div class="card-body">
            @php
                $analysis = is_array($cfoAnalysis ?? null) ? $cfoAnalysis : [];
                $dafo = is_array($analysis['dafo'] ?? null) ? $analysis['dafo'] : [];
                $hasDafo = implode('', $dafo) !== '';
                $capacity = is_array($analysis['capacity'] ?? null) ? $analysis['capacity'] : [];
                $hasCapacity = implode('', $capacity) !== '';
                $hasPerspective = $hasDafo || filled($analysis['fifo'] ?? null) || filled($analysis['dagmar'] ?? null) || $hasCapacity;
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

            @if ($hasCapacity)
                <h6 class="mb-3">{{ __('app.cfo_analysis_capacity') }}</h6>
                <div class="row g-3 mb-4">
                    @foreach ([
                        'resources' => 'cfo_analysis_resources',
                        'hours' => 'cfo_analysis_hours',
                        'minimum_salary' => 'cfo_analysis_minimum_salary',
                        'now' => 'cfo_analysis_now',
                        'missing_departments' => 'cfo_analysis_missing_departments',
                    ] as $key => $label)
                        @if (filled($capacity[$key] ?? null))
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small mb-1">{{ __('app.'.$label) }}</div>
                                    <div>{{ $capacity[$key] }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
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
            @elseif (! $hasPerspective)
                <p class="text-muted mb-0">{{ __('app.cfo_analysis_empty') }}</p>
            @endif
        </div>
    </div>

    @include('strategy.partials.cmo', ['cmoAnalysis' => $cmoAnalysis ?? null])

    <div class="card mb-4" id="cfo-projection">
        <div class="card-header">
            <h5 class="card-title m-0">
                {{ __('app.cfo_analysis_projection') }}
                <a href="{{ route('help.cfo-analysis') }}#projection" target="_blank" rel="noopener" class="text-muted ms-1" title="{{ __('help_cfo.projection_title') }}">
                    <i class="ti ti-info-circle"></i>
                </a>
            </h5>
            <p class="text-muted small mb-0">{{ __('app.cfo_analysis_projection_hint') }}</p>
        </div>
        <div class="card-body">
            @php
                $hasProjection = ($projection['months_with_data'] ?? 0) > 0
                    || ($projection['contracted_income'] ?? 0) > 0
                    || ($projection['contracted_expense'] ?? 0) > 0
                    || ($projection['chart_income'] ?? 0) > 0
                    || ($projection['chart_expense'] ?? 0) > 0;
            @endphp
            @if ($hasProjection)
                <p class="mb-3">
                    {{ __('app.cfo_analysis_run_rate', [
                        'income' => number_format((float) ($projection['chart_income'] ?? 0), 2, ',', '.'),
                        'expense' => number_format((float) ($projection['chart_expense'] ?? 0), 2, ',', '.'),
                        'profit' => number_format((float) ($projection['year_profit'] ?? 0), 2, ',', '.'),
                        'currency' => $projection['currency'] ?? '',
                    ]) }}
                </p>
                @if (($projection['contracted_income'] ?? 0) > 0)
                    <p class="mb-3">
                        {{ __('app.cfo_analysis_contracted', [
                            'amount' => number_format((float) $projection['contracted_income'], 2, ',', '.'),
                            'currency' => $projection['currency'] ?? '',
                        ]) }}
                    </p>
                @endif
                @include('strategy.partials.salary-forecast', ['projection' => $projection])
                <div id="cfo-projection-chart"></div>
                @if (! empty($projection['generated_at']))
                    <p class="text-muted small mt-3 mb-0">{{ __('app.cfo_analysis_projection_updated', ['date' => \Carbon\Carbon::parse($projection['generated_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i')]) }}</p>
                @endif
            @elseif (empty($projection['generated_at']))
                @include('strategy.partials.salary-forecast', ['projection' => $projection])
                <p class="text-muted mb-0">{{ __('app.cfo_analysis_projection_pending') }}</p>
            @else
                @include('strategy.partials.salary-forecast', ['projection' => $projection])
                <p class="text-muted mb-0">{{ __('app.cfo_analysis_projection_empty') }}</p>
            @endif
        </div>
    </div>

    @if ($canAskCfo ?? false)
        <script>
            document.getElementById('cfo-refresh-form')?.addEventListener('submit', function (event) {
                const button = document.getElementById('cfo-refresh-button');

                if (!button || button.dataset.loading === '1') {
                    event.preventDefault();
                    return;
                }

                button.dataset.loading = '1';
                button.setAttribute('aria-busy', 'true');
                window.setTimeout(function () {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + @json(__('app.cfo_analysis_refreshing'));
                }, 0);
            });

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

            document.getElementById('cmo-brief-form')?.addEventListener('submit', function (event) {
                const button = document.getElementById('cmo-brief-button');

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
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + @json(__('Asking the CMO...'));
                }, 0);
            });
        </script>
    @endif
@endsection

@section('page-script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartEl = document.querySelector('#cfo-projection-chart');
            const points = @json($projectionPoints);

            if (!chartEl || !points.length || typeof ApexCharts === 'undefined') {
                return;
            }

            const formatMoney = function (val) {
                return new Intl.NumberFormat(document.documentElement.lang || 'es', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(val);
            };

            new ApexCharts(chartEl, {
                chart: { type: 'bar', height: 340, toolbar: { show: false }, stacked: false },
                series: [
                    { name: @json(__('Income')), data: points.map(function (point) { return point.projected ? null : point.income; }) },
                    { name: @json(__('app.cfo_projected_income')), data: points.map(function (point) { return point.projected ? point.income : null; }) },
                    { name: @json(__('Expenses')), data: points.map(function (point) { return point.expense_projected ? null : point.expense; }) },
                    { name: @json(__('app.cfo_projected_expense')), data: points.map(function (point) { return point.expense_projected ? point.expense : null; }) },
                ],
                colors: ['#28c76f', '#b2edc4', '#ea5455', '#fad8d9'],
                plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
                dataLabels: { enabled: false },
                stroke: { show: true, width: 2, colors: ['transparent'] },
                xaxis: { categories: points.map(function (point) { return point.label; }) },
                yaxis: { labels: { formatter: function (val) { return formatMoney(val); } } },
                tooltip: { y: { formatter: function (val) { return val === null ? '' : formatMoney(val); } } },
            }).render();
        });
    </script>
@endsection
