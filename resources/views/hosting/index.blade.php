@extends('layouts/layoutMaster')

@section('title', __('app.domains'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
@endsection

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }
</style>

@section('content')
    <!-- Título y botón de agregar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">Administración de Hosting</h4>
            <p class="text-muted">Administra dominios y servidores</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route(Route::has('hosting.create') ? 'hosting.create' : 'domain.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>
                Agregar Hosting
            </a>
        </div>
    </div>

    @if (session('success'))
        <div id="toast-container" class="toast-top-right">
            <div class="toast toast-success" aria-live="polite" style="display: block;">
                <div class="toast-client">{{ session('success') }}</div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var toastElement = document.getElementById('toast-container');
                var toast = new bootstrap.Toast(toastElement, {
                    animation: true,
                    delay: 1000,
                    autohide: true
                });
                toast.show();
            });
        </script>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Total Servers</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $servers->count() }}</h3>
                            </div>
                            <p class="mb-0">Active Servers</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-server ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Online</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $servers->where('success', true)->count() }}</h3>
                                <p class="text-success mb-0">({{ $servers->count() > 0 ? round(($servers->where('success', true)->count() / $servers->count()) * 100) : 0 }}%)</p>
                            </div>
                            <p class="mb-0">Servers Online</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-check ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>SSL Valid</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $servers->filter->hasSsl()->count() }}</h3>
                                <p class="text-primary mb-0">({{ $servers->count() > 0 ? round(($servers->filter->hasSsl()->count() / $servers->count()) * 100) : 0 }}%)</p>
                            </div>
                            <p class="mb-0">Valid Certificates</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-shield-check ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Offline</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $servers->where('success', false)->count() }}</h3>
                                <p class="text-danger mb-0">({{ $servers->count() > 0 ? round(($servers->where('success', false)->count() / $servers->count()) * 100) : 0 }}%)</p>
                            </div>
                            <p class="mb-0">Servers Offline</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-danger">
                                <i class="ti ti-alert-triangle ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{ $dataTable->table() }}
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush

@section('vendor-script')
    <script src="{{ asset('vendors/data-tables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
    <script src="{{ asset('vendors/fullcalendar/lib/moment.min.js') }}"></script>
    <script src="{{ asset('js/moment/' . app()->getLocale() . '.js') }}"></script>
@endsection