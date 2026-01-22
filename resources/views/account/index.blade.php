@extends('layouts/layoutMaster')

@section('title', __('app.list60'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
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
            <h4 class="mb-1 mt-3">Cuentas</h4>
            <p class="text-muted">Gestión de cuentas</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            <a href="{{ route('account.products.index') }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-package me-1"></i>Productos
            </a>
        </div>
    </div>

    <div class="card">
        <!-- <div class="card-header border-bottom">
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('contact.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                        <i class="ti ti-plus me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Añadir cliente</span>
                    </a>
                </div>
            </div>
        </div> -->
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

@push('scripts')
    <script>
        function deleteRecord(id, element) {
            Swal.fire({
                title: '¿Estás seguro de eliminar este contacto de la Lista de 60?',
                icon: 'warning',
                showCloseButton: true,
                showCancelButton: false,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('list60.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
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

        function copyAutologinLink(url, element) {
            try {
                navigator.clipboard.writeText(url).then(function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Link copiado',
                        text: 'El link de autologueo ha sido copiado al portapapeles',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }).catch(function(err) {
                    // Fallback for older browsers
                    const textarea = document.createElement('textarea');
                    textarea.value = url;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Link copiado',
                        text: 'El link de autologueo ha sido copiado al portapapeles',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            } catch (err) {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = url;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Link copiado',
                    text: 'El link de autologueo ha sido copiado al portapapeles',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }
    </script>
@endpush
