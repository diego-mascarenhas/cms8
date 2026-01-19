@extends('layouts/layoutMaster')

@section('title', __('Processing Download'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4 class="mb-2">{{ __('Processing Download') }}</h4>
            <p class="mb-4">{{ __('Generating invoice files for Q:quarter :year...', ['quarter' => $quarter, 'year' => $year]) }}</p>
            <p class="text-muted">{{ __('This process may take a few moments. You will receive a notification when the download is ready.') }}</p>
            <a href="{{ route('accounting.index') }}" class="btn btn-primary mt-3">
                <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Accounting') }}
            </a>
        </div>
    </div>
</div>
@endsection

