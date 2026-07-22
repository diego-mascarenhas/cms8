@extends('layouts/layoutMaster')

@section('title', __('Financial projection report'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@section('content')
@php
    $summary = $report['summary'];
    $scenario = $report['scenario'];
    $monthlyTrend = $report['monthly_trend'];
    $incomeCategories = $report['income_categories'];
    $expenseCategories = $report['expense_categories'];
    $reportingCurrency = $report['reporting_currency'] ?? strtoupper((string) config('verifactu.default_currency', 'EUR'));
    $conversion = $report['conversion'] ?? ['complete' => true, 'missing_pairs' => [], 'native_totals' => ['income' => [], 'expense' => []]];
    $formatCardAmount = static fn (float $amount): string => \App\Helpers\Helpers::formatDecimal($amount, 0);
    $formatNativeTotals = static function (array $totalsByCurrency) use ($formatCardAmount): string {
        if ($totalsByCurrency === []) {
            return '—';
        }

        $parts = [];
        foreach ($totalsByCurrency as $currency => $amount) {
            $parts[] = $formatCardAmount((float) $amount).' '.$currency;
        }

        return implode(' · ', $parts);
    };
    $incomeChartColors = ['#28c76f', '#55d187', '#83df9e', '#b2edc4', '#1f9d57', '#3dd68c', '#6ee7a8', '#9ef0c4'];
    $expenseChartColors = ['#ea5455', '#f08182', '#f5adaf', '#fad8d9', '#d43f3f', '#ff6b6b', '#ff9999', '#ffc9c9'];
    $incomeDelta = $summary['prior_year_income'] > 0
        ? (($summary['income'] - $summary['prior_year_income']) / $summary['prior_year_income']) * 100
        : 0.0;
    $expenseDelta = $summary['prior_year_expense'] > 0
        ? (($summary['expense'] - $summary['prior_year_expense']) / $summary['prior_year_expense']) * 100
        : 0.0;
    $priorProfit = (float) ($summary['prior_year_income'] ?? 0) - (float) ($summary['prior_year_expense'] ?? 0);
    $profitDelta = $priorProfit != 0.0
        ? (($summary['profit'] - $priorProfit) / abs($priorProfit)) * 100
        : 0.0;
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Accounting Dashboard') }}/</span>
            {{ __('Financial projection report') }}
            <i
                class="ti ti-info-circle text-muted ms-1"
                style="font-size: 1rem; cursor: help; vertical-align: middle;"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                title="{{ __('Totals use invoice line amounts grouped by category. Voided, draft, and credit-note invoices are excluded.') }}"
            ></i>
        </h4>
        <p class="text-muted mb-0">{{ __('Based on invoiced line items by category (historical billing data).') }}</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap align-items-center gap-2">
        <form method="GET" action="{{ route('finance-dashboard.projection') }}" class="d-flex align-items-center">
            <select id="projection-year" name="year" class="form-select w-auto" aria-label="{{ __('Year') }}" onchange="this.form.submit()">
                @foreach($availableYears as $year)
                    <option value="{{ $year }}" @selected($year === $selectedYear)>{{ $year }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('finance-dashboard.index', ['year' => $selectedYear]) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Accounting Dashboard') }}
        </a>
    </div>
</div>

@if (! $conversion['complete'])
<div class="alert alert-warning mb-4" role="alert">
    <div class="d-flex gap-2">
        <i class="ti ti-alert-triangle mt-1"></i>
        <div class="small">
            <strong>{{ __('Exchange rates required') }}</strong>
            <p class="mb-1 mt-1">
                {{ __('Totals could not be converted to :currency. Missing rates:', ['currency' => $reportingCurrency]) }}
                {{ implode(', ', $conversion['missing_pairs']) }}
            </p>
            <p class="mb-0">
                {{ __('Load system exchange rates with') }}
                <code>php artisan exchange-rates:fetch-daily</code>
                {{ __('or monthly history with') }}
                <code>php artisan exchange-rates:backfill-monthly</code>
                {{ __('or official USD/ARS with') }}
                <code>php artisan exchange-rates:backfill-bcra</code>
                {{ __('or USD/EUR with') }}
                <code>php artisan exchange-rates:backfill-frankfurter</code>.
                {{ __('Amounts below are shown in the invoice currency.') }}
            </p>
        </div>
    </div>
</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Invoiced income') }} ({{ $selectedYear }})</span>
                <div class="d-flex align-items-center my-2">
                    @if ($conversion['complete'])
                        <h3 class="mb-0 me-2 text-success">{{ $formatCardAmount($summary['income']) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                    @else
                        <h3 class="mb-0 me-2 text-success">{{ $formatNativeTotals($conversion['native_totals']['income']) }}</h3>
                    @endif
                    @if($summary['prior_year_income'] > 0 && $incomeDelta != 0)
                        <p class="mb-0 {{ $incomeDelta >= 0 ? 'text-success' : 'text-danger' }}">
                            ({{ $incomeDelta >= 0 ? '+' : '' }}{{ number_format($incomeDelta, 1) }}%)
                        </p>
                    @endif
                </div>
                <p class="mb-0 text-muted">{{ __('vs :year', ['year' => $selectedYear - 1]) }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Invoiced expenses') }} ({{ $selectedYear }})</span>
                <div class="d-flex align-items-center my-2">
                    @if ($conversion['complete'])
                        <h3 class="mb-0 me-2 text-danger">{{ $formatCardAmount($summary['expense']) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                    @else
                        <h3 class="mb-0 me-2 text-danger">{{ $formatNativeTotals($conversion['native_totals']['expense']) }}</h3>
                    @endif
                    @if($summary['prior_year_expense'] > 0 && $expenseDelta != 0)
                        <p class="mb-0 {{ $expenseDelta <= 0 ? 'text-success' : 'text-danger' }}">
                            ({{ $expenseDelta >= 0 ? '+' : '' }}{{ number_format($expenseDelta, 1) }}%)
                        </p>
                    @endif
                </div>
                <p class="mb-0 text-muted">{{ __('vs :year', ['year' => $selectedYear - 1]) }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Net profit (invoiced)') }}</span>
                <div class="d-flex align-items-center my-2">
                    @if ($conversion['complete'])
                        <h3 class="mb-0 me-2 {{ $summary['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $formatCardAmount($summary['profit']) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small>
                        </h3>
                        @if($profitDelta != 0)
                            <p class="mb-0 {{ $profitDelta >= 0 ? 'text-success' : 'text-danger' }}">
                                ({{ $profitDelta >= 0 ? '+' : '' }}{{ number_format($profitDelta, 1) }}%)
                            </p>
                        @endif
                    @else
                        <h3 class="mb-0 me-2 text-muted">—</h3>
                    @endif
                </div>
                @if ($conversion['complete'])
                    <p class="mb-0 text-muted">{{ __('Margin') }}: {{ number_format($summary['margin_percent'], 1) }}%</p>
                @else
                    <p class="mb-0 text-muted">{{ __('Profit requires converted totals.') }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Avg. monthly profit') }}</span>
                <div class="d-flex align-items-center my-2">
                    @if ($conversion['complete'])
                        <h3 class="mb-0 me-2 {{ $scenario['avg_monthly_profit'] >= 0 ? 'text-info' : 'text-danger' }}">
                            {{ $formatCardAmount($scenario['avg_monthly_profit']) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small>
                        </h3>
                    @else
                        <h3 class="mb-0 me-2 text-muted">—</h3>
                    @endif
                </div>
                <p class="mb-0 text-muted">{{ __('Months with billing activity in :year', ['year' => $selectedYear]) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Income vs expenses (invoiced)') }}</h5>
                <p class="text-muted small mb-0">{{ $selectedYear }}</p>
            </div>
            <div class="card-body">
                <div id="projectionTrendChart"></div>
            </div>
        </div>
    </div>
</div>

@include('finance-dashboard.partials.invoice-category-breakdown', [
    'incomeCategories' => $incomeCategories,
    'expenseCategories' => $expenseCategories,
    'reportingCurrency' => $reportingCurrency,
    'selectedYear' => $selectedYear,
    'selectedMonth' => $selectedMonth,
])
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        }
    });

    const monthlyTrend = @json($monthlyTrend);
    const incomeCategories = @json($incomeCategories);
    const expenseCategories = @json($expenseCategories);

    const formatMoney = function (val) {
        return new Intl.NumberFormat(document.documentElement.lang || 'es', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(val);
    };

    const trendEl = document.querySelector('#projectionTrendChart');
    if (trendEl) {
        new ApexCharts(trendEl, {
            chart: { type: 'bar', height: 340, toolbar: { show: false } },
            series: [
                { name: @json(__('Income')), data: monthlyTrend.map(function (d) { return d.income; }) },
                { name: @json(__('Expenses')), data: monthlyTrend.map(function (d) { return d.expense; }) },
            ],
            colors: ['#28c76f', '#ea5455'],
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
            dataLabels: { enabled: false },
            xaxis: { categories: monthlyTrend.map(function (d) { return d.label; }) },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return formatMoney(val);
                    },
                },
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return formatMoney(val);
                    },
                },
            },
        }).render();
    }

    const incomeChartColors = @json($incomeChartColors);
    const expenseChartColors = @json($expenseChartColors);

    function expandChartColors(palette, count) {
        const colors = [];
        for (let i = 0; i < count; i++) {
            colors.push(palette[i % palette.length]);
        }
        return colors;
    }

    function renderDonut(elId, rows, palette) {
        const el = document.querySelector(elId);
        if (!el || !rows.length) {
            return;
        }
        const top = rows.slice(0, 8);
        const otherTotal = rows.slice(8).reduce(function (sum, row) { return sum + row.total; }, 0);
        const labels = top.map(function (r) { return r.name; });
        const series = top.map(function (r) { return r.total; });
        if (otherTotal > 0) {
            labels.push(@json(__('Other')));
            series.push(otherTotal);
        }
        new ApexCharts(el, {
            chart: { type: 'donut', height: 220 },
            labels: labels,
            series: series,
            colors: expandChartColors(palette, labels.length),
            legend: { show: false },
            dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + '%'; } },
        }).render();
    }

    renderDonut('#incomeCategoryChart', incomeCategories, incomeChartColors);
    renderDonut('#expenseCategoryChart', expenseCategories, expenseChartColors);
});
</script>
@endsection
