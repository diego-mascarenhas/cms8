@extends('layouts/layoutMaster')

@section('title', __('Opportunities'))

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
</style>

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Opportunities') }}</h4>
        <p class="text-muted">{{ __('CRM opportunities') }}</p>
    </div>
    @can('create', App\Models\Opportunity::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('opportunity.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> {{ __('Add opportunity') }} </a>
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

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
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
                fetch("{{ route('opportunity.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
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

{{-- vendor scripts --}}
@section('vendor-script')
<script src="{{asset('vendors/data-tables/js/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
<script src="{{asset('vendors/fullcalendar/lib/moment.min.js')}}"></script>
<script src="{{asset('js/moment/' . app()->getLocale() . '.js')}}"></script>
@endsection