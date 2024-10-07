@extends('layouts/layoutMaster')

@section('title', 'Notas')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />

<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>

<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/ui-toasts.js')}}"></script>
@endsection

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }
    
    .post-it {
        background-color: #feff9c;
        padding: 20px;
        margin: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: rotate(-2deg);
        transition: transform 0.3s ease;
        width: 250px;
        min-height: 200px;
        display: flex;
        flex-direction: column;
    }
    
    .post-it:hover {
        transform: rotate(0deg) scale(1.05);
    }
    
    .post-it-header {
        font-size: 1.2em;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    .post-it-date {
        font-size: 0.8em;
        color: #666;
        margin-bottom: 10px;
    }
    
    .post-it-content {
        flex-grow: 1;
    }
    
    .post-it-tag {
        align-self: flex-end;
        font-size: 0.9em;
        color: #007bff;
    }
    
    .post-it-administracion { background-color: #feff9c; }
    .post-it-tecnica { background-color: #ffc988; }
    .post-it-comercial { background-color: #b4ff88; }
    .post-it-desarrollo { background-color: #88e1ff; }
</style>

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Notas</h4>
        <p class="text-muted">Administra tus notas</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Administración</h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap">
            <div class="post-it post-it-administracion">
                <div class="post-it-header">Gestión de servicios</div>
                <div class="post-it-date">Pablo</div>
                <div class="post-it-content">
                    Los úlitmos 3 días de cada mes se realizan las gestiones de servicios.
                </div>
                <div class="post-it-tag">12 hs mensuales</div>
            </div>
            <div class="post-it post-it-administracion">
                <div class="post-it-header">Emisión de facturas</div>
                <div class="post-it-date">Pablo</div>
                <div class="post-it-content">
                    Revisión de las facturas de los servicios y aprobación para que se facturen.
                </div>
                <div class="post-it-tag">4 hs mensuales</div>
            </div>
            <div class="post-it post-it-administracion">
                <div class="post-it-header">Pago a proveedores</div>
                <div class="post-it-date">Diego</div>
                <div class="post-it-content">
                    Los mismos se realizan los días jueves de 9 a 12 hs y viernes de 12 a 15 hs.
                </div>
                <div class="post-it-tag">Total de 12 hs al mes</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Técnica</h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap">
            <div class="post-it post-it-tecnica">
                <div class="post-it-header">Atención telefónica</div>
                <div class="post-it-date">Lucio</div>
                <div class="post-it-content">
                    Atención telefónica de lunes a viernes de 8 a 18 hs.
                </div>
                <div class="post-it-tag">Disponibilidad 60 hs semanales</div>
            </div>
            <div class="post-it post-it-tecnica">
                <div class="post-it-header">Atención por WhatsApp</div>
                <div class="post-it-date">Lucio</div>
                <div class="post-it-content">
                    Atención telefónica de lunes a viernes de 8 a 18 hs.
                </div>
                <div class="post-it-tag">Disponibilidad 60 hs semanales</div>
            </div>
            <div class="post-it post-it-tecnica">
                <div class="post-it-header">Alertas de seguridad</div>
                <div class="post-it-date">Diego</div>
                <div class="post-it-content">
                    Disponibilidad ante cualquier imprevisto.
                </div>
                <div class="post-it-tag">Disponibilidad 24 hs</div>
            </div>
            <div class="post-it post-it-tecnica">
                <div class="post-it-header">Control de backups</div>
                <div class="post-it-date">Lucio</div>
                <div class="post-it-content">
                    Revisión de los backups de los servicios y de base de datos.
                </div>
                <div class="post-it-tag">1 hs diaria</div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- vendor scripts --}}
@section('vendor-script')
<script src="{{asset('vendors/data-tables/js/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
<script src="{{asset('vendors/fullcalendar/lib/moment.min.js')}}"></script>
<script src="{{asset('js/moment/' . app()->getLocale() . '.js')}}"></script>
@endsection