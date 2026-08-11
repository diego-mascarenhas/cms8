@extends('layouts/layoutMaster')

@section('title', __('Projects'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
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

    .project-list-card {
        overflow: hidden;
    }

    .project-list-card > .card-body {
        overflow-x: auto;
    }

    #project-table_wrapper,
    #project-table {
        width: 100% !important;
        max-width: 100%;
    }

    #project-table td,
    #project-table th {
        white-space: normal;
        vertical-align: middle;
    }
</style>

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Projects') }}</h4>
        <p class="text-muted">{{ __('Track your projects') }}</p>
    </div>
    @can('create', App\Models\Project::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('project.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> {{ __('Add New Project') }} </a>
    </div>
    @endcan
</div>

@if(session('success'))
<div id="toast-container" class="toast-top-right">
    <div class="toast toast-success" aria-live="polite" style="display: block;">
        <div class="toast-message">{{ session('success') }}</div>
    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
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
                        <span>{{ __('project_status.BUDGET') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $totalBudget ?? 0 }}</h3>
                            <p class="text-secondary mb-0">({{ $budgetPercentage ?? 0 }}%)</p>
                        </div>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-secondary filter-status" data-status="1">
                            <i class="ti ti-pencil-plus ti-sm"></i>
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
                        <span>{{ __('project_status.BUDGETED') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $totalBudgeted ?? 0 }}</h3>
                            <p class="text-warning mb-0">({{ $budgetedPercentage ?? 0 }}%)</p>
                        </div>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-warning filter-status" data-status="2">
                            <i class="ti ti-file-description ti-sm"></i>
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
                        <span>{{ __('project_status.IN_PROGRESS') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $totalInProgress ?? 0 }}</h3>
                            <p class="text-primary mb-0">({{ $inProgressPercentage ?? 0 }}%)</p>
                        </div>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-primary filter-status" data-status="3,7,8,9">
                            <i class="ti ti-player-play ti-sm"></i>
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
                        <span>{{ __('project_status.TO_INVOICE') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $totalToInvoice ?? 0 }}</h3>
                            <p class="text-info mb-0">({{ $toInvoicePercentage ?? 0 }}%)</p>
                        </div>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-info filter-status" data-status="10,11">
                            <i class="ti ti-receipt ti-sm"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card project-list-card">
    <div class="card-body">
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive w-100']) }}
    </div>
</div>

<script>
    function deleteRecord(id, element) {
        Swal.fire({
            title: '{{ __("Are you sure you want to delete this record?") }}',
            text: '{{ __("This action cannot be undone") }}',
            icon: 'warning',
            showCloseButton: false,
            showCancelButton: false,
            confirmButtonColor: '#3085d6',
            confirmButtonText: '{{ __("Yes, delete") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("{{ route('project.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok.');
                    }
                    return response.json();
                }).then(data => {
                    const toastHTML = `
                        <div id="toast-container" class="toast-top-right">
                            <div class="toast toast-success" aria-live="polite" style="display: block;">
                                <div class="toast-message">${data.success}</div>
                            </div>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', toastHTML);
                    var toastElement = document.getElementById('toast-container');
                    var toast = new bootstrap.Toast(toastElement, {
                        animation: true,
                        delay: 3000,
                        autohide: true
                    });
                    toast.show();

                    const row = element.closest('tr');
                    if (row) {
                        row.classList.add('fade-out');
                        row.addEventListener('transitionend', () => {
                            row.remove();
                        });
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    Swal.fire('{{ __("Error") }}', '{{ __("An error occurred while deleting the record") }}', 'error');
                });
            }
        });
    }
</script>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
