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

    @include('partials.business-configuration-prompt', [
        'team' => $activeTeam ?? null,
        'dashboardTopRow' => true,
    ])

    <!-- Hour chart  -->
    <div class="card bg-transparent shadow-none mt-4 mb-0 border-0">
        <div class="card-body row p-0 pb-2 align-items-stretch">
            <div class="col-12 col-md-8 mb-4 mb-md-4 mb-lg-3 mb-sm-2">
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
                            <p class="mb-0">{{ __('app.dashboard_metric_new_leads') }}</p>
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
            <div class="col-12 col-md-4 mb-4 mb-md-4 mb-lg-3 mb-sm-2 d-flex flex-column">
                <div class="card w-100 flex-grow-1 d-flex flex-column">
                    <div class="row g-0 flex-grow-1 align-items-stretch">
                        <div class="col-9 min-w-0 d-flex flex-column">
                            <div class="card-body d-flex flex-column flex-grow-1">
                                @php
                                    $insightCardFirstName = explode(' ', (string) auth()->user()->name, 2)[0] ?? '';
                                @endphp
                                @if(auth()->user()->hasAnyRole(['admin', 'root']))
                                    <h5 class="card-title mb-1 fw-semibold">{{ e(__('app.dashboard_assistant_greeting', ['name' => $insightCardFirstName])) }}</h5>
                                    <p class="mb-2 text-body">{{ e(__('app.dashboard_assistant_subtitle')) }}</p>
                                    @if(auth()->user()->can('chat.list') || auth()->user()->hasAnyRole(['admin', 'root']))
                                        <div class="mt-auto pt-2">
                                            <a href="{{ route('chat.index', ['view' => 'assistant']) }}" class="btn btn-sm btn-primary waves-effect waves-light"><i class="ti ti-sparkles ti-sm me-1" aria-hidden="true"></i>{{ __('app.dashboard_open_assistant') }}</a>
                                        </div>
                                    @endif
                                @else
                                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                        <span class="text-primary flex-shrink-0" aria-hidden="true"><i class="ti ti-sparkles ti-sm"></i></span>
                                        <h5 class="card-title mb-0 fw-semibold">{{ __('app.performance_insight_card_greeting_default', ['name' => $insightCardFirstName]) }}</h5>
                                    </div>
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
                                @endif

                                {{-- <h4 class="text-primary mb-1">{{ number_format($currentMonthRevenue, 2, ',', '.') }}€</h4>
                                <p class="text-muted mb-2">
                                    Mes pasado: {{ number_format($lastMonthRevenue, 2, ',', '.') }}€
                                </p> --}}
                                {{-- Strategy & Organization: hidden for now; restore by changing to @if(true) --}}
                                @if(false)
                                <a href="{{ route('strategy.index') }}" class="btn btn-sm btn-primary waves-effect waves-light">Strategia</a>
                                <a href="{{ route('organization.index') }}" class="btn btn-sm btn-primary waves-effect waves-light ms-2">Organización</a>
                                @endif
                            </div>
                        </div>
                        <div class="col-3 text-center text-sm-left flex-shrink-0 d-flex align-items-end justify-content-center">
                            <div class="card-body pb-2 px-0 px-md-4 pt-0">
                                <img src="{{ asset('assets/img/illustrations/card-advance-sale.png') }}" height="140"
                                    alt="" role="presentation">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- View sales -->
        </div>
    </div>
    <!-- Hour chart End  -->

    {{-- Placeholder social stat cards: hidden until real team stats are wired. Set to @if(true) to show. --}}
    @if(false)
        <div class="row mb-4">
            <div class="col-12">
                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #e8b5e6;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">10,77k <i class="ti ti-arrow-up-right text-success"></i></h2>
                                <span class="fs-5 text-body">Instagram</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #8e97ea;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">8.445 <i class="ti ti-arrow-down-right text-danger"></i></h2>
                                <span class="fs-5 text-body">Facebook</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #99a8b1;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">1.511 <i class="ti ti-arrow-up-right text-success"></i></h2>
                                <span class="fs-5 text-body">TikTok</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #f58462;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">1.070</h2>
                                <span class="fs-5 text-body">YouTube</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #5f6bdc;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">31</h2>
                                <span class="fs-5 text-body">Bluesky</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

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

    <div class="row align-items-lg-stretch">
        <!-- Emotional Balance (right column) -->
        <div class="col-lg-4 order-lg-2 mb-4 mb-lg-0 d-flex flex-column">
            <!-- Emotional Balance -->
            <div class="card mb-4 flex-grow-1 d-flex flex-column w-100">
                <div class="card-header pb-0 d-flex justify-content-between mb-lg-n4">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Balance emocional</h5>
                        <small class="text-muted">¡Bravo! Estás en el buen camino</small>
                    </div>
                </div>
                <div class="card-body flex-grow-1 d-flex flex-column">
                    <div class="row flex-grow-1">
                        <div class="col-12 d-flex flex-column flex-grow-1">
                            <div class="sentiment-chart flex-grow-1 d-flex flex-column">
                                <div class="d-flex align-items-end justify-content-between flex-grow-1">
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

            {{-- Activity Feed section removed - Activity Log package was removed from the project --}}
        </div>

        <!-- Main Content Column -->
        <div class="col-lg-8 order-lg-1 d-flex flex-column">
            <!-- Today's contacts — paired with emotional balance; matching header + equal card height (lg+) -->
            <div class="card mb-4 flex-grow-1 d-flex flex-column w-100">
                <div class="card-header pb-0 d-flex justify-content-between mb-lg-n4">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Contactos para hoy</h5>
                        <small class="text-muted">Lista de seguimiento diario</small>
                    </div>
                </div>
                <div class="card-body flex-grow-1 d-flex flex-column">
                    <div class="table-responsive flex-grow-1">
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

    @if(isset($activeTeam) && $activeTeam->hasModule('projects'))
    <div class="row mb-4">
        <div class="col-12">
            <div class="card mb-0">
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
        </div>
    </div>
    @endif

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
