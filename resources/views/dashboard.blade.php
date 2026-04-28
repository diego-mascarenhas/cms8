@extends('layouts/layoutMaster')

@section('title', __('app.dashboard'))

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
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/swiper/swiper.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/dashboards-analytics.js') }}"></script>

    @if(auth()->user()->hasRole(['root', 'admin']) && !empty($tokenStats['byModule']))
    <script>
        (function() {
            const moduleData = @json($tokenStats['byModule']);
            const labels = [];
            const series = [];
            const colors = ['#696cff', '#8592a3', '#71dd37', '#ffab00', '#ff3e1d', '#03c3ec'];

            Object.values(moduleData).forEach(module => {
                if (module.tokens_used > 0) {
                    labels.push(module.module_name);
                    series.push(module.tokens_used);
                }
            });

            if (series.length > 0) {
                const chart = new ApexCharts(document.querySelector("#tokensByModuleChart"), {
                    chart: {
                        type: 'donut',
                        height: 240,
                        fontFamily: 'Public Sans'
                    },
                    series: series,
                    labels: labels,
                    colors: colors,
                    stroke: {
                        width: 0
                    },
                    dataLabels: {
                        enabled: false
                    },
                    legend: {
                        show: true,
                        position: 'bottom',
                        horizontalAlign: 'center',
                        fontSize: '13px',
                        fontFamily: 'Public Sans',
                        markers: {
                            width: 10,
                            height: 10,
                            offsetX: -3
                        },
                        itemMargin: {
                            horizontal: 8,
                            vertical: 5
                        },
                        offsetY: 0
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '75%',
                                labels: {
                                    show: true,
                                    name: {
                                        show: false
                                    },
                                    value: {
                                        show: true,
                                        fontSize: '24px',
                                        fontWeight: 600,
                                        color: '#566a7f',
                                        offsetY: 5,
                                        formatter: val => parseInt(val).toLocaleString()
                                    },
                                    total: {
                                        show: true,
                                        showAlways: true,
                                        fontSize: '13px',
                                        fontWeight: 400,
                                        color: '#a1acb8',
                                        label: 'Total tokens',
                                        formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString()
                                    }
                                }
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: val => val.toLocaleString() + ' tokens'
                        }
                    },
                    responsive: [{
                        breakpoint: 480,
                        options: {
                            chart: {
                                height: 200
                            },
                            legend: {
                                fontSize: '12px'
                            }
                        }
                    }]
                });
                chart.render();
            }
        })();
    </script>
    @endif

    @if(!empty($analyticsChartData) && !empty($analyticsChartData['dates']))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const analyticsChartEl = document.querySelector('#analyticsChart');
            if (analyticsChartEl) {
                const chartData = @json($analyticsChartData);
                new ApexCharts(analyticsChartEl, {
                    chart: {
                        type: 'line',
                        height: 280,
                        fontFamily: 'Public Sans',
                        toolbar: { show: false },
                        zoom: { enabled: false }
                    },
                    stroke: { curve: 'smooth', width: 2 },
                    series: [
                        { name: 'Visitors', data: chartData.visitors },
                        { name: 'Page Views', data: chartData.pageViews }
                    ],
                    xaxis: {
                        categories: chartData.dates,
                        labels: {
                            formatter: function(val) {
                                return val ? new Date(val).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : val;
                            }
                        }
                    },
                    yaxis: {
                        labels: { formatter: function(val) { return val ? parseInt(val, 10) : val; } }
                    },
                    legend: { position: 'top', horizontalAlign: 'right' },
                    colors: ['#696cff', '#71dd37'],
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#e7e7e7', strokeDashArray: 4 }
                }).render();
            }
        });
    </script>
    @endif
@endsection

@section('content')

    <!-- Hour chart  -->
    <div class="card bg-transparent shadow-none my-4 border-0">
        <div class="card-body row p-0 pb-2">
            <div class="col-12 col-md-8 mb-4 mb-md-4 mb-lg-3 mb-sm-2">
                <h3>{{ __('app.welcome') }}</h3>
                @include('partials.business-configuration-prompt', ['team' => $activeTeam ?? null])
                <div class="col-12 col-lg-12">
                    @php
                        $weeklyGoals = [
                            'Esta semana me comprometo a escuchar activamente a cada cliente',
                            'Mi objetivo es identificar nuevas oportunidades en cada conversación',
                            'Me enfocaré en fortalecer la relación con los clientes más antiguos',
                            'Buscaré convertir cada interacción en una experiencia positiva',
                            'Me propongo dar seguimiento oportuno a todas las conversaciones pendientes',
                            'Esta semana mejoraré la calidad de mis notas y registros de contacto',
                            'Me comprometo a identificar y atender las necesidades no expresadas',
                            'Trabajaré en proporcionar soluciones proactivas a mis clientes',
                            'Mi meta es aumentar el nivel de satisfacción de cada cliente',
                            'Me dedicaré a construir relaciones más sólidas y duraderas',
                        ];

                        $randomGoal = $weeklyGoals[array_rand($weeklyGoals)];
                    @endphp
                    <p>{{ $randomGoal }}</p>
                </div>
                <div class="d-flex justify-content-between gap-3 me-5">
                    <div class="d-flex align-items-center gap-3 me-4 me-sm-0">
                        <span class="bg-label-primary p-2 rounded">
                            <i class='ti ti-device-laptop ti-xl'></i>
                        </span>
                        <div class="content-right">
                            <p class="mb-0">Horas invertidas</p>
                            <h4 class="text-primary mb-0">@formatMinutes($totalTeamMinutes)</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="bg-label-success p-2 rounded">
                            <i class='ti ti-target ti-xl'></i>
                        </span>
                        <div class="content-right">
                            <p class="mb-0">Contactos recientes</p>
                            <h4 class="text-success mb-0">{{ $recentLeadsCount }}</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="bg-label-warning p-2 rounded">
                            <i class='ti ti-discount-check ti-xl'></i>
                        </span>
                        <div class="content-right">
                            <p class="mb-0">Para hablar hoy</p>
                            <h4 class="text-warning mb-0">{{ $clientsToContactToday }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View sales -->
            <div class="col-12 col-md-4 mb-4 mb-md-4 mb-lg-3 mb-sm-2">
                <div class="card">
                    <div class="d-flex align-items-end row">
                        <div class="col-7">
                            <div class="card-body text-nowrap">
                                <h5 class="card-title mb-0">¡Felicitaciones {{ explode(' ', auth()->user()->name)[0] }}! 🎉
                                </h5>
                                @if($mentoringPlan)
                                    @if($mentoringMessage)
                                        <p class="mb-2">{{ $mentoringMessage }}</p>
                                    @endif
                                    @if($mentoringLevelName)
                                        <p class="mb-4"><strong>{{ $mentoringLevelName }}</strong></p>
                                    @endif
                                @elseif($subscriptionLevel)
                                    <p class="mb-2">¡Vas viento en popa!</p>
                                    <p class="mb-4"><span class="badge bg-primary">Plan: {{ $subscriptionLevel->getDisplayName() }}</span></p>
                                @else
                                    <p class="mb-4">¡Vas viento en popa!</p>
                                @endif

                                {{-- <h4 class="text-primary mb-1">{{ number_format($currentMonthRevenue, 2, ',', '.') }}€</h4>
                                <p class="text-muted mb-2">
                                    Mes pasado: {{ number_format($lastMonthRevenue, 2, ',', '.') }}€
                                </p> --}}
                                <a href="{{ route('strategy.index') }}" class="btn btn-sm btn-primary waves-effect waves-light">Strategia</a>
                                <a href="{{ route('organization.index') }}" class="btn btn-sm btn-primary waves-effect waves-light ms-2">Organización</a>
                            </div>
                        </div>
                        <div class="col-5 text-center text-sm-left">
                            <div class="card-body pb-0 px-0 px-md-4">
                                <img src="{{ asset('assets/img/illustrations/card-advance-sale.png') }}" height="140"
                                    alt="view sales">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- View sales -->
        </div>
    </div>
    <!-- Hour chart End  -->

    <div class="row mb-4">
        <div class="col-12">
            @include('partials.dashboard-recent-activities', ['recentContactActivities' => $recentContactActivities ?? collect()])
        </div>
    </div>

    @if(!empty($analyticsChartData) && !empty($analyticsChartData['dates']))
    <!-- Google Analytics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0 d-flex justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Google Analytics</h5>
                        <small class="text-muted">Visitors and page views (last 7 days)</small>
                    </div>
                </div>
                <div class="card-body">
                    <div id="analyticsChart" style="min-height: 280px;"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- / Google Analytics -->
    @endif

    <div class="row">
        <!-- Emotional Balance and Dangerous Clients (right column) -->
        <div class="col-lg-4 order-lg-2 mb-4 mb-lg-0">
            @if(auth()->user()->hasRole(['root', 'admin']))
            <!-- Toon API Usage Widget -->
            <div class="card mb-4">
                <div class="card-header pb-0 d-flex justify-content-between mb-3">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Uso de API & Ahorro</h5>
                        <small class="text-muted">Optimización de costos</small>
                    </div>
                    <div>
                        <a href="{{ route('assistant.activity') }}" class="btn btn-sm btn-label-primary waves-effect" title="Ver actividad AI" aria-label="Ver actividad AI">
                            <i class="ti ti-activity"></i>
                        </a>
                        <a href="{{ route('assistant.documents') }}" class="btn btn-sm btn-label-info waves-effect" title="Ver documentos procesados" aria-label="Ver documentos procesados">
                            <i class="ti ti-file-search"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <!-- Stats Section -->
                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <span class="bg-label-primary p-2 rounded me-2">
                                            <i class='ti ti-api ti-sm'></i>
                                        </span>
                                        <div>
                                            <small class="text-muted d-block mb-1">Llamadas</small>
                                            <h5 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalCalls']) }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <span class="bg-label-success p-2 rounded me-2">
                                            <i class='ti ti-coin ti-sm'></i>
                                        </span>
                                        <div>
                                            <small class="text-muted d-block mb-1">Ahorro</small>
                                            <h5 class="mb-0 text-success">{{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalTokensSaved']) }}</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Section -->
                        @if(!empty($tokenStats['byModule']))
                        <div class="col-12">
                            <div id="tokensByModuleChart"></div>
                        </div>
                        @endif

                        <!-- Progress Bar Section -->
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted">Tokens ahorrados</small>
                                <span class="badge bg-label-success">{{ $tokenStats['averageSavings'] }}%</span>
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar bg-success"
                                     role="progressbar"
                                     style="width: {{ $tokenStats['averageSavings'] }}%;"
                                     aria-valuenow="{{ $tokenStats['averageSavings'] }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">
                                    Usados: {{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalTokensUsed']) }}
                                </small>
                                <small class="text-muted">
                                    Sin optimización: {{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalTokensWithoutToon']) }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Emotional Balance -->
            <div class="card mb-4">
                <div class="card-header pb-0 d-flex justify-content-between mb-lg-n4">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Balance emocional</h5>
                        <small class="text-muted">¡Bravo! Estás en el buen camino</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="sentiment-chart">
                                <div class="d-flex align-items-end justify-content-between">
                                    @foreach ($sentimentData as $index => $sentiment)
                                        <div class="sentiment-column text-center">
                                            <div class="sentiment-bar" style="height: calc({{ $sentiment['count'] && max(array_column($sentimentData, 'count')) ? ($sentiment['count'] / max(array_column($sentimentData, 'count'))) * 150 : 0 }}px)">
                                                <span class="sentiment-count">{{ $sentiment['count'] }}</span>
                                            </div>
                                            <div class="sentiment-emoji mt-2">
                                                @switch($index)
                                                    @case(0)
                                                        😡
                                                    @break

                                                    @case(1)
                                                        🙁
                                                    @break

                                                    @case(2)
                                                        😐
                                                    @break

                                                    @case(3)
                                                        🙂
                                                    @break

                                                    @case(4)
                                                        🥳
                                                    @break
                                                @endswitch
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clients in danger -->
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="m-0 me-2">Clientes en peligro</h5>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless border-top">
                        <tbody>
                            @if(isset($dangerousContacts) && $dangerousContacts->count() > 0)
                                @foreach($dangerousContacts as $contact)
                                    <tr>
                                        <td class="pt-2">
                                            <div
                                                class="d-flex justify-content-start align-items-center @if ($loop->first) mt-lg-4 @endif">
                                                <div class="d-flex flex-column">
                                                    <h6 class="mb-0">
                                                        <a
                                                            href="{{ route('contact.show', $contact->id) }}">{{ $contact->name }}</a>
                                                    </h6>
                                                    @if ($contact->enterprise)
                                                        <small class="text-muted">{{ $contact->enterprise->name }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end @if ($loop->first) pt-2 @endif">
                                            <div class="user-progress @if ($loop->first) mt-lg-4 @endif">
                                                <p class="mb-0 fw-medium" style="font-size: 1.5em;">
                                                    {{ $contact->currentSentiment->sentiment->emoji }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="2" class="text-center py-4">
                                        <i class="ti ti-mood-smile text-success ti-2x mb-2"></i>
                                        <p class="mb-0">No hay clientes en situación de riesgo</p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Activity Feed section removed - Activity Log package was removed from the project --}}
        </div>

        <!-- Main Content Column -->
        <div class="col-lg-8 order-lg-1">
            <!-- Ongoing Projects (only when team has projects module) -->
            @if(isset($activeTeam) && $activeTeam->hasModule('projects'))
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="m-0 me-2">{{ __('Ongoing Projects') }}</h5>
                        <small class="text-muted">{{ __('Current active projects') }}</small>
                    </div>
                    <div class="dropdown">
                        <a href="{{ route('project-list') }}" class="btn btn-primary btn-sm">
                            <i class="ti ti-list ti-xs me-1"></i>{{ __('View All') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless border-top">
                            <thead>
                                <tr>
                                    <th>{{ __('Project') }}</th>
                                    <th class="text-center">{{ __('Status') }}</th>
                                    <th class="text-center">{{ __('Hours') }}</th>
                                    <th class="text-center">{{ __('Tasks') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ongoingProjects as $project)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <h6 class="mb-0 text-truncate" style="max-width: 250px;">{{ $project->name }}</h6>
                                                <small class="text-muted">{{ $project->client->name ?? 'N/A' }}</small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            {!! $project->status_label !!}
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center">
                                                @php
                                                    $totalHours = $project->total_hours ?? 0;
                                                    $estimatedHours = $project->estimated_hours ?? 0;
                                                @endphp
                                                <span class="fw-semibold">{{ $totalHours }}h</span>
                                                @if($estimatedHours > 0)
                                                    <small class="text-muted">/ {{ $estimatedHours }}h</small>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @if($project->status_id == 9)
                                                {{-- IN_PROGRESS: Show Kanban icon --}}
                                                <a href="{{ route('task.index', ['view' => 'kanban', 'project_id' => $project->id]) }}" class="text-body" title="{{ __('View Kanban') }}">
                                                    <i class="ti ti-layout-kanban ti-sm"></i>
                                                </a>
                                            @else
                                                {{-- BUDGET/BUDGETED: Show eye icon to view details --}}
                                                <a href="{{ route('project.show', $project->id) }}" class="text-body" title="{{ __('View Details') }}">
                                                    <i class="ti ti-eye ti-sm"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="ti ti-mood-check text-success ti-3x mb-3"></i>
                                            <h5>{{ __('No ongoing projects') }}</h5>
                                            <p class="text-muted">{{ __('All projects are completed or not yet started') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Today's Contacts (Restored) -->
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="m-0 me-2">Contactos para hoy</h5>
                        <small class="text-muted">Lista de seguimiento diario</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless">
                            @if(isset($todayContacts) && $todayContacts->count() > 0 && $todayContacts->first()->contact)
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Sentimiento</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($todayContacts as $contact)
                                        @if($contact->contact)
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <h6 class="mb-0">
                                                            <a href="{{ route('contact.show', $contact->contact->id) }}">{{ $contact->contact->name }}</a>
                                                        </h6>
                                                        @if($contact->contact->enterprise)
                                                            <small class="text-muted">{{ $contact->contact->enterprise->name }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge rounded-pill {{ $contact->status->label_class }}">
                                                        {{ $contact->status->name }}
                                                    </span>
                                                </td>
                                                <td class="text-center">{{ $contact->contact->currentSentiment->sentiment->emoji ?? '' }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('contact.show', $contact->contact->id) }}" class="btn btn-sm btn-primary rounded-pill">
                                                        <i class="ti ti-phone-call me-1"></i>Contactar
                                                    </a>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            @else
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="ti ti-checkbox text-success ti-3x mb-3"></i>
                                            <h5>¡Todo al día!</h5>
                                            <p class="text-muted">Has completado todas las tareas programadas para hoy</p>
                                        </td>
                                    </tr>
                                </tbody>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

<style>
    .sentiment-chart {
        padding: 1rem 0;
    }

    .sentiment-column {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0 5px;
    }

    .sentiment-bar {
        width: 100%;
        max-width: 60px;
        background-color: #696cff;
        border-radius: 8px;
        position: relative;
        min-height: 30px;
        transition: height 0.3s ease;
    }

    .sentiment-count {
        position: absolute;
        top: -25px;
        left: 50%;
        transform: translateX(-50%);
        color: #566a7f;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .sentiment-emoji {
        font-size: 1.5rem;
        margin-top: 0.5rem;
    }

    .sentiment-column:nth-child(1) .sentiment-bar {
        background-color: #ff4d4f;
    }

    .sentiment-column:nth-child(2) .sentiment-bar {
        background-color: #ffa39e;
    }

    .sentiment-column:nth-child(3) .sentiment-bar {
        background-color: #ffd666;
    }

    .sentiment-column:nth-child(4) .sentiment-bar {
        background-color: #95de64;
    }

    .sentiment-column:nth-child(5) .sentiment-bar {
        background-color: #52c41a;
    }

    .sentiment-chart .d-flex {
        height: 150px !important;
        margin-top: 2rem;
    }

    @media (max-width: 576px) {
        .sentiment-column {
            padding: 0 2px;
        }

        .sentiment-bar {
            max-width: 40px;
        }

        .sentiment-count {
            font-size: 0.75rem;
        }

        .sentiment-emoji {
            font-size: 1.2rem;
        }

        .sentiment-chart .d-flex {
            height: 120px !important;
        }
    }

    /* Activity Feed Styles */
    .activity-feed {
        max-height: 400px;
        overflow-y: auto;
    }

    .activity-item {
        transition: background-color 0.2s ease;
    }

    .activity-item:hover {
        background-color: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        padding: 8px;
        margin: -8px;
        margin-bottom: 4px;
    }

    .activity-content p {
        line-height: 1.4;
        font-size: 0.875rem;
    }

    .activity-content small {
        font-size: 0.75rem;
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
</style>
