@extends('layouts/layoutMaster')

@section('title', __('Paid Ads'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Paid Ads') }}</h4>
        <p class="text-muted">{{ __('Paid advertising campaigns across Google, Meta, LinkedIn, TikTok and X') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
        <a href="{{ route('paid-ads.dashboard') }}" class="btn btn-label-secondary"><i class="ti ti-chart-bar me-1"></i>{{ __('Dashboard') }}</a>
        <a href="{{ route('paid-ads.connections') }}" class="btn btn-label-secondary"><i class="ti ti-plug me-1"></i>{{ __('Connections') }}</a>
        <a href="{{ route('paid-ads.audiences.index') }}" class="btn btn-label-secondary"><i class="ti ti-users me-1"></i>{{ __('Audiences') }}</a>
        @can('create', App\Models\PaidAdCampaign::class)
        <a href="{{ route('paid-ads.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ __('Add campaign') }}</a>
        @endcan
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
<div class="alert alert-warning">{{ session('warning') }}</div>
@endif

<div class="card">
    <div class="card-body">
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>

<style>.fade-out { opacity: 0; transition: opacity 0.5s ease-out; }</style>

<script>
    function deleteRecord(id, element) {
        Swal.fire({
            title: '{{ __("Are you sure you want to delete this record?") }}',
            text: '{{ __("This action cannot be undone") }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            confirmButtonText: '{{ __("Yes, delete") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("{{ route('paid-ads.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                }).then(response => {
                    if (!response.ok) { throw new Error('Network response was not ok.'); }
                    return response.json();
                }).then(data => {
                    const row = element.closest('tr');
                    if (row) { row.classList.add('fade-out'); row.addEventListener('transitionend', () => row.remove()); }
                }).catch(error => {
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
