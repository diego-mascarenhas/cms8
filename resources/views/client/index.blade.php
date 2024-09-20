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
    
    <script>
    $(document).ready(function() {
        $(document).on('click', '.edit-sentiment', function() {
            var id = $(this).data('id');
            var url = "{{ route('client.update-sentiment', ':id') }}";
            url = url.replace(':id', id);
            $('#updateSentimentForm').attr('action', url);
            $('#updateSentimentModal').modal('show');
        });
    });

    $(function() {
        let table = $('.datatable').DataTable();
        
        $('#EmotionalState').on('change', function(){
            let selectedValue = $(this).val();
            table.column('.select-filter').search(selectedValue).draw();
        });
    });

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
                fetch("{{ route('client.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
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
                            <div class="toast-client">${data.success}</div>
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

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Contactos</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalContacts }}</h3>
                                <p class="text-success mb-0">(+{{ $contactsPercentage }}%)</p>
                            </div>
                            <p class="mb-0">Total Contactos</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-user ti-sm"></i>
                            </span>
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
                            <span>Clientes</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalClients }}</h3>
                                <p class="text-success mb-0">(+{{ $clientsPercentage }}%)</p>
                            </div>
                            <p class="mb-0">Total Clientes</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-danger">
                                <i class="ti ti-user-plus ti-sm"></i>
                            </span>
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
                            <span>En seguimiento</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalFollowUps }}</h3>
                                <p class="text-danger mb-0">({{ $followUpsPercentage }}%)</p>
                            </div>
                            <p class="mb-0">Total en seguimiento</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-user-check ti-sm"></i>
                            </span>
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
                            <span>Pasados</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalPast }}</h3>
                                <p class="text-success mb-0">(+{{ $pastPercentage }}%)</p>
                            </div>
                            <p class="mb-0">Total pasados</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ti ti-user-exclamation ti-sm"></i>
                            </span>
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
                    <a href="{{ route('client.create') }}" class="btn btn-primary btn-sm waves-effect waves-light">
                        <i class="ti ti-plus me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Añadir cliente</span>
                    </a>
                    <button class="btn btn-outline-secondary btn-sm waves-effect">
                        <i class="ti ti-file-import me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Importar</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm waves-effect">
                        <i class="ti ti-file-export me-sm-1"></i>
                        <span class="d-none d-sm-inline-block">Exportar</span>
                    </button>
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row gap-3">
                <div class="flex-grow-1">
                    <x-input-select-array 
                        id="EmotionalState" 
                        :options="$emotionalStates" 
                        :value="''"
                        placeholder="Seleccione un estado emocional"
                    />
                </div>
                <div class="flex-grow-1">
                    <select id="ContractedService" class="form-select text-capitalize">
                        <option value=""> Selector de servicio contratado </option>
                        <!-- Añade aquí las opciones de servicio contratado -->
                    </select>
                </div>
                <div class="flex-grow-1">
                    <select id="ContactType" class="form-select text-capitalize">
                        <option value=""> Selector de tipo de contacto </option>
                        <!-- Añade aquí las opciones de tipo de contacto -->
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            {{ $dataTable->table() }}
        </div>
    </div>

    <!-- Modal Sentiment -->
    <div class="modal fade" id="updateSentimentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Sentiment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateSentimentForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="sentiment_id" class="form-label">Sentiment</label>
                            <select class="form-select" id="sentiment_id" name="sentiment_id" required>
                                <option value="" selected disabled>Seleccione el estado emocional</option>
                                @foreach(App\Models\EnterpriseSentiment::all() as $sentiment)
                                    <option value="{{ $sentiment->id }}">{{ $sentiment->name }} {{ $sentiment->emoji }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Sentiment</button>
                    </div>
                </form>
            </div>
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