@extends('layouts/layoutMaster')

@section('title', __('Accounting Dashboard'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Accounting Dashboard') }}</h4>
        <p class="text-muted">{{ __('Financial overview and indicators') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('income.index') }}" class="btn btn-outline-success me-2">
            <i class="ti ti-trending-up me-1"></i> {{ __('Income') }}
        </a>
        <a href="{{ route('expense.index') }}" class="btn btn-outline-danger">
            <i class="ti ti-trending-down me-1"></i> {{ __('Expenses') }}
        </a>
    </div>
</div>

<!-- Key Metrics Row -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Monthly Profit') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 {{ $currentMonthProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($currentMonthProfit, 2) }}
                            </h3>
                        </div>
                        <p class="mb-0">{{ date('F Y') }}</p>
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
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Monthly Income') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 text-success">{{ number_format($currentMonthIncome, 2) }}</h3>
                        </div>
                        <p class="mb-0">{{ date('F Y') }}</p>
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
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Monthly Expenses') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 text-danger">{{ number_format($currentMonthExpense, 2) }}</h3>
                        </div>
                        <p class="mb-0">{{ date('F Y') }}</p>
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
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Profit Margin') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2 {{ $profitMargin >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($profitMargin, 1) }}%
                            </h3>
                        </div>
                        <p class="mb-0">{{ __('YTD') }}</p>
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
                        <h2 class="mb-0 text-success">{{ number_format($ytdIncome, 2) }}</h2>
                        <p class="text-muted mb-0">{{ date('Y') }}</p>
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
                        <h2 class="mb-0 text-danger">{{ number_format($ytdExpense, 2) }}</h2>
                        <p class="text-muted mb-0">{{ date('Y') }}</p>
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
                            {{ number_format($ytdProfit, 2) }}
                        </h2>
                        <p class="text-muted mb-0">{{ date('Y') }}</p>
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
                    <p class="text-muted mb-0">{{ __('Last 12 months') }}</p>
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
                <p class="text-muted mb-0">{{ __('Last 12 months') }}</p>
            </div>
            <div class="card-body">
                <div id="profitChart"></div>
            </div>
        </div>
    </div>
</div>

<!-- Account Balances -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title m-0">{{ __('Account Balances') }}</h5>
    </div>
    <div class="card-widget-separator-wrapper">
        <div class="card-body card-widget-separator">
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
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Income vs Expenses Chart
    const incomeExpenseChartEl = document.querySelector('#incomeExpenseChart');
    const monthlyData = @json($monthlyData);

    const incomeExpenseConfig = {
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        series: [
            {
                name: '{{ __("Income") }}',
                data: monthlyData.map(d => d.income)
            },
            {
                name: '{{ __("Expenses") }}',
                data: monthlyData.map(d => d.expense)
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
            categories: monthlyData.map(d => d.month)
        },
        yaxis: {
            labels: {
                formatter: function(val) {
                    return new Intl.NumberFormat('es-ES', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }).format(val);
                }
            }
        },
        fill: { opacity: 1 },
        tooltip: {
            y: {
                formatter: function(val) {
                    return new Intl.NumberFormat('es-ES', {
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

    if (incomeExpenseChartEl) {
        const incomeExpenseChart = new ApexCharts(incomeExpenseChartEl, incomeExpenseConfig);
        incomeExpenseChart.render();
    }

    // Profit Chart
    const profitChartEl = document.querySelector('#profitChart');
    const profitConfig = {
        chart: {
            type: 'area',
            height: 350,
            toolbar: { show: false },
            sparkline: { enabled: false }
        },
        series: [{
            name: '{{ __("Profit") }}',
            data: monthlyData.map(d => d.profit)
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
            categories: monthlyData.map(d => d.month),
            labels: {
                rotate: -45,
                rotateAlways: true
            }
        },
        yaxis: {
            labels: {
                formatter: function(val) {
                    return new Intl.NumberFormat('es-ES', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }).format(val);
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return new Intl.NumberFormat('es-ES', {
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

    if (profitChartEl) {
        const profitChart = new ApexCharts(profitChartEl, profitConfig);
        profitChart.render();
    }
});
</script>
@endsection
