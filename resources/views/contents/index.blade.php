@extends('layouts/layoutMaster')

@section('title', __('app.Contents'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
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
        <h4 class="mb-1 mt-3">{{ __('app.Contents') }}</h4>
        <p class="text-muted">{{ __('app.Manage website contents') }}</p>
    </div>
    @can('create', \App\Models\Content::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('contents.create', ['section_id' => request('section_id')]) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('app.New Content') }}
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
            <div class="col-md-3">
                <label class="form-label" for="filter_section">{{ __('app.Category') }}</label>
                <select id="filter_section" class="form-select select2" data-placeholder="{{ __('app.All') }}">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($sectionCategories as $sectionCategory)
                        <option value="{{ $sectionCategory->id }}" {{ request('section_id') == $sectionCategory->id ? 'selected' : '' }}>
                            {{ $sectionCategory->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_status">{{ __('app.Status') }}</label>
                <select id="filter_status" class="form-select select2" data-placeholder="{{ __('app.All') }}">
                    <option value="">{{ __('app.All') }}</option>
                    <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>{{ __('app.Draft') }}</option>
                    <option value="2" {{ request('status') == '2' ? 'selected' : '' }}>{{ __('app.Pending') }}</option>
                    <option value="3" {{ request('status') == '3' ? 'selected' : '' }}>{{ __('app.Published') }}</option>
                    <option value="4" {{ request('status') == '4' ? 'selected' : '' }}>{{ __('app.Archived') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_featured">{{ __('app.Featured') }}</label>
                <select id="filter_featured" class="form-select select2" data-placeholder="{{ __('app.All') }}">
                    <option value="">{{ __('app.All') }}</option>
                    <option value="1" {{ request('featured') == '1' ? 'selected' : '' }}>{{ __('app.Yes') }}</option>
                    <option value="0" {{ request('featured') == '0' ? 'selected' : '' }}>{{ __('app.No') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="datatable_search">{{ __('app.Search') }}</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-search"></i></span>
                    <input type="text" id="datatable_search" class="form-control" placeholder="{{ __('app.Search') }}" aria-label="{{ __('app.Search') }}">
                </div>
            </div>
        </div>

        {!! $dataTable->table() !!}
    </div>
</div>
@endsection

@section('page-script')
<script>
$(function() {
    $('#filter_status, #filter_featured').select2();
    
    // Configurar Select2 para el filtro de categoría sin mostrar el nombre del módulo
    $('#filter_section').select2({
        templateResult: function(data) {
            // Solo mostrar el nombre de la categoría, sin el nombre del módulo
            if (!data.id) {
                return data.text;
            }
            return data.text;
        },
        templateSelection: function(data) {
            // Solo mostrar el nombre de la categoría en la selección
            return data.text;
        }
    });

    let table = window.LaravelDataTables['content-table'];

    $('#filter_section').on('change', function() {
        table.column(2).search(this.value).draw();
    });

    $('#filter_status').on('change', function() {
        table.column(3).search(this.value).draw();
    });

    $('#filter_featured').on('change', function() {
        table.column(4).search(this.value).draw();
    });
    
    // Eliminar completamente el campo de búsqueda por defecto de DataTables
    setTimeout(function() {
        $('#content-table_filter').remove();
    }, 100);
    
    // Conectar el campo de búsqueda personalizado con DataTables
    $('#datatable_search').on('keyup', function() {
        table.search(this.value).draw();
    });
});
</script>
{!! $dataTable->scripts() !!}
@endsection
