@extends('layouts/layoutMaster')

@section('title', __('Accounting Dashboard'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
@endsection

@section('content')
@php
    $formatCardAmount = static fn (float $amount): string => \App\Helpers\Helpers::formatDecimal($amount, 0);
@endphp
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Accounting Dashboard') }}</h4>
        <p class="text-muted mb-0">{{ __('Financial overview and indicators') }}</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
        <form method="GET" action="{{ route('finance-dashboard.index') }}" class="d-flex align-items-center">
            <label for="financial-dashboard-year" class="form-label mb-0 me-2">{{ __('Year') }}</label>
            <select id="financial-dashboard-year" name="year" class="form-select" onchange="this.form.submit()">
                @foreach($availableYears as $year)
                    <option value="{{ $year }}" @selected($year === $selectedYear)>{{ $year }}</option>
                @endforeach
            </select>
        </form>

        <a href="{{ route('income.index') }}" class="btn btn-outline-success">
            <i class="ti ti-trending-up me-1"></i> {{ __('Income') }}
        </a>
        <a href="{{ route('expense.index') }}" class="btn btn-outline-danger">
            <i class="ti ti-trending-down me-1"></i> {{ __('Expenses') }}
        </a>
        @can('viewAny', App\Models\Invoice::class)
        <a href="{{ route('finance-dashboard.projection', ['year' => $selectedYear]) }}" class="btn btn-primary">
            <i class="ti ti-report-analytics me-1"></i> {{ __('Report') }}
        </a>
        @endcan
    </div>
</div>

<!-- Key Metrics Row -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between h-100">
                    <div class="content-left">
                        <span>{{ __('Monthly Profit') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 {{ $currentMonthProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $formatCardAmount($currentMonthProfit) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small>
                            </h3>
                        </div>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded {{ $currentMonthProfit >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                            <i class="ti {{ $currentMonthProfit >= 0 ? 'ti-chart-line' : 'ti-chart-line-down' }} ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between h-100">
                    <div class="content-left">
                        <span>{{ __('Monthly Income') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 text-success">{{ $formatCardAmount($currentMonthIncome) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                        </div>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-arrow-up ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between h-100">
                    <div class="content-left">
                        <span>{{ __('Monthly Expenses') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 text-danger">{{ $formatCardAmount($currentMonthExpense) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                        </div>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="ti ti-arrow-down ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between h-100">
                    <div class="content-left">
                        <span>{{ __('Profit Margin') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 {{ $profitMargin >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($profitMargin, 1) }}%
                            </h3>
                        </div>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="ti ti-percentage ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Year to Date Metrics -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Year to Date Income') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0 text-success">{{ $formatCardAmount($ytdIncome) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h2>
                    </div>
                    <div class="avatar avatar-lg">
                        <span class="avatar-initial rounded-circle bg-label-success">
                            <i class="ti ti-trending-up ti-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Year to Date Expenses') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0 text-danger">{{ $formatCardAmount($ytdExpense) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h2>
                    </div>
                    <div class="avatar avatar-lg">
                        <span class="avatar-initial rounded-circle bg-label-danger">
                            <i class="ti ti-trending-down ti-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Year to Date Profit') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0 {{ $ytdProfit >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $formatCardAmount($ytdProfit) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small>
                        </h2>
                    </div>
                    <div class="avatar avatar-lg">
                        <span class="avatar-initial rounded-circle {{ $ytdProfit >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                            <i class="ti ti-chart-line ti-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Income vs Expenses Chart -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title m-0">{{ __('Income vs Expenses') }}</h5>
                    <p class="text-muted mb-0">{{ __('Selected year') }}: {{ $selectedYear }}</p>
                </div>
            </div>
            <div class="card-body">
                <div id="incomeExpenseChart"></div>
            </div>
        </div>
    </div>

    <!-- Profit Chart -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Monthly Profit') }}</h5>
                <p class="text-muted mb-0">{{ __('Selected year') }}: {{ $selectedYear }}</p>
            </div>
            <div class="card-body">
                <div id="profitChart"></div>
            </div>
        </div>
    </div>
</div>

@can('viewAny', App\Models\Invoice::class)
    @include('finance-dashboard.partials.invoice-category-breakdown', [
        'incomeCategories' => $incomeCategories,
        'expenseCategories' => $expenseCategories,
        'reportingCurrency' => $invoiceReportingCurrency,
        'incomeChartId' => 'financeDashboardIncomeCategoryChart',
        'expenseChartId' => 'financeDashboardExpenseCategoryChart',
    ])
@endcan

<!-- Account Balances -->
<div class="card">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="card-title m-0">{{ __('Account Balances') }}</h5>
            <p class="text-muted small mb-0">{{ __('Balances sum all payments per account; only active accounts with movements are listed.') }}</p>
        </div>
        @can('viewAny', \App\Models\PaymentAccount::class)
            <a href="{{ route('payment-account.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-wallet me-1"></i> Cuentas de pago
            </a>
        @endcan
    </div>
    <div class="card-widget-separator-wrapper">
        <div class="card-body card-widget-separator">
            @if ($accounts->isEmpty())
                <p class="text-muted mb-0">{{ __('No account balances to show.') }}</p>
            @else
            <div class="row gy-4 gy-sm-1">
                @foreach($accounts as $index => $account)
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start card-widget border-end pb-3 pb-sm-0">
                            <div>
                                <h4 class="mb-2 {{ $account['balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($account['balance'], 2) }}
                                </h4>
                                <p class="mb-0 fw-medium">{{ $account['name'] }}</p>
                            </div>
                            <span class="avatar me-sm-4">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    {{ $account['currency_code'] }}
                                </span>
                            </span>
                        </div>
                        @if ($index < count($accounts) - 1)
                            <hr class="d-none d-sm-block d-lg-none me-4">
                        @endif
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') {
        return;
    }

    const monthlyData = @json($monthlyData);
    const numberLocale = (document.documentElement.lang || 'es-ES').replace('_', '-');
    let incomeExpenseChart = null;
    let profitChart = null;

    const incomeExpenseConfig = {
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        series: [
            {
                name: @json(__('Income')),
                data: monthlyData.map(function (d) { return d.income; })
            },
            {
                name: @json(__('Expenses')),
                data: monthlyData.map(function (d) { return d.expense; })
            }
        ],
        colors: ['#28c76f', '#ea5455'],
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 5
            }
        },
        dataLabels: { enabled: false },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: monthlyData.map(function (d) { return d.month; })
        },
        yaxis: {
            labels: {
                formatter: function (val) {
                    return new Intl.NumberFormat(numberLocale, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }).format(val);
                }
            }
        },
        fill: { opacity: 1 },
        tooltip: {
            y: {
                formatter: function (val) {
                    return new Intl.NumberFormat(numberLocale, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(val);
                }
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left'
        }
    };

    const profitConfig = {
        chart: {
            type: 'area',
            height: 350,
            toolbar: { show: false },
            sparkline: { enabled: false }
        },
        series: [{
            name: @json(__('Profit')),
            data: monthlyData.map(function (d) { return d.profit; })
        }],
        colors: ['#00cfe8'],
        stroke: {
            curve: 'smooth',
            width: 3
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.3,
                stops: [0, 90, 100]
            }
        },
        xaxis: {
            categories: monthlyData.map(function (d) { return d.month; }),
            labels: {
                rotate: -45,
                rotateAlways: true
            }
        },
        yaxis: {
            labels: {
                formatter: function (val) {
                    return new Intl.NumberFormat(numberLocale, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }).format(val);
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return new Intl.NumberFormat(numberLocale, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(val);
                }
            }
        },
        grid: {
            borderColor: '#e7e7e7',
            strokeDashArray: 5
        }
    };

    function chartContainersReady() {
        const incomeEl = document.querySelector('#incomeExpenseChart');
        const profitEl = document.querySelector('#profitChart');

        return (!incomeEl || incomeEl.offsetWidth > 0) && (!profitEl || profitEl.offsetWidth > 0);
    }

    function renderFinanceCharts() {
        const incomeExpenseChartEl = document.querySelector('#incomeExpenseChart');
        const profitChartEl = document.querySelector('#profitChart');

        if (incomeExpenseChartEl) {
            if (incomeExpenseChart) {
                incomeExpenseChart.destroy();
                incomeExpenseChart = null;
            }
            incomeExpenseChart = new ApexCharts(incomeExpenseChartEl, incomeExpenseConfig);
            incomeExpenseChart.render().catch(function () {});
        }

        if (profitChartEl) {
            if (profitChart) {
                profitChart.destroy();
                profitChart = null;
            }
            profitChart = new ApexCharts(profitChartEl, profitConfig);
            profitChart.render().catch(function () {});
        }
    }

    function scheduleFinanceCharts(attempt) {
        attempt = attempt || 0;

        if (chartContainersReady()) {
            renderFinanceCharts();
            return;
        }

        if (attempt >= 30) {
            renderFinanceCharts();
            return;
        }

        setTimeout(function () {
            scheduleFinanceCharts(attempt + 1);
        }, 100);
    }

    scheduleFinanceCharts();

    window.addEventListener('load', function () {
        scheduleFinanceCharts();
    });

    const incomeCategories = @json($incomeCategories ?? []);
    const expenseCategories = @json($expenseCategories ?? []);
    const incomeChartColors = ['#28c76f', '#55d187', '#83df9e', '#b2edc4', '#1f9d57', '#3dd68c', '#6ee7a8', '#9ef0c4'];
    const expenseChartColors = ['#ea5455', '#f08182', '#f5adaf', '#fad8d9', '#d43f3f', '#ff6b6b', '#ff9999', '#ffc9c9'];

    function expandChartColors(palette, count) {
        const colors = [];
        for (let i = 0; i < count; i++) {
            colors.push(palette[i % palette.length]);
        }
        return colors;
    }

    function renderCategoryDonut(elId, rows, palette) {
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

    renderCategoryDonut('#financeDashboardIncomeCategoryChart', incomeCategories, incomeChartColors);
    renderCategoryDonut('#financeDashboardExpenseCategoryChart', expenseCategories, expenseChartColors);
});
</script>
@endpush
