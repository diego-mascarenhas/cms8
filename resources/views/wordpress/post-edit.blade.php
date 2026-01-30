@extends('layouts/layoutMaster')

@section('title', __('Edit post'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
@endsection

@section('page-script')
    <script>
        function initPostEditors() {
            if (typeof Quill === 'undefined') return false;
            var excerptEl = document.querySelector('#excerpt-editor');
            var contentEl = document.querySelector('#content-editor');
            var excerptToolbar = document.querySelector('#excerpt-toolbar');
            var contentToolbar = document.querySelector('#content-toolbar');
            if (!excerptEl || !contentEl || !excerptToolbar || !contentToolbar) return false;

            var excerptEditor = null;
            var contentEditor = null;
            try {
                excerptEditor = new Quill(excerptEl, {
                    theme: 'snow',
                    modules: { toolbar: excerptToolbar },
                    placeholder: '{{ __('Brief summary for listings and search.') }}'
                });
                var excerptContent = document.querySelector('#excerpt').value;
                if (excerptContent && excerptContent.trim() !== '' && excerptContent.trim() !== '<p><br></p>') {
                    excerptEditor.root.innerHTML = excerptContent;
                }
            } catch (e) { console.warn('Quill excerpt init:', e); }

            setTimeout(function() {
                try {
                    contentEditor = new Quill(contentEl, {
                        theme: 'snow',
                        modules: { toolbar: contentToolbar },
                        placeholder: '{{ __('Post content.') }}'
                    });
                    var contentVal = document.querySelector('#content').value;
                    if (contentVal && contentVal.trim() !== '' && contentVal.trim() !== '<p><br></p>') {
                        contentEditor.root.innerHTML = contentVal;
                    }
                } catch (e) { console.warn('Quill content init:', e); }

                var form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        if (excerptEditor) document.querySelector('#excerpt').value = excerptEditor.root.innerHTML;
                        if (contentEditor) document.querySelector('#content').value = contentEditor.root.innerHTML;
                    }, { once: true });
                }
            }, 50);
            return true;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (!initPostEditors()) window.addEventListener('load', initPostEditors);
            });
        } else {
            if (!initPostEditors()) window.addEventListener('load', initPostEditors);
        }
    </script>
@endsection

@section('content')
    @php
        $postTitle = old('title');
        if ($postTitle === null) {
            $postTitle = is_array($post['title'] ?? null) ? ($post['title']['raw'] ?? strip_tags($post['title']['rendered'] ?? '')) : ($post['title'] ?? '');
        }
        $postContent = old('content');
        if ($postContent === null) {
            $contentObj = $post['content'] ?? [];
            $postContent = is_array($contentObj) ? ($contentObj['raw'] ?? $contentObj['rendered'] ?? '') : (string) $contentObj;
        }
        $postExcerpt = old('excerpt');
        if ($postExcerpt === null) {
            $excerptObj = $post['excerpt'] ?? [];
            $postExcerpt = is_array($excerptObj) ? ($excerptObj['raw'] ?? $excerptObj['rendered'] ?? '') : (string) $excerptObj;
        }
        $postStatus = old('status', $post['status'] ?? 'publish');
    @endphp
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Posts') }}/</span> {{ __('Edit') }}</h4>
            <p class="text-muted">{{ __('Content from your WordPress site') }}</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="{{ $storeUrl }}/wp-admin/post.php?post={{ $post['id'] }}&action=edit" target="_blank" rel="noopener noreferrer" class="btn btn-label-secondary">{{ __('Edit in WordPress') }}</a>
            <a href="{{ route('wordpress.posts') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">{{ __('Edit post') }}</h5>
        <form class="card-body" action="{{ route('wordpress.posts.update', $post['id']) }}" method="POST">
            @csrf
            @method('PUT')

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="title" class="form-label">{{ __('Title') }} (*)</label>
                    <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ $postTitle }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="publish" {{ $postStatus === 'publish' ? 'selected' : '' }}>{{ __('Published') }}</option>
                        <option value="draft" {{ $postStatus === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                        <option value="pending" {{ $postStatus === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                        <option value="private" {{ $postStatus === 'private' ? 'selected' : '' }}>{{ __('Private') }}</option>
                        <option value="future" {{ $postStatus === 'future' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label d-block mb-2" for="excerpt">{{ __('Excerpt') }}</label>
                    <div id="excerpt-toolbar" class="border rounded-top">
                        <span class="ql-formats">
                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-link"></button>
                        </span>
                    </div>
                    <div id="excerpt-editor" class="border border-top-0 rounded-bottom" style="height: 100px; background: white;"></div>
                    <input type="hidden" id="excerpt" name="excerpt" value="{{ $postExcerpt }}">
                    @error('excerpt')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mt-4 pt-3 border-top">
                    <label class="form-label d-block mb-2" for="content">{{ __('Content') }}</label>
                    <div id="content-toolbar" class="border rounded-top">
                        <span class="ql-formats">
                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-underline"></button>
                            <button class="ql-header" value="1"></button>
                            <button class="ql-header" value="2"></button>
                            <button class="ql-list" value="ordered"></button>
                            <button class="ql-list" value="bullet"></button>
                            <button class="ql-link"></button>
                            <button class="ql-image"></button>
                        </span>
                    </div>
                    <div id="content-editor" class="border border-top-0 rounded-bottom bg-white" style="height: 280px;"></div>
                    <input type="hidden" id="content" name="content" value="{{ $postContent }}">
                    @error('content')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
                <a href="{{ route('wordpress.posts') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
