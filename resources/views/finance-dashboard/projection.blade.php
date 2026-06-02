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
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Accounting Dashboard') }}/</span>
            {{ __('Financial projection report') }}
        </h4>
        <p class="text-muted mb-0">{{ __('Based on invoiced line items by category (historical billing data).') }}</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap align-items-center gap-2">
        <form method="GET" action="{{ route('finance-dashboard.projection') }}" class="d-flex align-items-center">
            <label for="projection-year" class="form-label mb-0 me-2">{{ __('Year') }}</label>
            <select id="projection-year" name="year" class="form-select" onchange="this.form.submit()">
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

<div class="alert alert-secondary mb-4" role="status">
    <div class="d-flex gap-2">
        <i class="ti ti-info-circle mt-1"></i>
        <div class="small">
            {{ __('Totals use invoice line amounts grouped by category. Voided, draft, and credit-note invoices are excluded.') }}
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Invoiced income') }} ({{ $selectedYear }})</span>
                <h3 class="mb-0 mt-2 text-success">{{ number_format($summary['income'], 2) }}</h3>
                @if($summary['prior_year_income'] > 0)
                    @php
                        $incomeDelta = (($summary['income'] - $summary['prior_year_income']) / $summary['prior_year_income']) * 100;
                    @endphp
                    <small class="text-muted">{{ __('vs :year', ['year' => $selectedYear - 1]) }}:
                        <span class="{{ $incomeDelta >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $incomeDelta >= 0 ? '+' : '' }}{{ number_format($incomeDelta, 1) }}%
                        </span>
                    </small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Invoiced expenses') }} ({{ $selectedYear }})</span>
                <h3 class="mb-0 mt-2 text-danger">{{ number_format($summary['expense'], 2) }}</h3>
                @if($summary['prior_year_expense'] > 0)
                    @php
                        $expenseDelta = (($summary['expense'] - $summary['prior_year_expense']) / $summary['prior_year_expense']) * 100;
                    @endphp
                    <small class="text-muted">{{ __('vs :year', ['year' => $selectedYear - 1]) }}:
                        <span class="{{ $expenseDelta <= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $expenseDelta >= 0 ? '+' : '' }}{{ number_format($expenseDelta, 1) }}%
                        </span>
                    </small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Net profit (invoiced)') }}</span>
                <h3 class="mb-0 mt-2 {{ $summary['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($summary['profit'], 2) }}
                </h3>
                <small class="text-muted">{{ __('Margin') }}: {{ number_format($summary['margin_percent'], 1) }}%</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <span class="text-muted">{{ __('Avg. monthly profit') }}</span>
                <h3 class="mb-0 mt-2 {{ $scenario['avg_monthly_profit'] >= 0 ? 'text-info' : 'text-danger' }}">
                    {{ number_format($scenario['avg_monthly_profit'], 2) }}
                </h3>
                <small class="text-muted">{{ __('Months with billing activity in :year', ['year' => $selectedYear]) }}</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
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
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Growth scenario') }}</h5>
                <p class="text-muted small mb-0">{{ __('Estimate from average monthly profit') }}</p>
            </div>
            <div class="card-body">
                <label class="form-label" for="growth-multiplier">{{ __('Target multiplier (e.g. 2 = double profit)') }}</label>
                <input type="number" class="form-control mb-3" id="growth-multiplier" min="1" max="20" step="0.5" value="2">
                <div id="growth-scenario-result" class="small text-muted"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title m-0">{{ __('Income by category') }}</h5>
                    <p class="text-muted small mb-0">{{ __('Top invoiced revenue lines') }}</p>
                </div>
            </div>
            <div class="card-body">
                @if(count($incomeCategories) === 0)
                    <p class="text-muted mb-0">{{ __('No invoiced income in this period.') }}</p>
                @else
                    <div id="incomeCategoryChart" class="mb-3"></div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-end">{{ __('Total') }}</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($incomeCategories as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['share_percent'], 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Expenses by category') }}</h5>
                <p class="text-muted small mb-0">{{ __('Where costs concentrate') }}</p>
            </div>
            <div class="card-body">
                @if(count($expenseCategories) === 0)
                    <p class="text-muted mb-0">{{ __('No invoiced expenses in this period.') }}</p>
                @else
                    <div id="expenseCategoryChart" class="mb-3"></div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-end">{{ __('Total') }}</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expenseCategories as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['share_percent'], 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const monthlyTrend = @json($monthlyTrend);
    const incomeCategories = @json($incomeCategories);
    const expenseCategories = @json($expenseCategories);
    const scenario = @json($scenario);

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

    function renderDonut(elId, rows, color) {
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
            chart: { type: 'donut', height: 260 },
            labels: labels,
            series: series,
            colors: color,
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + '%'; } },
        }).render();
    }

    renderDonut('#incomeCategoryChart', incomeCategories, ['#28c76f', '#55d187', '#83df9e', '#b2edc4']);
    renderDonut('#expenseCategoryChart', expenseCategories, ['#ea5455', '#f08182', '#f5adaf', '#fad8d9']);

    const multiplierInput = document.getElementById('growth-multiplier');
    const resultEl = document.getElementById('growth-scenario-result');

    function updateGrowthScenario() {
        const multiplier = parseFloat(multiplierInput.value) || 1;
        const current = scenario.avg_monthly_profit;
        const target = current * multiplier;
        const gap = target - current;
        const avgIncome = scenario.avg_monthly_income;
        const avgExpense = scenario.avg_monthly_expense;

        let html = '<p class="mb-2"><strong>' + @json(__('Current avg. monthly profit')) + ':</strong> ' + formatMoney(current) + '</p>';
        html += '<p class="mb-2"><strong>' + @json(__('Target profit')) + ' (×' + multiplier + '):</strong> ' + formatMoney(target) + '</p>';
        html += '<p class="mb-2"><strong>' + @json(__('Monthly gap')) + ':</strong> <span class="' + (gap >= 0 ? 'text-primary' : 'text-success') + '">' + formatMoney(gap) + '</span></p>';

        if (gap > 0 && avgIncome > 0) {
            const incomePct = (gap / avgIncome) * 100;
            html += '<p class="mb-1">' + @json(__('Equivalent to increasing invoiced income by about')) + ' <strong>' + incomePct.toFixed(1) + '%</strong> ' + @json(__('per month (holding expenses steady).')) + '</p>';
        }
        if (gap > 0 && avgExpense > 0) {
            const expensePct = (gap / avgExpense) * 100;
            html += '<p class="mb-0">' + @json(__('Or reducing invoiced expenses by about')) + ' <strong>' + expensePct.toFixed(1) + '%</strong> ' + @json(__('per month (holding income steady).')) + '</p>';
        }

        resultEl.innerHTML = html;
    }

    if (multiplierInput && resultEl) {
        multiplierInput.addEventListener('input', updateGrowthScenario);
        updateGrowthScenario();
    }
});
</script>
@endsection
