@extends('layouts/layoutMaster')

@section('title', isset($post) && $post ? __('app.Edit') : __('app.Create'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@php
    $isEdit = isset($post) && $post;
    $typeName = $isEdit ? $post->post_type : ($currentType?->name ?? 'post');
@endphp

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ $currentType?->label ?? __('app.Content') }}/</span>
            {{ $isEdit ? __('app.Edit') : __('app.Create') }}
        </h4>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ $listingUrl }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('app.Back') }}
        </a>
    </div>
</div>

<form id="post-form" action="{{ $isEdit ? route('cms.posts.update', $post->id) : route('cms.posts.store') }}" method="POST">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="post_type" value="{{ $typeName }}">

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="post_title">{{ __('app.Title') }}</label>
                        <input type="text" id="post_title" name="post_title" class="form-control @error('post_title') is-invalid @enderror"
                            value="{{ old('post_title', $isEdit ? $post->post_title : '') }}">
                        @error('post_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="post_name">{{ __('app.Slug') }}</label>
                        <input type="text" id="post_name" name="post_name" class="form-control @error('post_name') is-invalid @enderror"
                            value="{{ old('post_name', $isEdit ? $post->post_name : '') }}" pattern="[a-z0-9\-]*">
                        <div class="form-text">{{ __('app.Leave blank to generate from the title.') }}</div>
                        @error('post_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.Content') }}</label>
                        <div id="post-editor" style="min-height: 250px;">{!! old('post_content', $isEdit ? $post->post_content : '') !!}</div>
                        <textarea name="post_content" id="post_content" class="d-none">{{ old('post_content', $isEdit ? $post->post_content : '') }}</textarea>
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="post_excerpt">{{ __('app.Excerpt') }}</label>
                        <textarea id="post_excerpt" name="post_excerpt" rows="2" class="form-control">{{ old('post_excerpt', $isEdit ? $post->post_excerpt : '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <h5 class="card-header">{{ __('app.Publish') }}</h5>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="post_status">{{ __('app.Status') }}</label>
                        <select id="post_status" name="post_status" class="form-select">
                            @php $status = old('post_status', $isEdit ? $post->post_status : \App\Models\Post::STATUS_PUBLISH); @endphp
                            <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>{{ __('app.Draft') }}</option>
                            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>{{ __('app.Pending') }}</option>
                            <option value="publish" {{ $status === 'publish' ? 'selected' : '' }}>{{ __('app.Published') }}</option>
                            <option value="private" {{ $status === 'private' ? 'selected' : '' }}>{{ __('app.Private') }}</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="menu_order">{{ __('app.Order') }}</label>
                        <input type="number" id="menu_order" name="menu_order" class="form-control"
                            value="{{ old('menu_order', $isEdit ? $post->menu_order : 0) }}">
                    </div>

                    @if($currentType?->hierarchical)
                    <div class="mb-1">
                        <label class="form-label" for="post_parent">{{ __('app.Parent') }}</label>
                        <input type="number" id="post_parent" name="post_parent" class="form-control"
                            value="{{ old('post_parent', $isEdit ? $post->post_parent : 0) }}">
                    </div>
                    @endif
                </div>
            </div>

            @if($availableTerms->isNotEmpty())
            <div class="card mb-4">
                <h5 class="card-header">{{ __('app.Taxonomies') }}</h5>
                <div class="card-body">
                    <select name="terms[]" class="form-select select2" multiple data-placeholder="{{ __('app.Select') }}">
                        @foreach($availableTerms as $termTaxonomy)
                            <option value="{{ $termTaxonomy->id }}" {{ in_array($termTaxonomy->id, $selectedTermIds) ? 'selected' : '' }}>
                                {{ $termTaxonomy->term?->name }} ({{ $termTaxonomy->taxonomy }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('app.Save') }}</button>
            </div>
        </div>
    </div>
</form>
@endsection

@section('page-script')
<script>
$(function() {
    $('.select2').select2();

    var quill = new Quill('#post-editor', { theme: 'snow' });

    $('#post-form').on('submit', function() {
        document.querySelector('#post_content').value = quill.root.innerHTML;
    });
});
</script>
@endsection
