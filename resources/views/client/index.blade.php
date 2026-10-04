@extends('layouts/layoutMaster')

@section('title', __('app.clients'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
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

    #client-table_filter {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        float: none;
        width: 100%;
    }

    #client-table_filter label {
        margin-bottom: 0;
    }
</style>

@section('content')
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

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Clients') }}</h4>
            <p class="text-muted">Gestiona y personaliza a tus clientes</p>
        </div>
        @can('create', \App\Models\Enterprise::class)
        <div class="mt-3 mt-md-0">
            <a href="{{ route('client.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> {{ __('Add') }} {{ __('Client') }} </a>
        </div>
        @endcan
    </div>

    <div id="client-archive-filter" class="btn-group btn-group-sm d-none" role="group" aria-label="Filtro de clientes">
        <button type="button" class="btn btn-primary client-archive-filter" data-archived="0">Activas</button>
        <button type="button" class="btn btn-outline-primary client-archive-filter" data-archived="1">Archivadas</button>
    </div>

    <div class="card">
        <div class="card-body">
            {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.clientListArchived = '0';
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.client-archive-filter');
            if (!button) {
                return;
            }
            window.clientListArchived = button.getAttribute('data-archived') || '0';
            document.querySelectorAll('.client-archive-filter').forEach(function (item) {
                var active = item === button;
                item.classList.toggle('btn-primary', active);
                item.classList.toggle('btn-outline-primary', !active);
            });
            if (window.LaravelDataTables && window.LaravelDataTables['client-table']) {
                window.LaravelDataTables['client-table'].draw();
            }
        });
    </script>
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush

@section('vendor-script')
    <script src="{{ asset('vendors/data-tables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
    <script src="{{ asset('vendors/fullcalendar/lib/moment.min.js') }}"></script>
    <script src="{{ asset('js/moment/' . app()->getLocale() . '.js') }}"></script>
@endsection
