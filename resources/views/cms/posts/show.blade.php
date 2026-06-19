@extends('layouts/layoutMaster')

@section('title', $post->post_title ?: __('app.Post'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ ucfirst($post->post_type) }}/</span>
            {{ $post->post_title ?: __('app.No title') }}
        </h4>
        <p class="text-muted">{{ $post->post_name }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $post)
        <a href="{{ route('cms.posts.edit', $post->id) }}" class="btn btn-primary">
            <i class="ti ti-edit me-1"></i>{{ __('app.Edit') }}
        </a>
        @endcan
        <a href="{{ $listingUrl }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('app.Back') }}
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="ql-snow"><div class="ql-editor p-0">{!! $post->post_content !!}</div></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4">
            <h5 class="card-header">{{ __('app.Details') }}</h5>
            <div class="card-body">
                <p class="mb-2"><strong>{{ __('app.Status') }}:</strong> {{ $post->post_status }}</p>
                <p class="mb-2"><strong>{{ __('app.Order') }}:</strong> {{ $post->menu_order }}</p>
                <p class="mb-2"><strong>{{ __('app.Author') }}:</strong> {{ $post->author?->name ?? '-' }}</p>
                <p class="mb-0"><strong>{{ __('app.Modified') }}:</strong> {{ optional($post->post_modified)->format('d-m-Y H:i') ?? '-' }}</p>
            </div>
        </div>

        @if($post->termTaxonomies->isNotEmpty())
        <div class="card mb-4">
            <h5 class="card-header">{{ __('app.Taxonomies') }}</h5>
            <div class="card-body">
                @foreach($post->termTaxonomies as $tt)
                    <span class="badge bg-label-primary me-1 mb-1">{{ $tt->term?->name }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
