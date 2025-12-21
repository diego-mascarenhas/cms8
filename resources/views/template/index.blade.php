@extends('layouts/layoutMaster')

@section('title', 'Templates')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
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
</style>

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Templates</h4>
        <p class="text-muted">Create and manage email templates with visual editor</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('template.create') }}" type="submit" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-plus me-1"></i>Create New Template
        </a>
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

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>

<script>
    function deleteRecord(id, element) {
        Swal.fire({
            title: 'Are you sure you want to delete this template?',
            text: 'This action cannot be undone',
            icon: 'warning',
            showCloseButton: false,
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("{{ route('template.destroy', ['hashedId' => ':ID']) }}".replace(':ID', id), {
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
                    Swal.fire('Error', 'Ha ocurrido un error al eliminar el template', 'error');
                });
            }
        });
    }
</script>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
