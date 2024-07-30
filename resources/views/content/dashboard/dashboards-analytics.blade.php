@extends('layouts/layoutMaster')

@section('title', 'Analytics')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/swiper/swiper.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}" />
@endsection

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/cards-advance.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/swiper/swiper.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Configuración del gráfico de ingresos por día de la semana
    const weeklyEarningReportsEl = document.querySelector('#weeklyEarningReports'),
        weeklyEarningReportsConfig = {
            chart: {
                height: 202,
                parentHeightOffset: 0,
                type: 'bar',
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    barHeight: '60%',
                    columnWidth: '38%',
                    startingShape: 'rounded',
                    endingShape: 'rounded',
                    borderRadius: 4,
                    distributed: true
                }
            },
            grid: {
                show: false,
                padding: {
                    top: -30,
                    bottom: 0,
                    left: -10,
                    right: -10
                }
            },
            colors: [
                '#696cff', '#696cff', '#696cff', '#696cff', '#696cff', '#696cff', '#696cff'
            ],
            dataLabels: {
                enabled: false
            },
            series: [{
                data: @json(array_values($monthDays))
            }],
            legend: {
                show: false
            },
            xaxis: {
                categories: @json(array_keys($monthDays)),
                labels: {
                    formatter: function(value) {
                        return new Date(value).getDate(); // Aquí se obtiene solo el día
                    },
                    style: {
                        colors: '#697a8d',
                        fontSize: '13px',
                        fontFamily: 'Public Sans'
                    }
                },
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                }
            },
            yaxis: {
                labels: {
                    show: false
                }
            },
            tooltip: {
                enabled: false
            },
            responsive: [{
                breakpoint: 1025,
                options: {
                    chart: {
                        height: 199
                    }
                }
            }]
        };
    if (typeof weeklyEarningReportsEl !== undefined && weeklyEarningReportsEl !== null) {
        const weeklyEarningReports = new ApexCharts(weeklyEarningReportsEl, weeklyEarningReportsConfig);
        weeklyEarningReports.render();
    }

    // Configuración del gráfico de Support Tracker
    const supportTrackerEl = document.querySelector('#supportTracker'),
        supportTrackerOptions = {
            series: [85],
            labels: ['Completed Task'],
            chart: {
                height: 300,
                type: 'radialBar'
            },
            plotOptions: {
                radialBar: {
                    offsetY: 10,
                    startAngle: -140,
                    endAngle: 130,
                    hollow: {
                        size: '65%'
                    },
                    track: {
                        background: '#f2f2f2',
                        strokeWidth: '100%'
                    },
                    dataLabels: {
                        name: {
                            offsetY: -20,
                            color: '#6e6b7b',
                            fontSize: '13px',
                            fontWeight: '400',
                            fontFamily: 'Public Sans'
                        },
                        value: {
                            offsetY: 10,
                            color: '#333',
                            fontSize: '38px',
                            fontWeight: '500',
                            fontFamily: 'Public Sans'
                        }
                    }
                }
            },
            colors: ['#696cff'],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    shadeIntensity: 0.5,
                    gradientToColors: ['#696cff'],
                    inverseColors: true,
                    opacityFrom: 1,
                    opacityTo: 0.6,
                    stops: [30, 70, 100]
                }
            },
            stroke: {
                dashArray: 10
            },
            grid: {
                padding: {
                    top: -20,
                    bottom: 5
                }
            },
            states: {
                hover: {
                    filter: {
                        type: 'none'
                    }
                },
                active: {
                    filter: {
                        type: 'none'
                    }
                }
            },
            responsive: [
                {
                    breakpoint: 1025,
                    options: {
                        chart: {
                            height: 330
                        }
                    }
                },
                {
                    breakpoint: 769,
                    options: {
                        chart: {
                            height: 280
                        }
                    }
                }
            ]
        };
    if (typeof supportTrackerEl !== undefined && supportTrackerEl !== null) {
        const supportTracker = new ApexCharts(supportTrackerEl, supportTrackerOptions);
        supportTracker.render();
    }
});
</script>
@endsection

@section('content')

<div class="row">
    <!-- Earning Reports -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0 d-flex justify-content-between mb-lg-n4">
                <div class="card-title mb-0">
                    <h5 class="mb-0">Earning Reports</h5>
                    <small class="text-muted">Monthly Earnings Overview</small>
                </div>
                <div class="dropdown">
                    <button class="btn p-0" type="button" id="earningReportsId" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="earningReportsId">
                        <a class="dropdown-item" href="{{ route('app-payment-list') }}">View Payments</a>
                        <a class="dropdown-item" href="{{ route('app-invoice-report') }}">View Report</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-4 d-flex flex-column align-self-end">
                        <div class="d-flex gap-2 align-items-center mb-2 pb-1 flex-wrap">
                            <h1 class="mb-0">${{ number_format($profitDifference, 2) }}</h1>
                            <div class="badge rounded {{ $profitDifference >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                                {{ number_format($profitPercentage, 2) }}%
                            </div>
                        </div>
                        <small>You informed of this month compared to last month</small>
                    </div>
                    <div class="col-12 col-md-8">
                        <div id="weeklyEarningReports"></div>
                    </div>
                </div>
                <div class="border rounded p-3 mt-4">
                    <div class="row gap-4 gap-sm-0">
                        <div class="col-12 col-sm-4">
                            <div class="d-flex gap-2 align-items-center">
                                <div class="badge rounded bg-label-success p-1">
                                    <i class="fas fa-chart-line ti-sm"></i>
                                </div>
                                <h6 class="mb-0">Earnings</h6>
                            </div>
                            <h4 class="my-2 pt-1">${{ number_format($currentMonthEarnings, 2) }}</h4>
                            <div class="progress w-75" style="height:4px">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="d-flex gap-2 align-items-center">
                                <div class="badge rounded bg-label-info p-1">
                                    <i class="ti ti-currency-dollar ti-sm"></i>
                                </div>
                                <h6 class="mb-0">Profit</h6>
                            </div>
                            <h4 class="my-2 pt-1">${{ number_format($currentMonthProfit, 2) }}</h4>
                            <div class="progress w-75" style="height:4px">
                                <div class="progress-bar bg-info" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="d-flex gap-2 align-items-center">
                                <div class="badge rounded bg-label-danger p-1">
                                    <i class="fas fa-shopping-cart ti-sm"></i>
                                </div>
                                <h6 class="mb-0">Expense</h6>
                            </div>
                            <h4 class="my-2 pt-1">${{ number_format($currentMonthExpenses, 2) }}</h4>
                            <div class="progress w-75" style="height:4px">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--/ Earning Reports -->

    <!-- Support Tracker -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between pb-0">
                <div class="card-title mb-0">
                    <h5 class="mb-0">Support Tracker</h5>
                    <small class="text-muted">Last 7 Days</small>
                </div>
                <div class="dropdown">
                    <button class="btn p-0" type="button" id="supportTrackerMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="supportTrackerMenu">
                        <a class="dropdown-item" href="{{ route('app-communication-list') }}">View Communications</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-sm-4 col-md-12 col-lg-4">
                        <div class="mt-lg-4 mt-lg-2 mb-lg-4 mb-2 pt-1">
                            <h1 class="mb-0">164</h1>
                            <p class="mb-0">Total Tickets</p>
                        </div>
                        <ul class="p-0 m-0">
                            <li class="d-flex gap-3 align-items-center mb-lg-3 pt-2 pb-1">
                                <div class="badge rounded bg-label-primary p-1">
                                    <i class="ti ti-ticket ti-sm"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-nowrap">New Tickets</h6>
                                    <small class="text-muted">142</small>
                                </div>
                            </li>
                            <li class="d-flex gap-3 align-items-center mb-lg-3 pb-1">
                                <div class="badge rounded bg-label-info p-1">
                                    <i class="ti ti-circle-check ti-sm"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-nowrap">Open Tickets</h6>
                                    <small class="text-muted">28</small>
                                </div>
                            </li>
                            <li class="d-flex gap-3 align-items-center pb-1">
                                <div class="badge rounded bg-label-warning p-1">
                                    <i class="ti ti-clock ti-sm"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-nowrap">Response Time</h6>
                                    <small class="text-muted">1 Day</small>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="col-12 col-sm-8 col-md-12 col-lg-8">
                        <div id="supportTracker"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--/ Support Tracker -->

    <!-- Hosts Status -->
    @if(isset($hosts) && $hosts->isNotEmpty())
    <div class="col-xl-4 col-md-6 order-2 order-lg-1 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title mb-0">
                    <h5 class="mb-0">vCenter</h5>
                    <small class="text-muted">Hosts Status</small>
                </div>
                <div class="dropdown">
                    <button class="btn p-0" type="button" id="sourceVisits" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="sourceVisits">
                        <a class="dropdown-item" href="{{ route('host.index') }}">View all Hosts</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    @foreach($hosts as $host)
                    <li class="mb-3 pb-1">
                        <div class="d-flex align-items-start">
                            <div class="badge bg-label-secondary p-2 me-3 rounded">
                                @if($host->connection_state == 'CONNECTED')
                                <i class="ti ti-link ti-sm"></i>
                                @else
                                <i class="ti ti-unlink ti-sm"></i>
                                @endif
                            </div>
                            <div class="d-flex justify-content-between w-100 flex-wrap gap-2">
                                <div class="me-2">
                                    <h6 class="mb-0">{{ $host->name }}</h6>
                                    <small class="text-muted">{{ $host->host }}</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    @if($host->power_state == 'POWERED_ON')
                                    <div class="ms-3 badge bg-label-success">ON</div>
                                    @else
                                    <div class="ms-3 badge bg-label-danger">OFF</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <!-- Projects table -->
    <div class="col-12 col-xl-8 col-sm-12 order-1 order-lg-2 mb-4 mb-lg-0">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title mb-0">
                    <h5 class="mb-0">Projects</h5>
                    <small class="text-muted">Ongoing projects</small>
                </div>
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-projects table border-top">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Client</th>
                            <th>Leader</th>
                            <th class="text-center">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                        <tr>
                            <td>{{ $project->name }}</td>
                            <td>{{ $project->client }}</td>
                            <td>{{ $project->leader }}</td>
                            <td class="text-center">{!! $project->status_label !!}</td>
                            <td>{!! $project->dropdown !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!--/ Projects table -->
</div>

@endsection
