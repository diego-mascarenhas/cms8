@extends('layouts/layoutMaster')

@section('title', __('app.Content'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ $currentType?->label ?? __('app.Content') }}</h4>
        <p class="text-muted">{{ __('app.Manage your site content') }}</p>
    </div>
    @can('create', \App\Models\Post::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('cms.posts.create', ['post_type' => $currentType?->name]) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('app.New') }} {{ $currentType?->label_singular ?? __('app.Post') }}
        </a>
    </div>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end mb-4">
            <div class="col-md-4">
                <label class="form-label" for="filter_post_type">{{ __('app.Type') }}</label>
                <select id="filter_post_type" class="form-select select2">
                    @foreach($postTypes as $type)
                        <option value="{{ $type->name }}" {{ $currentType?->name === $type->name ? 'selected' : '' }}>
                            {{ $type->label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="datatable_search">{{ __('app.Search') }}</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-search"></i></span>
                    <input type="text" id="datatable_search" class="form-control" placeholder="{{ __('app.Search') }}">
                </div>
            </div>
        </div>

        {!! $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) !!}
    </div>
</div>
@endsection

@section('page-script')
<script>
$(function() {
    $('#filter_post_type').select2();
    let table = window.LaravelDataTables['posts-table'];

    $('#filter_post_type').on('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('post_type', this.value);
        window.location.href = url.toString();
    });

    setTimeout(function() {
        $('#posts-table_filter').remove();
    }, 100);

    $('#datatable_search').on('keyup', function() {
        table.search(this.value).draw();
    });
});
</script>
{!! $dataTable->scripts() !!}
@endsection
