@extends('layouts/layoutMaster')

@section('title', 'Analytics')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/swiper/swiper.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
@endsection

@section('page-style')
    <!-- Page -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/cards-advance.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
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
        <div class="card-body row p-0 pb-3">
            <div class="col-12 col-md-8 mb-4 mb-md-4 mb-lg-3 mb-sm-2">
                <h3>{{ __('app.welcome') }}</h3>
                <div class="col-12 col-lg-7">
                    <p>Me comprometo a hacer tal cosa esta semana</p>
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
                        <span class="bg-label-info p-2 rounded">
                            <i class='ti ti-bulb ti-xl'></i>
                        </span>
                        <div class="content-right">
                            <p class="mb-0">Resultados</p>
                            <h4 class="text-info mb-0">82%</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="bg-label-warning p-2 rounded">
                            <i class='ti ti-discount-check ti-xl'></i>
                        </span>
                        <div class="content-right">
                            <p class="mb-0">Clientes a hablar hoy</p>
                            <h4 class="text-warning mb-0">17</h4>
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
                                <h5 class="card-title mb-0">Felicitaciones {{ explode(' ', auth()->user()->name)[0] }}! 🎉
                                </h5>
                                <p class="mb-2">Vas viento en popa!</p>
                                <h4 class="text-primary mb-1">$48.9k</h4>
                                <a href="javascript:;" class="btn btn-sm btn-primary">Pasar a Nivel 5</a>
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
        <!-- Clients in danger -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="m-0 me-2">Clientes en peligro</h5>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless border-top">
                        <tbody>
                            @foreach($dangerousContacts as $contact)
                            <tr>
                                <td class="pt-2">
                                    <div class="d-flex justify-content-start align-items-center @if($loop->first) mt-lg-4 @endif">
                                        <div class="d-flex flex-column">
                                            <h6 class="mb-0"><a href="{{ route('contact.show', $contact->id) }}">{{ $contact->name }}</a></h6>
                                            <small class="text-truncate text-muted"><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end @if($loop->first) pt-2 @endif">
                                    <div class="user-progress @if($loop->first) mt-lg-4 @endif">
                                        <p class="mb-0 fw-medium" style="font-size: 1.5em;">{{ $contact->currentSentiment->sentiment->emoji }}</p>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--/ Clients in danger -->

        <!-- Earning Reports -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0 d-flex justify-content-between mb-lg-n4">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Balance emocional</h5>
                        <small class="text-muted">¡Bravo! Estás en el buen camino</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-4 d-flex flex-column align-self-end">
                            <div class="d-flex gap-2 align-items-center mb-2 pb-1 flex-wrap">
                                <div class="badge rounded bg-label-success">+4.2%</div>
                            </div>
                            <small>Comparación con la semana pasada</small>
                        </div>
                        <div class="col-12 col-md-8">
                            <div id="weeklyEarningReports"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--/ Earning Reports -->
    </div>

    {{-- <div class="row">
        <!-- Activity Timeline -->
        <div class="col-lg-6 col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title m-0 me-2 pt-1 mb-2 d-flex align-items-center"><i
                            class="ti ti-list-details ms-n1 me-2"></i> Actividad</h5>
                    <div class="dropdown">
                        <button class="btn p-0" type="button" id="timelineWapper" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="timelineWapper">
                            <a class="dropdown-item" href="javascript:void(0);">Descargar</a>
                            <a class="dropdown-item" href="javascript:void(0);">Actualizar</a>
                            <a class="dropdown-item" href="javascript:void(0);">Compartir</a>
                        </div>
                    </div>
                </div>
                <div class="card-body pb-0">
                    <ul class="timeline ms-1 mb-0">
                        <li class="timeline-item timeline-item-transparent ps-4">
                            <span class="timeline-point timeline-point-warning"></span>
                            <div class="timeline-event">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Reunión con el cliente</h6>
                                    <small class="text-muted">Hoy</small>
                                </div>
                                <p class="mb-2">Reunión de proyecto con John a las 10:15 am</p>
                                <div class="d-flex flex-wrap">
                                    <div class="avatar me-2">
                                        <img src="{{ asset('assets/img/avatars/3.png') }}" alt="Avatar"
                                            class="rounded-circle" />
                                    </div>
                                    <div class="ms-1">
                                        <h6 class="mb-0">Lester McCarthy (Cliente)</h6>
                                        <span>CEO de Infibeam</span>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li class="timeline-item timeline-item-transparent ps-4">
                            <span class="timeline-point timeline-point-primary"></span>
                            <div class="timeline-event">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Crear un nuevo proyecto para el cliente</h6>
                                    <small class="text-muted">Hace 2 días</small>
                                </div>
                                <p class="mb-0">Agregar archivos a la nueva carpeta de diseño</p>
                            </div>
                        </li>
                        <li class="timeline-item timeline-item-transparent ps-4">
                            <span class="timeline-point timeline-point-info"></span>
                            <div class="timeline-event">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Se compartieron 2 nuevos archivos de proyecto</h6>
                                    <small class="text-muted">Hace 6 días</small>
                                </div>
                                <p class="mb-2">Enviado por Mollie Dixon</p>
                                <div class="d-flex flex-wrap gap-2 pt-1">
                                    <a href="javascript:void(0)" class="me-3 d-flex align-items-center">
                                        <i class="ti ti-file-text text-warning me-2 ti-xs"></i>
                                        <span class="fw-medium text-heading">Directrices de la aplicación</span>
                                    </a>
                                    <a href="javascript:void(0)" class="d-flex align-items-center">
                                        <i class="ti ti-table text-success me-2 ti-xs"></i>
                                        <span class="fw-medium text-heading">Resultados de las pruebas</span>
                                    </a>
                                </div>
                            </div>
                        </li>
                        <li class="timeline-item timeline-item-transparent ps-4 border-transparent">
                            <span class="timeline-point timeline-point-secondary"></span>
                            <div class="timeline-event pb-0">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Se actualizó el estado del proyecto</h6>
                                    <small class="text-muted">Hace 10 días</small>
                                </div>
                                <p class="mb-0">Aplicación de WooCommerce iOS completada</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div> --}}

@endsection
