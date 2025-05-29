@extends('layouts/layoutMaster')

@section('title', __('Style Books'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flag-icons/flag-icons.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
<script>
function deleteRecord(id, element) {
    if (confirm("{{ __('Are you sure you want to delete this style book?') }}")) {
        $.ajax({
            url: "{{ route('stylebook.destroy', ':id') }}".replace(':id', id),
            type: 'DELETE',
            data: {
                "_token": "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.success) {
                    $(element).closest('tr').fadeOut('slow', function() {
                        $(this).remove();
                    });
                }
            }
        });
    }
}
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Style Books') }}</h4>
        <p class="text-muted">{{ __('Manage your translation style books') }}</p>
    </div>
    @can('stylebook.create')
    <div class="mt-3 mt-md-0">
        <a href="{{ route('stylebook.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> {{ __('Add Style Book') }} </a>
    </div>
    @endcan
</div>

<div class="card">
    <div class="card-datatable table-responsive">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection 