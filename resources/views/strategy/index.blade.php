@extends('layouts/layoutMaster')

@section('title', __('Strategic Growth Framework'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/fontawesome/css/all.min.css') }}">
@endsection

@section('content')

    <h2 class="mb-4">Strategic Growth Framework</h2>

    <div class="row">
        <!-- Card 1 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-success mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-briefcase fa-2x mb-3"></i>
                    <h5 class="card-title">1. tu dossier comercial.</h5>
                    <ul class="list-unstyled">
                        <li>Cliente</li>
                        <li>Destino</li>
                        <li>Oferta</li>
                        <li>Storytelling</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 2 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-success mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-globe fa-2x mb-3"></i>
                    <h5 class="card-title">2. tu fachada digital.</h5>
                    <ul class="list-unstyled">
                        <li>Web</li>
                        <li>RRSS</li>
                        <li>SEO/SEM</li>
                        <li>Estrategia contenido</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 3 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-success mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-gamepad fa-2x mb-3"></i>
                    <h5 class="card-title">3. entender tu juego.</h5>
                    <ul class="list-unstyled">
                        <li>Audiencia</li>
                        <li>Dinero</li>
                        <li>Contactos</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 4 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-warning mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-sync-alt fa-2x mb-3"></i>
                    <h5 class="card-title">4. tu embudo en automático.</h5>
                    <ul class="list-unstyled">
                        <li>Doblar lo que funciona</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 5 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-warning mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-filter fa-2x mb-3"></i>
                    <h5 class="card-title">5. tu embudo de operaciones.</h5>
                    <ul class="list-unstyled">
                        <li>Talento</li>
                        <li>Herramientas</li>
                        <li>IA</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 6 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-warning mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-book fa-2x mb-3"></i>
                    <h5 class="card-title">6. tu business playbook.</h5>
                    <ul class="list-unstyled">
                        <li>Manual de procesos</li>
                        <li>Wiki Notion</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 7 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-warning mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-chart-line fa-2x mb-3"></i>
                    <h5 class="card-title">7. scale framework.</h5>
                    <ul class="list-unstyled">
                        <li>Up / Down / Cross</li>
                        <li>Creación de audiencia</li>
                        <li>Embudo stories</li>
                        <li>Warm up leads</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 8 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-primary mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-compress fa-2x mb-3"></i>
                    <h5 class="card-title">8. simplificar tu negocio.</h5>
                    <ul class="list-unstyled">
                        <li>80/20</li>
                        <li>5' business pitch</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 9 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-primary mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-user-slash fa-2x mb-3"></i>
                    <h5 class="card-title">9. quitar al fundador.</h5>
                    <ul class="list-unstyled">
                        <li>Auditar Calendar</li>
                        <li>Buyback your time</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 10 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-primary mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-users fa-2x mb-3"></i>
                    <h5 class="card-title">10. crear tus managers.</h5>
                    <ul class="list-unstyled">
                        <li>Liderazgo</li>
                        <li>Operativa diaria</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 11 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-primary mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-palette fa-2x mb-3"></i>
                    <h5 class="card-title">11. generar tu cultura.</h5>
                    <ul class="list-unstyled">
                        <li>Visionboard empresa</li>
                        <li>Visionboard empleados</li>
                        <li>Retiros de equipo</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Card 12 -->
        <div class="col-md-4 mb-4">
            <div class="card text-center border border-primary mb-3" style="height: 100%;">
                <div class="card-body">
                    <i class="fas fa-door-open fa-2x mb-3"></i>
                    <h5 class="card-title">12. business exit.</h5>
                    <ul class="list-unstyled">
                        <li>Auditar valor empresa</li>
                        <li>Plan de salida</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
