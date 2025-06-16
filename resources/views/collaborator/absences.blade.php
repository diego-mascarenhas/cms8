@extends('layouts/layoutMaster')

@section('title', 'Ausencias')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Collaborator Sidebar -->
    @include('collaborator.partials.sidebar')
    <!--/ Collaborator Sidebar -->

    <!-- Ausencias Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        @include('collaborator.partials.tabs')
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-4">Ausencias</h5>
                <p>Ejemplo de contenido para la sección de ausencias.</p>
            </div>
        </div>
    </div>
    <!--/ Ausencias Content -->
</div>

<!-- Include Valoration Modal -->
@include('collaborator.partials.valoration-modal')
@endsection 