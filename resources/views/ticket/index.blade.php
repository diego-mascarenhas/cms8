@extends('layouts/layoutMaster')

@section('title', __('tickets.Tickets'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('tickets.Tickets') }}</h4>
        <p class="text-muted">{{ __('tickets.Support tickets') }}</p>
    </div>
    @can('create', \App\Models\Ticket::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('ticket.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('tickets.New ticket') }}
        </a>
    </div>
    @endcan
</div>

@if (session('success'))
<div id="toast-container" class="toast-top-right">
    <div class="toast toast-success" aria-live="polite" style="display: block;">
        <div class="toast-message">{{ session('success') }}</div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toastElement = document.getElementById('toast-container');
    if (toastElement) {
        var toast = new bootstrap.Toast(toastElement, { animation: true, delay: 3000, autohide: true });
        toast.show();
    }
});
</script>
@endif

<div class="card">
    <div class="card-body">
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
