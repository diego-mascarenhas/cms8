@extends('layouts/layoutMaster')

@section('title', 'Detalle de Tarifa')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">Tarifas /</span> Detalle
</h4>

<div class="row">
    <div class="col-xl-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Detalle de Tarifa</h5>
                <div>
                    <a href="{{ route('fare.edit', $fare->id) }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-edit me-1"></i> Editar
                    </a>
                    <button type="button" class="btn btn-danger btn-sm delete-record" 
                           data-id="{{ $fare->id }}" 
                           data-route="{{ route('fare.destroy', $fare->id) }}">
                        <i class="ti ti-trash me-1"></i> Eliminar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-semibold">Nombre</h6>
                        <p>{{ $fare->name }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold">Unidades</h6>
                        <p>
                            @if($fare->units->isNotEmpty())
                                @foreach($fare->units as $unit)
                                    <span class="badge bg-label-primary">{{ $unit->type }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-semibold">Tipo</h6>
                        <p>{{ $fare->type ? $fare->type->name : 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold">Fecha de Creación</h6>
                        <p>{{ $fare->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 text-end">
                        <a href="{{ route('fare.index') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-arrow-left me-1"></i> Volver al Listado
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(document).on('click', '.delete-record', function() {
        const id = $(this).data('id');
        const route = $(this).data('route');
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function(result) {
            if (result.value) {
                $.ajax({
                    url: route,
                    type: 'DELETE',
                    data: {
                        "_token": $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminado!',
                                text: 'La tarifa ha sido eliminada.',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            }).then(function() {
                                window.location.href = "{{ route('fare.index') }}";
                            });
                        }
                    },
                    error: function(error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                                                            text: 'Ocurrió un error al eliminar la tarifa.',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    }
                });
            }
        });
    });
</script>
@endsection 