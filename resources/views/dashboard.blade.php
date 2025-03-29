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
    <script src="{{ asset('assets/vendor/libs/swiper/swiper.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/dashboards-analytics.js') }}"></script>
@endsection

@section('content')

    <!-- Hour chart  -->
    <div class="card bg-transparent shadow-none my-4 border-0">
        <div class="card-body row p-0 pb-2">
            <div class="col-12 col-md-8 mb-4 mb-md-4 mb-lg-3 mb-sm-2">
                <h3>{{ __('app.welcome') }}</h3>
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
                                <p class="mb-2">¡Vas viento en popa!</p>
                                <h4 class="text-primary mb-1">{{ number_format($currentMonthRevenue, 2, ',', '.') }}€</h4>
                                <p class="text-muted mb-2">
                                    Mes pasado: {{ number_format($lastMonthRevenue, 2, ',', '.') }}€
                                </p>
                                <a href="{{ route('strategy.index') }}" class="btn btn-sm btn-primary">Strategia</a>
                                <a href="{{ route('organization.index') }}" class="btn btn-sm btn-primary ms-2">Organización</a>
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

    <div class="row">
        <!-- Emotional Balance and Dangerous Clients (right column) -->
        <div class="col-lg-4 order-lg-2 mb-4 mb-lg-0">
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
            <div class="card">
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
        </div>

        <!-- Main Content Column -->
        <div class="col-lg-8 order-lg-1">
            <!-- Ongoing Projects -->
            @if(isset($ongoingProjects))
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
                                    <th class="text-center">{{ __('Client') }}</th>
                                    <th class="text-center">{{ __('Status') }}</th>
                                    <th class="text-center">{{ __('Progress') }}</th>
                                    <th class="text-center">{{ __('Responsible') }}</th>
                                    <th class="text-center">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ongoingProjects as $project)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <h6 class="mb-0 text-truncate" style="max-width: 180px;">{{ $project->name }}</h6>
                                                <small class="text-muted">{{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d M Y') : 'N/A' }}</small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column">
                                                <span>{{ $project->client->name ?? 'N/A' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            {!! $project->status_label !!}
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center align-items-center gap-3">
                                                @php
                                                    // Calculate days remaining
                                                    $progress = 0;
                                                    $today = \Carbon\Carbon::now();
                                                    $endDate = $project->end_date ? \Carbon\Carbon::parse($project->end_date) : null;
                                                    $startDate = $project->start_date ? \Carbon\Carbon::parse($project->start_date) : null;
                                                    
                                                    if ($startDate && $endDate) {
                                                        $totalDays = $startDate->diffInDays($endDate);
                                                        $daysElapsed = $startDate->diffInDays($today);
                                                        $progress = $totalDays > 0 ? min(100, round(($daysElapsed / $totalDays) * 100)) : 0;
                                                    }
                                                @endphp
                                                <div class="progress w-100" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" style="width: {{ $progress }}%" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <small class="fw-semibold">{{ $progress }}%</small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            {{ $project->responsible->name ?? 'N/A' }}
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('project.edit', $project->id) }}" class="btn btn-sm btn-icon">
                                                <i class="ti ti-pencil text-primary"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
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
        padding: 0 10px;
    }

    .sentiment-bar {
        width: 60px;
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
    }

    .sentiment-emoji {
        font-size: 1.8rem;
        margin-top: 1rem;
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
        height: 200px !important;
        margin-top: 2rem;
    }

    .sentiment-bar {
        height: 100%;
        max-height: 250px; /* Ajusta este valor según necesites */
    }
</style>
