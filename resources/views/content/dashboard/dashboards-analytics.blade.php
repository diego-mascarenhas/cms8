@extends('layouts/layoutMaster')

@section('title', 'Analytics')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/swiper/swiper.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.css') }}" />
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
    // Earning Reports Bar Chart
    const monthlyEarningReportsEl = document.querySelector('#monthlyEarningReports'),
        monthlyEarningReportsConfig = {
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
                categories: Array.from({ length: {{ now()->daysInMonth }} }, (_, i) => i + 1),
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: '#697a8d',
                        fontSize: '13px',
                        fontFamily: 'Public Sans'
                    }
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
    if (typeof monthlyEarningReportsEl !== undefined && monthlyEarningReportsEl !== null) {
        const monthlyEarningReports = new ApexCharts(monthlyEarningReportsEl, monthlyEarningReportsConfig);
        monthlyEarningReports.render();
    }

    // Total Earning Chart - Bar Chart
    const totalEarningChartEl = document.querySelector('#totalEarningChart'),
        totalEarningChartOptions = {
            series: [{
                name: 'Earning',
                data: [@json($currentMonthEarnings)]
            }, {
                name: 'Expense',
                data: [@json($currentMonthExpenses)]
            }],
            chart: {
                height: 230,
                parentHeightOffset: 0,
                stacked: true,
                type: 'bar',
                toolbar: {
                    show: false
                }
            },
            tooltip: {
                enabled: false
            },
            legend: {
                show: false
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '18%',
                    borderRadius: 5,
                    startingShape: 'rounded',
                    endingShape: 'rounded'
                }
            },
            colors: ['#696cff', '#d1d7e3'],
            dataLabels: {
                enabled: false
            },
            grid: {
                show: false,
                padding: {
                    top: -40,
                    bottom: -20,
                    left: -10,
                    right: -2
                }
            },
            xaxis: {
                labels: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                axisBorder: {
                    show: false
                }
            },
            yaxis: {
                labels: {
                    show: false
                }
            },
            responsive: [{
                    breakpoint: 1468,
                    options: {
                        plotOptions: {
                            bar: {
                                columnWidth: '22%'
                            }
                        }
                    }
                },
                {
                    breakpoint: 1197,
                    options: {
                        chart: {
                            height: 228
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 8,
                                columnWidth: '26%'
                            }
                        }
                    }
                },
                {
                    breakpoint: 783,
                    options: {
                        chart: {
                            height: 232
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 6,
                                columnWidth: '28%'
                            }
                        }
                    }
                },
                {
                    breakpoint: 589,
                    options: {
                        plotOptions: {
                            bar: {
                                columnWidth: '16%'
                            }
                        }
                    }
                },
                {
                    breakpoint: 520,
                    options: {
                        plotOptions: {
                            bar: {
                                borderRadius: 6,
                                columnWidth: '18%'
                            }
                        }
                    }
                },
                {
                    breakpoint: 426,
                    options: {
                        plotOptions: {
                            bar: {
                                borderRadius: 5,
                                columnWidth: '20%'
                            }
                        }
                    }
                },
                {
                    breakpoint: 381,
                    options: {
                        plotOptions: {
                            bar: {
                                columnWidth: '24%'
                            }
                        }
                    }
                }
            ],
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
            }
        };
    if (typeof totalEarningChartEl !== undefined && totalEarningChartEl !== null) {
        const totalEarningChart = new ApexCharts(totalEarningChartEl, totalEarningChartOptions);
        totalEarningChart.render();
    }
});
</script>
@endsection

@section('content')

<div class="row">
    <!-- Earning Reports -->
    <div class="col-lg-6 mb-4">
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
                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
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
                        <div id="monthlyEarningReports"></div>
                    </div>
                </div>
                <div class="border rounded p-3 mt-4">
                    <div class="row gap-4 gap-sm-0">
                        <div class="col-12 col-sm-4">
                            <div class="d-flex gap-2 align-items-center">
                                <div class="badge rounded bg-label-primary p-1">
                                    <i class="ti ti-currency-dollar ti-sm"></i>
                                </div>
                                <h6 class="mb-0">Earnings</h6>
                            </div>
                            <h4 class="my-2 pt-1">${{ number_format($currentMonthEarnings, 2) }}</h4>
                            <div class="progress w-75" style="height:4px">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="d-flex gap-2 align-items-center">
                                <div class="badge rounded bg-label-info p-1">
                                    <i class="ti ti-chart-pie-2 ti-sm"></i>
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
                                    <i class="ti ti-brand-paypal ti-sm"></i>
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
    <div class="col-md-6 mb-4">
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
                        <a class="dropdown-item" href="javascript:void(0);">View More</a>
                        <a class="dropdown-item" href="javascript:void(0);">Delete</a>
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
                        <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
                        <a class="dropdown-item" href="javascript:void(0);">View All</a>
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
            <div class="card-datatable table-responsive">
                {!! $dataTable->table(['class' => 'project-table table border-top', 'id' => 'project-table']) !!}
            </div>
        </div>
    </div>
    <!--/ Projects table -->
</div>

@endsection

@section('scripts')
{!! $dataTable->scripts() !!}
@endsection
