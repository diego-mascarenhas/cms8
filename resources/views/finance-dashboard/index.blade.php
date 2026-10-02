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
        <p class="mb-0 mt-1">
            <a href="{{ route('finance-dashboard.exchange-rates') }}">{{ __('Exchange rates') }}</a>
            @if ($exchangeRates['updated_at'])
                <span class="text-muted">· {{ __('Updated :datetime', ['datetime' => $exchangeRates['updated_at']->timezone('Europe/Madrid')->format('d/m/Y H:i')]) }}</span>
                @if ($exchangeRates['quote_date'])
                    <span class="text-muted">· {{ __('Latest quote :date', ['date' => \Carbon\Carbon::parse($exchangeRates['quote_date'])->format('d/m/Y')]) }}</span>
                @endif
            @endif
        </p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
        <form method="GET" action="{{ route('finance-dashboard.index') }}" class="d-flex align-items-center">
            <label for="financial-dashboard-year" class="form-label mb-0 me-2">{{ __('Year') }}</label>
            <div class="position-relative w-px-100">
                <select id="financial-dashboard-year" name="year" class="select2 form-select js-filter-select" onchange="this.form.submit()">
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" @selected($year === $selectedYear)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        @include('partials.filter-select2-script')

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

<!-- Account Balances -->
<div class="card">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="card-title m-0">{{ __('Account Balances') }}</h5>
            <p class="text-muted small mb-0">{{ __('Balances sum all payments per account; only active accounts with movements are listed.') }}</p>
        </div>
        @can('viewAny', \App\Models\PaymentAccount::class)
            <div class="d-flex flex-wrap gap-2">
                @if ($accounts->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadStatementModal">
                        <i class="ti ti-upload me-1"></i>{{ __('Subir extracto') }}
                    </button>
                @endif
                <a href="{{ route('payment-account.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-wallet me-1"></i> Cuentas de pago
                </a>
            </div>
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
                        <a href="{{ route('payment-account.show', $account['id']) }}" class="text-body text-decoration-none d-block">
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
                        </a>
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

@can('viewAny', \App\Models\PaymentAccount::class)
    @if ($accounts->isNotEmpty())
        <div class="modal fade" id="uploadStatementModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('payment-account.statements.store', $accounts->first()['id']) }}" enctype="multipart/form-data" id="dashboard-statement-form">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Subir extractos bancarios') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="statement-account" class="form-label">{{ __('Cuenta') }} <span class="text-danger">*</span></label>
                            <select id="statement-account" class="select2 form-select" required>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account['id'] }}">{{ $account['name'] }} ({{ $account['currency_code'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="dashboard-statement-files" class="form-label">{{ __('Archivos') }} <span class="text-danger">*</span></label>
                            <input type="file" name="files[]" id="dashboard-statement-files" class="form-control" multiple required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="dashboard-period-year" class="form-label">{{ __('Año') }}</label>
                                <input type="number" name="period_year" id="dashboard-period-year" class="form-control" min="2000" max="2100" value="{{ $selectedYear }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="dashboard-period" class="form-label">{{ __('Periodo') }}</label>
                                <select name="period" id="dashboard-period" class="select2 form-select" required>
                                    <optgroup label="{{ __('Mes') }}">
                                        @for ($month = 1; $month <= 12; $month++)
                                            <option value="m:{{ $month }}" @selected($month === (int) now()->month)>
                                                {{ \Carbon\Carbon::create(null, $month, 1)->translatedFormat('F') }}
                                            </option>
                                        @endfor
                                    </optgroup>
                                    <optgroup label="{{ __('Trimestre') }}">
                                        @for ($quarter = 1; $quarter <= 4; $quarter++)
                                            <option value="q:{{ $quarter }}">Q{{ $quarter }}</option>
                                        @endfor
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="dashboard-statement-balance" class="form-label">{{ __('Saldo del extracto') }}</label>
                                <input type="number" step="0.01" name="statement_balance" id="dashboard-statement-balance" class="form-control" placeholder="0.00">
                                <div class="form-text">{{ __('Para PDF u otros documentos sin importes legibles. Se compara con los pagos del periodo.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-upload me-1"></i>{{ __('Subir') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statementForm = document.getElementById('dashboard-statement-form');
    const statementAccount = document.getElementById('statement-account');
    const statementAction = @json(route('payment-account.statements.store', ['paymentAccount' => '__ACCOUNT__']));

    if (statementForm && statementAccount) {
        const applyAccount = function () {
            statementForm.action = statementAction.replace('__ACCOUNT__', statementAccount.value);
        };
        statementAccount.addEventListener('change', applyAccount);
        statementForm.addEventListener('submit', applyAccount);
        applyAccount();
    }

    if (window.jQuery && jQuery.fn.select2) {
        const $statementModal = jQuery('#uploadStatementModal');
        jQuery('#statement-account, #dashboard-period').each(function () {
            const $select = jQuery(this);
            if ($select.data('select2')) {
                return;
            }
            $select.select2({
                dropdownParent: $statementModal,
                width: '100%',
                minimumResultsForSearch: Infinity,
            });
        });
    }

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
});
</script>
@endpush
