@extends('layouts/layoutMaster')

@section('title', 'Productos de Suscripción')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">Productos de Suscripción</h4>
            <p class="text-muted">Gestión de productos (solo edición)</p>
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
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <style>
        .swal2-container {
            z-index: 99999 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .swal2-popup {
            margin: 0 !important;
            position: relative !important;
            top: auto !important;
            left: auto !important;
            transform: none !important;
            background: #fff !important;
            border: none !important;
            border-radius: 0.375rem !important;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1) !important;
            padding: 1.5rem !important;
            max-width: 32em !important;
            width: 100% !important;
            opacity: 1 !important;
        }
        .dark-style .swal2-popup {
            background: #283144 !important;
        }
        .swal2-backdrop-show {
            background-color: rgba(0, 0, 0, 0.4) !important;
        }
        .swal2-shown {
            overflow: hidden !important;
        }
        .swal2-title {
            color: #697a8d !important;
            font-size: 1.5rem !important;
            font-weight: 500 !important;
            margin: 1.875rem 0 1rem 0 !important;
            text-align: center !important;
        }
        .dark-style .swal2-title {
            color: #a1acb8 !important;
        }
        .swal2-html-container {
            color: #697a8d !important;
            margin: 0 0 1rem 0 !important;
            text-align: center !important;
        }
        .dark-style .swal2-html-container {
            color: #a1acb8 !important;
        }
        .swal2-icon {
            margin: 0 auto 1rem auto !important;
        }
        .swal2-icon.swal2-question .swal2-icon-content {
            display: none !important;
        }
        .swal2-actions {
            justify-content: center !important;
            margin-top: 1rem !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle send SLA button clicks
            document.querySelectorAll('.send-sla-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    sendSLA(productId);
                });
            });
        });

        function sendSLA(productId) {
            Swal.fire({
                title: '¿Enviar SLA?',
                text: 'Se enviará un email al cliente con el link de aceptación del SLA.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí',
                cancelButtonText: 'No',
                buttonsStyling: false,
                allowOutsideClick: true,
                allowEscapeKey: true,
                focusConfirm: false,
                customClass: {
                    confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
                    cancelButton: 'btn btn-label-secondary waves-effect waves-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('sla.send', ['productId' => ':ID']) }}".replace(':ID', productId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    }).then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok.');
                        }
                        return response.json();
                    }).then(data => {
                        Swal.fire({
                            icon: 'success',
                            title: 'SLA enviado',
                            text: 'El email ha sido enviado exitosamente al cliente.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }).catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ha ocurrido un error al enviar el SLA',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    });
                }
            });
        }
    </script>
@endpush
