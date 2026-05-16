@extends('layouts/layoutMaster')

@section('title', __('app.performance_insights_menu'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('app.performance_insights_menu') }}</h4>
            <p class="text-muted">{{ __('app.performance_insight_list_subtitle') }}</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="{{ route('performance-insights.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="insight_date">{{ __('app.performance_insight_filter_date') }}</label>
                    <input type="date" class="form-control" id="insight_date" name="insight_date"
                        value="{{ request('insight_date', now()->toDateString()) }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">{{ __('app.performance_insight_filter_apply') }}</button>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="{{ route('performance-insights.index') }}" class="btn btn-label-secondary btn-sm">
                        {{ __('app.performance_insight_filter_all_dates') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{ $dataTable->table() }}
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts() }}
@endpush
