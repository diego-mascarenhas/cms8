@extends('layouts/layoutMaster')

@section('title', 'Employees')

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
		<h4 class="mb-1 mt-3">Employees</h4>
		<p class="text-muted">Manage your employees</p>
	</div>
	@can('employee.create')
	<div class="mt-3 mt-md-0">
		<a href="{{ route('employee.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> Add Employee </a>
	</div>
	@endcan
</div>

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Total Employees</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $dashboardStats['totalEmployees'] ?? 0 }}</h3>
                                <p class="text-primary mb-0">(100%)</p>
                            </div>
                            <p class="mb-0">Total de empleados</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-primary filter-status" data-status="all">
                                <i class="ti ti-users ti-sm"></i>
                            </a>
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
                            <span>Active Employees</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $dashboardStats['activeEmployees'] ?? 0 }}</h3>
                                <p class="text-success mb-0">({{ $dashboardStats['activeEmployees'] && $dashboardStats['totalEmployees'] ? round(($dashboardStats['activeEmployees'] / $dashboardStats['totalEmployees']) * 100, 2) : 0 }}%)</p>
                            </div>
                            <p class="mb-0">Empleados activos</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-success filter-status" data-status="active">
                                <i class="ti ti-user-check ti-sm"></i>
                            </a>
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
                            <span>New This Week</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $dashboardStats['newThisWeek'] ?? 0 }}</h3>
                                <p class="text-info mb-0">({{ $dashboardStats['newThisWeek'] && $dashboardStats['totalEmployees'] ? round(($dashboardStats['newThisWeek'] / $dashboardStats['totalEmployees']) * 100, 2) : 0 }}%)</p>
                            </div>
                            <p class="mb-0">Nuevos esta semana</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-info filter-status" data-status="new">
                                <i class="ti ti-user-plus ti-sm"></i>
                            </a>
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
                            <span>Inactive</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ ($dashboardStats['totalEmployees'] ?? 0) - ($dashboardStats['activeEmployees'] ?? 0) }}</h3>
                                <p class="text-warning mb-0">({{ $dashboardStats['totalEmployees'] && $dashboardStats['activeEmployees'] ? round((($dashboardStats['totalEmployees'] - $dashboardStats['activeEmployees']) / $dashboardStats['totalEmployees']) * 100, 2) : 0 }}%)</p>
                            </div>
                            <p class="mb-0">Empleados inactivos</p>
                        </div>
                        <div class="avatar">
                            <a href="#" class="avatar-initial rounded bg-label-warning filter-status" data-status="inactive">
                                <i class="ti ti-user-off ti-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('employee.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                        <i class="ti ti-plus me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Add Employee</span>
                    </a>
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row gap-3">
                <div class="flex-grow-1">
                    <select id="StatusFilter" class="form-select">
                        <option value="">Filter by status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status['id'] }}">{{ $status['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-grow-1">
                    <select id="CategoryFilter" class="form-select">
                        <option value="">Filter by category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

        <script>
        $(document).ready(function() {
            // Initialize DataTable when it's ready
            setTimeout(function() {
                let table = $('.datatable').DataTable();

                if (table) {
                    $('#StatusFilter').on('change', function() {
                        let selectedValue = $(this).val();
                        if (selectedValue) {
                            table.column(7).search(selectedValue).draw(); // status_id column
                        } else {
                            table.column(7).search('').draw();
                        }
                    });

                    $('#CategoryFilter').on('change', function() {
                        let selectedValue = $(this).val();
                        if (selectedValue) {
                            table.column(5).search(selectedValue).draw(); // command column
                        } else {
                            table.column(5).search('').draw();
                        }
                    });

                    $('.filter-status').on('click', function(e) {
                        e.preventDefault();
                        var status = $(this).data('status');
                        if (status === 'all') {
                            table.column(7).search('').draw(); // status_id column
                        } else {
                            table.column(7).search(status).draw();
                        }
                    });
                }
            }, 1000);
        });

        function deleteRecord(id, element) {
            event.preventDefault();
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¿Deseas eliminar este empleado?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.value) {
                    fetch("{{ route('employee.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        const row = element.closest('tr');
                        if (row) {
                            row.classList.add('fade-out');
                            row.addEventListener('transitionend', () => {
                                row.remove();
                            });
                        }

                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: data.success,
                            customClass: {
                                confirmButton: 'btn btn-success'
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ha ocurrido un error al eliminar el registro',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            }
                        });
                    });
                }
            });
        }
    </script>
@endpush
