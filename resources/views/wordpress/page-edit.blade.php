@extends('layouts/layoutMaster')

@section('title', __('Edit page'))

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
        function initPageEditor() {
            if (typeof Quill === 'undefined') return false;
            var contentEl = document.querySelector('#content-editor');
            var contentToolbar = document.querySelector('#content-toolbar');
            if (!contentEl || !contentToolbar) return false;
            try {
                var contentEditor = new Quill(contentEl, {
                    theme: 'snow',
                    modules: { toolbar: contentToolbar },
                    placeholder: '{{ __('Page content.') }}'
                });
                var contentVal = document.querySelector('#content').value;
                if (contentVal && contentVal.trim() !== '' && contentVal.trim() !== '<p><br></p>') {
                    contentEditor.root.innerHTML = contentVal;
                }
                var form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        document.querySelector('#content').value = contentEditor.root.innerHTML;
                    }, { once: true });
                }
            } catch (e) { console.warn('Quill content init:', e); }
            return true;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (!initPageEditor()) window.addEventListener('load', initPageEditor);
            });
        } else {
            if (!initPageEditor()) window.addEventListener('load', initPageEditor);
        }
    </script>
@endsection

@section('content')
    @php
        $pageTitle = old('title');
        if ($pageTitle === null) {
            $pageTitle = is_array($page['title'] ?? null) ? ($page['title']['raw'] ?? strip_tags($page['title']['rendered'] ?? '')) : ($page['title'] ?? '');
        }
        $pageContent = old('content');
        if ($pageContent === null) {
            $contentObj = $page['content'] ?? [];
            $pageContent = is_array($contentObj) ? ($contentObj['raw'] ?? $contentObj['rendered'] ?? '') : (string) $contentObj;
        }
        $pageStatus = old('status', $page['status'] ?? 'publish');
    @endphp
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Pages') }}/</span> {{ __('Edit') }}</h4>
            <p class="text-muted">{{ __('Content from your WordPress site') }}</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="{{ $storeUrl }}/wp-admin/post.php?post={{ $page['id'] }}&action=edit" target="_blank" rel="noopener noreferrer" class="btn btn-label-secondary">{{ __('Edit in WordPress') }}</a>
            <a href="{{ route('wordpress.pages') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">{{ __('Edit page') }}</h5>
        <form class="card-body" action="{{ route('wordpress.pages.update', $page['id']) }}" method="POST">
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
                    <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ $pageTitle }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="publish" {{ $pageStatus === 'publish' ? 'selected' : '' }}>{{ __('Published') }}</option>
                        <option value="draft" {{ $pageStatus === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                        <option value="pending" {{ $pageStatus === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                        <option value="private" {{ $pageStatus === 'private' ? 'selected' : '' }}>{{ __('Private') }}</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
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
                    <input type="hidden" id="content" name="content" value="{{ $pageContent }}">
                    @error('content')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
                <a href="{{ route('wordpress.pages') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
