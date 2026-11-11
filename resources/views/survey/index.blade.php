@extends('layouts/layoutMaster')

@section('title', __('Encuestas'))

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
        <h4 class="mb-1 mt-3">{{ __('Encuestas') }}</h4>
        <p class="text-muted">{{ __('Preguntas y respuestas ya recogidas') }}</p>
    </div>
    @can('create', App\Models\Survey::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('survey.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ __('Nueva encuesta') }}</a>
    </div>
    @endcan
</div>

<div class="card">
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>
@endsection

@section('page-script')
{!! $dataTable->scripts() !!}
@endsection
