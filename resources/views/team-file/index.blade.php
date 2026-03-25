@extends('layouts/layoutMaster')

@section('title', __('Team files'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Team files') }}</h4>
        <p class="text-muted">{{ __('Company documents, brand assets, and files shared per visibility.') }}</p>
    </div>
    @can('create', \App\Models\TeamFile::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('team-file.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> {{ __('Add file') }} </a>
    </div>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" for="filter_team_file_visibility">{{ __('Visibility') }}</label>
                <select id="filter_team_file_visibility" class="form-select select2" data-placeholder="{{ __('All') }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach($visibilityOptions as $visibility)
                        <option value="{{ $visibility->value }}">{{ $visibility->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>
@endsection

@section('page-script')
{!! $dataTable->scripts() !!}
<script>
$(function () {
    if ($.fn.select2) {
        $('#filter_team_file_visibility').select2({ width: '100%', allowClear: true });
    }
    $('#filter_team_file_visibility').on('change', function () {
        var table = window.LaravelDataTables?.['team-files-table'];
        if (table) {
            table.ajax.reload();
        }
    });
});
</script>
@endsection
