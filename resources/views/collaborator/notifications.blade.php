@extends('layouts/layoutMaster')

@section('title', 'Notificaciones')

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

    <!-- Notificaciones Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        @include('collaborator.partials.tabs')
        
        <!-- Notificaciones -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-4">Notificaciones</h5>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom position-relative">
                    <div class="position-absolute end-0 top-0">
                        <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    </div>
                    <div class="avatar avatar-md bg-light-primary rounded-circle">
                        <img src="{{ asset('assets/img/avatars/1.png') }}" alt="avatar">
                    </div>
                    <div>
                        <h6 class="mb-1">Congratulation Flora! 🎉</h6>
                        <p class="mb-1">Won the monthly best seller gold badge</p>
                        <small class="text-muted">Today</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom">
                    <div class="avatar avatar-md bg-light-secondary rounded-circle d-flex align-items-center justify-content-center">
                        <span>VU</span>
                    </div>
                    <div>
                        <h6 class="mb-1">New user registered.</h6>
                        <p class="mb-1">Accepted your connection</p>
                        <small class="text-muted">Yesterday</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom">
                    <div class="avatar avatar-md bg-light-info rounded-circle">
                        <img src="{{ asset('assets/img/avatars/2.png') }}" alt="avatar">
                    </div>
                    <div>
                        <h6 class="mb-1">New message received 📩</h6>
                        <p class="mb-1">You have new message from Natalie</p>
                        <small class="text-muted">11 Aug</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom position-relative">
                    <div class="position-absolute end-0 top-0">
                        <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    </div>
                    <div class="avatar avatar-md bg-light-danger rounded-circle d-flex align-items-center justify-content-center">
                        <span>P</span>
                    </div>
                    <div>
                        <h6 class="mb-1">Paypal</h6>
                        <p class="mb-1">ACME Inc. made new order $1,154</p>
                        <small class="text-muted">25 May</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 position-relative">
                    <div class="position-absolute end-0 top-0">
                        <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    </div>
                    <div class="avatar avatar-md bg-light-success rounded-circle">
                        <img src="{{ asset('assets/img/avatars/3.png') }}" alt="avatar">
                    </div>
                    <div>
                        <h6 class="mb-1">Application has been approved 🚀</h6>
                        <p class="mb-1">Your ABC project application has been approved.</p>
                        <small class="text-muted">19 Mar</small>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button class="btn btn-primary">Enviar notificación personalizada</button>
                </div>
            </div>
        </div>
    </div>
    <!--/ Notificaciones Content -->
</div>

<!-- Include Valoration Modal -->
@include('collaborator.partials.valoration-modal')
@endsection 