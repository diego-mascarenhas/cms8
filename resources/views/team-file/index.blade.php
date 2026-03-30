@extends('layouts/layoutMaster')

@section('title', __('Team files'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
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

@php
    $visibilitySelectOptions = [];
    foreach ($visibilityOptions as $v) {
        $visibilitySelectOptions[$v->value] = $v->label();
    }
@endphp

<div class="card mb-4">
    <div class="card-header border-bottom">
        <div class="d-flex flex-column flex-md-row gap-3">
            <div class="flex-grow-1">
                <x-module-categories-select
                    id="filter_team_file_category"
                    label=""
                    moduleKey="team_files"
                    :selected="''"
                    :allowEmpty="true"
                    :listingFilter="true"
                />
            </div>
            <div class="flex-grow-1">
                <x-input-select
                    id="filter_team_file_visibility"
                    :options="$visibilitySelectOptions"
                    :value="''"
                    :placeholder="__('Select visibility')"
                />
            </div>
        </div>
    </div>
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>
@endsection

@section('page-script')
{!! $dataTable->scripts() !!}
<script>
$(function () {
    $(document).on('change', '#filter_team_file_visibility, #filter_team_file_category', function () {
        var table = window.LaravelDataTables?.['team-files-table'];
        if (table) {
            table.ajax.reload();
        }
    });
});
</script>
@endsection
