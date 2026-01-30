@extends('layouts/layoutMaster')

@section('title', __('Pages'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Pages') }}</h4>
            <p class="text-muted">{{ __('Content from your WordPress site') }}</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ $storeUrl }}/wp-admin/edit.php?post_type=page" target="_blank" rel="noopener noreferrer" class="btn btn-label-secondary">{{ __('Open in WordPress') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-0">{{ __('List of pages will be available here once the WordPress integration is fully implemented. For now you can manage pages in') }} <a href="{{ $storeUrl }}/wp-admin/edit.php?post_type=page" target="_blank" rel="noopener noreferrer">{{ __('WordPress') }}</a>.</p>
        </div>
    </div>
@endsection
