@extends('layouts/layoutMaster')

@section('title', 'Invoices')

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

    .filter-invoice-summary {
        cursor: pointer;
    }

    .filter-invoice-summary.active-filter {
        box-shadow: 0 0 0 2px var(--bs-primary);
    }

    .invoice-summary-card-subtitle {
        min-height: 1.125rem;
    }
</style>

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Invoices') }}</h4>
        <p class="text-muted">{{ __('Manage your receipts') }}</p>
    </div>
    @can('create', App\Models\Invoice::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('invoice.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Crear Factura
        </a>
    </div>
    @endcan
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Pendientes de pago</span>
                        <small class="text-muted d-block invoice-summary-card-subtitle">Saldo pendiente</small>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $invoiceStats['unpaid']['amount_label'] }}</h3>
                        </div>
                        <p class="mb-0">{{ $invoiceStats['unpaid']['count'] }} {{ $invoiceStats['unpaid']['count'] === 1 ? 'factura' : 'facturas' }}</p>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-warning filter-invoice-summary" data-filter="unpaid" title="Filtrar pendientes de pago">
                            <i class="ti ti-alert-circle ti-sm"></i>
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
                        <span>Notas de crédito</span>
                        <small class="text-muted d-block invoice-summary-card-subtitle">Últimos 30 días</small>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $invoiceStats['credit_notes']['amount_label'] }}</h3>
                        </div>
                        <p class="mb-0">{{ $invoiceStats['credit_notes']['count'] }} {{ $invoiceStats['credit_notes']['count'] === 1 ? 'nota' : 'notas' }}</p>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-info filter-invoice-summary" data-filter="credit_notes" title="Filtrar notas de crédito">
                            <i class="ti ti-receipt-refund ti-sm"></i>
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
                        <span>Cobradas</span>
                        <small class="text-muted d-block invoice-summary-card-subtitle">Últimos 30 días</small>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $invoiceStats['collected']['amount_label'] }}</h3>
                        </div>
                        <p class="mb-0">{{ $invoiceStats['collected']['count'] }} {{ $invoiceStats['collected']['count'] === 1 ? 'factura' : 'facturas' }}</p>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-success filter-invoice-summary" data-filter="collected" title="Filtrar cobradas">
                            <i class="ti ti-circle-check ti-sm"></i>
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
                        <span>Vencidas</span>
                        <small class="text-muted d-block invoice-summary-card-subtitle">Vencimiento superado</small>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $invoiceStats['overdue']['amount_label'] }}</h3>
                        </div>
                        <p class="mb-0">{{ $invoiceStats['overdue']['count'] }} {{ $invoiceStats['overdue']['count'] === 1 ? 'factura' : 'facturas' }}</p>
                    </div>
                    <div class="avatar">
                        <a href="#" class="avatar-initial rounded bg-label-danger filter-invoice-summary" data-filter="overdue" title="Filtrar vencidas">
                            <i class="ti ti-clock-exclamation ti-sm"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
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

@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>

<script>
    function deleteRecord(id, element) {
        Swal.fire({
            title: 'Are you sure you want to delete this record?',
            text: 'This action cannot be undone',
            icon: 'warning',
            showCloseButton: false,
            showCancelButton: false,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("{{ route('invoice.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
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
                    console.log('Response data:', data);

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
                    } else {
                        console.error('No se encontró la fila correspondiente.');
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Ha ocurrido un error al eliminar el registro', 'error');
                });
            }
        });
    }
</script>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush

{{-- vendor scripts --}}
@section('vendor-script')
<script src="{{asset('vendors/data-tables/js/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
<script src="{{asset('vendors/fullcalendar/lib/moment.min.js')}}"></script>
<script src="{{asset('js/moment/' . app()->getLocale() . '.js')}}"></script>
@endsection
