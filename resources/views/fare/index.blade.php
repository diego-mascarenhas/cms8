@extends('layouts/layoutMaster')

@section('title', 'Tarifas')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <span class="me-2">Mostrar</span>
                <select id="records-per-page" class="form-select form-select-sm" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <a href="{{ route('fare.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Crear tarifa
            </a>
            <div class="ms-auto">
                <input type="search" id="fare-search" class="form-control" placeholder="Buscar" aria-label="Buscar">
            </div>
        </div>

        <div class="table-responsive">
            <table id="fares-table" class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select-all">
                            </div>
                        </th>
                        <th>TARIFA</th>
                        <th>UNIDADES</th>
                        <th>TIPO</th>
                        <th>GLOSARIO</th>
                        <th class="text-end">ACCIÓN</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fares as $fare)
                    <tr>
                        <td>
                            <div class="form-check">
                                <input class="form-check-input fare-checkbox" type="checkbox" value="{{ $fare->id }}">
                            </div>
                        </td>
                        <td>{{ $fare->name }}</td>
                        <td>
                            @if($fare->units->isNotEmpty())
                                @foreach($fare->units as $unit)
                                    <span class="badge bg-label-primary">{{ $unit->type }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>{{ $fare->type ? $fare->type->name : 'N/A' }}</td>
                        <td>{{ $fare->glosary_id ? 'Texto explicando de qué trata este tipo de servicio / tarifa' : 'N/A' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex">
                                <button type="button" class="btn btn-icon btn-sm btn-text-secondary rounded-pill delete-record"
                                    data-id="{{ $fare->id }}" data-route="{{ route('fare.destroy', $fare->id) }}">
                                    <i class="ti ti-trash"></i>
                                </button>
                                <a href="{{ route('fare.edit', $fare->id) }}" class="btn btn-icon btn-sm btn-text-secondary rounded-pill">
                                    <i class="ti ti-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-icon btn-sm btn-text-secondary rounded-pill">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                Mostrando 1 a {{ min(count($fares), 50) }} de {{ count($fares) }} tarifas
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-end">
                    <li class="page-item">
                        <a class="page-link" href="#" aria-label="First">
                            <i class="ti ti-chevrons-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#" aria-label="Previous">
                            <i class="ti ti-chevron-left"></i>
                        </a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">4</a></li>
                    <li class="page-item"><a class="page-link" href="#">5</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#" aria-label="Next">
                            <i class="ti ti-chevron-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#" aria-label="Last">
                            <i class="ti ti-chevrons-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(document).ready(function() {
        // Inicializar datatable con opciones personalizadas
        const table = $('#fares-table').DataTable({
            dom: 't',
            ordering: true,
            paging: false,
            language: {
                url: '/js/datatables/{{ session()->get('locale', app()->getLocale()) }}.json'
            }
        });

        // Buscar en la tabla
        $('#fare-search').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Seleccionar/deseleccionar todos
        $('#select-all').on('change', function() {
            $('.fare-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Eliminar registro
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
                                // Recargar la página
                                window.location.reload();
                            }
                        },
                        error: function(error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Ocurrió un error al eliminar el tipo de tarifa.',
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

        // Cambiar número de registros mostrados
        $('#records-per-page').on('change', function() {
            table.page.len($(this).val()).draw();
        });
    });
</script>
@endsection 