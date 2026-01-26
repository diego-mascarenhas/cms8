@extends('layouts/layoutMaster')

@section('title', __('app.Content'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ $content->getTranslatable('title') ?: __('app.Content') }}</h4>
        <p class="text-muted">{{ __('app.Content details') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $content)
        <a href="{{ route('contents.edit', $content->id) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-edit me-1"></i>{{ __('app.Edit') }}
        </a>
        @endcan
        <a href="{{ route('contents.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('app.Back') }}
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('app.Details') }}</h5>
            </div>
            <div class="card-body">
                <h3>{{ $content->getTranslatable('title') }}</h3>
                @if($content->getTranslatable('subtitle'))
                    <p class="text-muted">{{ $content->getTranslatable('subtitle') }}</p>
                @endif

                <hr>

                <dl class="row">
                    <dt class="col-sm-3">{{ __('app.Section') }}</dt>
                    <dd class="col-sm-9">{{ $content->sectionCategory->name }}</dd>

                    <dt class="col-sm-3">{{ __('app.Category') }}</dt>
                    <dd class="col-sm-9">{{ $content->category ? $content->category->name : __('app.No category') }}</dd>

                    <dt class="col-sm-3">{{ __('app.Status') }}</dt>
                    <dd class="col-sm-9">
                        @php
                            $statusLabels = [
                                1 => __('app.Draft'),
                                2 => __('app.Pending'),
                                3 => __('app.Published'),
                                4 => __('app.Archived'),
                            ];
                            $statusClasses = [
                                1 => 'bg-label-secondary',
                                2 => 'bg-label-warning',
                                3 => 'bg-label-success',
                                4 => 'bg-label-info',
                            ];
                        @endphp
                        <span class="badge {{ $statusClasses[$content->status] ?? 'bg-label-secondary' }}">
                            {{ $statusLabels[$content->status] ?? __('app.Unknown') }}
                        </span>
                    </dd>
                </dl>

                @if($content->getTranslatable('content1'))
                    <hr>
                    <div class="content-body">
                        {!! $content->getTranslatable('content1') !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
@if(app()->environment('production'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Track content view in Google Analytics (only in production)
    if (typeof gtag !== 'undefined') {
        gtag('event', 'content_view', {
            'content_id': {{ $content->id }},
            'content_title': '{{ addslashes($content->getTranslatable('title') ?? '') }}',
            'content_category': '{{ addslashes($content->sectionCategory->name ?? '') }}',
            'content_status': '{{ $content->status }}',
            'event_category': 'Content',
            'event_label': 'View'
        });
    }
});
</script>
@endif
@endsection
