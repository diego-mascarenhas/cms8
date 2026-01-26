@extends('layouts/layoutMaster')

@section('title', isset($content) ? __('app.Edit Content') : __('app.Create Content'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('app.Contents') }}/</span>
            @if(isset($content))
                {{ $content->getTranslatable('title') ?: __('app.Edit Content') }}
            @else
                {{ __('app.Create Content') }}
            @endif
        </h4>
        <p class="text-muted">
            @if(isset($content) && $content->sectionCategory)
                {{ __('app.Edit content in category') }}: <strong>{{ $content->sectionCategory->name }}</strong>
            @elseif(isset($selectedSection))
                {{ __('app.Create new content in category') }}: <strong>{{ $selectedSection->name }}</strong>
            @else
                {{ __('app.Manage website contents') }}
            @endif
        </p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('contents.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('app.Back') }}
        </a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ isset($content) ? __('app.Edit Content') : __('app.Create Content') }}</h5>
    <form class="card-body" action="{{ isset($content) ? route('contents.update', $content->id) : route('contents.store') }}" method="POST">
        @csrf
        @if(isset($content))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="section_category_id" class="form-label">{{ __('app.Category') }} (*)</label>
                <select id="section_category_id" name="section_category_id" class="form-select select2" required>
                    <option value="">{{ __('app.Select Category') }}</option>
                    @foreach($sectionCategories as $sectionCategory)
                        <option value="{{ $sectionCategory->id }}"
                            {{ old('section_category_id', $content->section_category_id ?? $selectedSection->id ?? '') == $sectionCategory->id ? 'selected' : '' }}>
                            {{ $sectionCategory->name }}
                        </option>
                    @endforeach
                </select>
                @error('section_category_id')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="status" class="form-label">{{ __('app.Status') }} (*)</label>
                <select id="status" name="status" class="form-select select2" required>
                    <option value="1" {{ old('status', $content->status ?? 3) == 1 ? 'selected' : '' }}>{{ __('app.Draft') }}</option>
                    <option value="2" {{ old('status', $content->status ?? 3) == 2 ? 'selected' : '' }}>{{ __('app.Pending') }}</option>
                    <option value="3" {{ old('status', $content->status ?? 3) == 3 ? 'selected' : '' }}>{{ __('app.Published') }}</option>
                    <option value="4" {{ old('status', $content->status ?? 3) == 4 ? 'selected' : '' }}>{{ __('app.Archived') }}</option>
                </select>
                @error('status')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-12">
                <x-input-general
                    id="title"
                    label="{{ __('app.Title') }}"
                    value="{{ old('title', isset($content) ? $content->getTranslatable('title') : '') }}"
                />
            </div>

            <div class="col-md-6">
                <x-input-general
                    id="subtitle"
                    label="{{ __('app.Subtitle') }}"
                    value="{{ old('subtitle', isset($content) ? $content->getTranslatable('subtitle') : '') }}"
                />
            </div>

            <div class="col-md-6">
                <x-input-general
                    id="url"
                    label="{{ __('app.URL') }}"
                    value="{{ old('url', isset($content) ? $content->getTranslatable('url') : '') }}"
                />
            </div>

            <div class="col-md-12">
                <label for="content" class="form-label">{{ __('app.Content') }}</label>
                <div id="content-editor" style="height: 200px;"></div>
                <input type="hidden" id="content" name="content" value="{{ old('content', isset($content) ? $content->getTranslatable('content') : '') }}">
                @error('content')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1"
                        {{ old('featured', isset($content) && $content->featured) ? 'checked' : '' }}>
                    <label class="form-check-label" for="featured">{{ __('app.Featured') }}</label>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" id="featured_slide" name="featured_slide" value="1"
                        {{ old('featured_slide', isset($content) && $content->featured_slide) ? 'checked' : '' }}>
                    <label class="form-check-label" for="featured_slide">{{ __('app.Featured Slide') }}</label>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" id="featured_modal" name="featured_modal" value="1"
                        {{ old('featured_modal', isset($content) && $content->featured_modal) ? 'checked' : '' }}>
                    <label class="form-check-label" for="featured_modal">{{ __('app.Featured Modal') }}</label>
                </div>
            </div>

            <div class="col-md-3">
                <label for="order" class="form-label">{{ __('app.Order') }}</label>
                <input type="number" class="form-control" id="order" name="order"
                    value="{{ old('order', $content->order ?? 0) }}" min="0">
                @error('order')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            @if(isset($fieldConfigs) && $fieldConfigs->count() > 0)
                <div class="col-12">
                    <hr>
                    <h6 class="mb-3">{{ __('app.Additional Fields') }}</h6>
                    @include('components.dynamic-content-fields', ['fieldConfigs' => $fieldConfigs, 'content' => $content ?? null])
                </div>
            @endif

            <div class="col-12">
                <hr>
                <h6 class="mb-3">{{ __('app.Multimedia') }}</h6>
                @include('components.content-multimedia-selector', ['selectedMultimedia' => $selectedMultimedia ?? []])
            </div>

            <div class="col-12">
                <hr>
                <h6 class="mb-3">{{ __('app.SEO') }}</h6>
                <div class="row g-3">
                    <div class="col-md-12">
                        <x-input-general
                            id="seo_title"
                            label="{{ __('app.SEO Title') }}"
                            value="{{ old('seo_title', isset($content) ? $content->getTranslatable('seo_title') : '') }}"
                        />
                    </div>
                    <div class="col-md-12">
                        <x-input-general
                            id="seo_keywords"
                            label="{{ __('app.SEO Keywords') }}"
                            value="{{ old('seo_keywords', isset($content) ? $content->getTranslatable('seo_keywords') : '') }}"
                        />
                    </div>
                    <div class="col-md-12">
                        <label for="seo_description" class="form-label">{{ __('app.SEO Description') }}</label>
                        <textarea class="form-control" id="seo_description" name="seo_description" rows="3">{{ old('seo_description', isset($content) ? $content->getTranslatable('seo_description') : '') }}</textarea>
                        @error('seo_description')
                            <div class="text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('app.Save') }}</button>
                <a href="{{ route('contents.index') }}" class="btn btn-label-secondary">{{ __('app.Cancel') }}</a>
            </div>
        </div>
    </form>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#section_category_id, #status').select2({
        templateResult: function(data) {
            // Solo mostrar el nombre de la categoría, sin el nombre del módulo
            if (!data.id) {
                return data.text;
            }
            return data.text;
        },
        templateSelection: function(data) {
            // Solo mostrar el nombre de la categoría en la selección
            return data.text;
        }
    });


    // Initialize Quill editor
    const contentEditor = new Quill('#content-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                ['link', 'image'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }]
            ]
        }
    });

    // Load existing content
    const contentValue = document.getElementById('content').value;
    if (contentValue) {
        contentEditor.root.innerHTML = contentValue;
    }

    // Update hidden input on form submit
    document.querySelector('form').addEventListener('submit', function() {
        document.getElementById('content').value = contentEditor.root.innerHTML;
    });
});
</script>
@endsection
