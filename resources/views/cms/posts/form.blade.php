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
    $featuredId = $isEdit ? $post->getMeta('_thumbnail_id') : null;
    $featuredUrl = null;
    if ($featuredId) {
        $featuredAttachment = \App\Models\Post::find((int) $featuredId);
        $featuredUrl = $featuredAttachment?->getMeta('_humano_thumb_url') ?: $featuredAttachment?->guid;
    }
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

                    <div class="mb-3" id="permalink-panel">
                        <div class="d-flex flex-wrap align-items-center gap-1 small" id="permalink-view">
                            <span class="text-muted">{{ __('app.Permalink') }}:</span>
                            <span class="text-muted">/</span>
                            <span id="permalink-slug-text" class="fw-medium">{{ old('post_name', $isEdit ? $post->post_name : '') ?: '…' }}</span>
                            <button type="button" class="btn btn-sm btn-link p-0 ms-1" id="permalink-edit-btn">{{ __('app.Edit') }}</button>
                        </div>
                        <div class="d-none mt-2" id="permalink-edit">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">/</span>
                                <input type="text" id="post_name" name="post_name"
                                    class="form-control @error('post_name') is-invalid @enderror"
                                    value="{{ old('post_name', $isEdit ? $post->post_name : '') }}"
                                    pattern="[a-z0-9\-]*" autocomplete="off">
                                <button type="button" class="btn btn-primary" id="permalink-ok-btn">OK</button>
                            </div>
                            @error('post_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('app.Slug is generated automatically from the title.') }}</div>
                        </div>
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

            <div class="card mb-4">
                <h5 class="card-header">{{ __('app.Featured image') }}</h5>
                <div class="card-body">
                    <input type="hidden" name="meta[_thumbnail_id]" id="thumbnail_id" value="{{ $featuredId }}">
                    <div id="featured-preview" class="mb-2 {{ $featuredUrl ? '' : 'd-none' }}">
                        <img src="{{ $featuredUrl }}" alt="" class="img-fluid rounded border" id="featured-preview-img" style="max-height: 180px;">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-label-primary" id="btn-select-featured">
                            <i class="ti ti-photo me-1"></i>{{ __('app.Select image') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-label-secondary {{ $featuredUrl ? '' : 'd-none' }}" id="btn-remove-featured">
                            <i class="ti ti-x me-1"></i>{{ __('app.Remove') }}
                        </button>
                    </div>
                </div>
            </div>

            @if($supportsCategory)
            <div class="card mb-4">
                <h5 class="card-header">{{ __('app.Categories') }}</h5>
                <div class="card-body">
                    <div class="taxonomy-checklist mb-3" style="max-height: 220px; overflow-y: auto;">
                        @forelse($categories as $category)
                            @php
                                $depth = 0;
                                $parentId = (int) $category->parent;
                                while ($parentId > 0 && $depth < 5) {
                                    $parent = $categories->firstWhere('id', $parentId);
                                    if (! $parent) {
                                        break;
                                    }
                                    $depth++;
                                    $parentId = (int) $parent->parent;
                                }
                            @endphp
                            <div class="form-check mb-1" style="margin-left: {{ $depth * 1.25 }}rem;">
                                <input class="form-check-input" type="checkbox" name="category_terms[]"
                                    value="{{ $category->id }}" id="category-{{ $category->id }}"
                                    {{ in_array($category->id, old('category_terms', $selectedCategoryIds), true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="category-{{ $category->id }}">
                                    {{ $category->term?->name }}
                                </label>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">{{ __('app.No categories in this module') }}</p>
                        @endforelse
                    </div>

                    <div class="border-top pt-3">
                        <button class="btn btn-sm btn-link p-0 mb-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#new-category-panel" aria-expanded="false">
                            {{ __('app.New Category') }}
                        </button>
                        <div class="collapse" id="new-category-panel">
                            <label class="form-label small mb-1" for="new_category_name">{{ __('app.Name') }}</label>
                            <input type="text" id="new_category_name" name="new_category[name]"
                                class="form-control form-control-sm mb-2"
                                value="{{ old('new_category.name') }}">
                            <label class="form-label small mb-1" for="new_category_parent">{{ __('app.Parent Category') }}</label>
                            <select id="new_category_parent" name="new_category[parent]" class="form-select form-select-sm">
                                <option value="0">{{ __('app.None') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ (int) old('new_category.parent') === $category->id ? 'selected' : '' }}>
                                        {{ $category->term?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($supportsTags)
            <div class="card mb-4">
                <h5 class="card-header">{{ __('app.Tags') }}</h5>
                <div class="card-body">
                    <select name="tag_terms[]" class="form-select select2" multiple
                        data-placeholder="{{ __('app.Tags') }}">
                        @foreach($tags as $tag)
                            <option value="{{ $tag->id }}"
                                {{ in_array($tag->id, old('tag_terms', $selectedTagIds), true) ? 'selected' : '' }}>
                                {{ $tag->term?->name }}
                            </option>
                        @endforeach
                    </select>
                    <label class="form-label mt-3 mb-1 small text-muted" for="new_tags">
                        {{ __('app.Separate tags with commas') }}
                    </label>
                    <input type="text" id="new_tags" name="new_tags" class="form-control form-control-sm"
                        value="{{ old('new_tags') }}" placeholder="{{ __('app.Tags') }}">
                </div>
            </div>
            @endif

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('app.Save') }}</button>
            </div>
        </div>
    </div>
</form>

@include('cms.posts.partials.media-picker')
@endsection

@section('page-script')
<script>
$(function() {
    $('.select2').select2();

    function insertMediaImage() {
        window.openMediaPicker(function(media) {
            if (!media.is_image) { return; }
            const range = quill.getSelection(true);
            quill.insertEmbed(range ? range.index : quill.getLength(), 'image', media.url, 'user');
        });
    }

    var quill = new Quill('#post-editor', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline', 'link'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['image'],
                    ['clean']
                ],
                handlers: {
                    image: insertMediaImage
                }
            }
        }
    });

    // Permalink / slug (WordPress-style: auto from title until manually edited).
    const titleInput = document.getElementById('post_title');
    const slugInput = document.getElementById('post_name');
    const slugText = document.getElementById('permalink-slug-text');
    const permalinkView = document.getElementById('permalink-view');
    const permalinkEdit = document.getElementById('permalink-edit');
    const isNewPost = {{ $isEdit ? 'false' : 'true' }};
    let slugManual = !isNewPost && (slugInput.value || '').trim() !== '';

    function slugify(text) {
        return (text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function syncSlugPreview() {
        const slug = (slugInput.value || '').trim();
        slugText.textContent = slug || '…';
    }

    function applyAutoSlug() {
        if (slugManual) {
            return;
        }
        const slug = slugify(titleInput.value);
        slugInput.value = slug;
        syncSlugPreview();
    }

    $('#post-form').on('submit', function() {
        document.querySelector('#post_content').value = quill.root.innerHTML;
        if (!slugManual || !(slugInput.value || '').trim()) {
            slugInput.value = slugify(titleInput.value);
        }
        syncSlugPreview();
    });

    // Featured image selection.
    const thumbInput = document.getElementById('thumbnail_id');
    const preview = document.getElementById('featured-preview');
    const previewImg = document.getElementById('featured-preview-img');
    const removeBtn = document.getElementById('btn-remove-featured');

    document.getElementById('btn-select-featured')?.addEventListener('click', function() {
        window.openMediaPicker(function(media) {
            if (!media.is_image) { return; }
            thumbInput.value = media.id;
            previewImg.src = media.thumb || media.url;
            preview.classList.remove('d-none');
            removeBtn.classList.remove('d-none');
        });
    });

    removeBtn?.addEventListener('click', function() {
        thumbInput.value = '';
        preview.classList.add('d-none');
        removeBtn.classList.add('d-none');
    });

    titleInput?.addEventListener('input', applyAutoSlug);

    slugInput?.addEventListener('input', function() {
        slugManual = true;
        syncSlugPreview();
    });

    document.getElementById('permalink-edit-btn')?.addEventListener('click', function() {
        permalinkView.classList.add('d-none');
        permalinkEdit.classList.remove('d-none');
        slugInput.focus();
        slugInput.select();
    });

    document.getElementById('permalink-ok-btn')?.addEventListener('click', function() {
        slugInput.value = slugify(slugInput.value);
        slugManual = true;
        syncSlugPreview();
        permalinkEdit.classList.add('d-none');
        permalinkView.classList.remove('d-none');
    });

    if (isNewPost) {
        applyAutoSlug();
    } else {
        syncSlugPreview();
    }
});
</script>
@endsection
