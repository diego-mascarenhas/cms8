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
    <form class="card-body" action="{{ isset($content) ? route('contents.update', $content->id) : route('contents.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if(isset($content))
            @method('PUT')
        @endif

        <div class="row g-2">
            <div class="col-md-6">
                <label for="section_category_id" class="form-label">{{ __('app.Category') }} (*)</label>
                <select id="section_category_id" name="section_category_id" class="form-select select2" required>
                    <option value="">{{ __('app.Select Category') }}</option>
                    @foreach($sectionCategories as $sectionCategory)
                        @php
                            $depth = (int) ($sectionCategory->depth_level ?? 0);
                            $indent = $depth > 0 ? str_repeat('— ', $depth) : '';
                        @endphp
                        <option value="{{ $sectionCategory->id }}"
                            {{ old('section_category_id', $content->section_category_id ?? $selectedSection->id ?? '') == $sectionCategory->id ? 'selected' : '' }}>
                            {{ $indent . $sectionCategory->name }}
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

            @php
                $cfv = $contentFormVisibility ?? \App\Support\ContentsSectionCategoryData::defaultContentFormVisibility();
                $showLocaleTabsBlock = ($cfv['show_title'] ?? true) || ($cfv['show_subtitle'] ?? true) || ($cfv['show_url'] ?? true) || ($cfv['show_main_content'] ?? true) || ($cfv['show_seo'] ?? true);
            @endphp

            {{-- Single locale tabs: content + SEO (no duplicate language strip) --}}
            @if($showLocaleTabsBlock)
            <div class="col-12">
                <ul class="nav nav-tabs mb-0" role="tablist">
                    @foreach($availableLocales ?? ['es' => 'Español'] as $localeCode => $localeName)
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $loop->first ? 'active' : '' }}" 
                                id="content-tab-{{ $localeCode }}" 
                                data-bs-toggle="tab" 
                                data-bs-target="#content-pane-{{ $localeCode }}" 
                                role="tab" 
                                aria-controls="content-pane-{{ $localeCode }}" 
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                {{ $localeName }}
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content mt-0">
                    @foreach($availableLocales ?? ['es' => 'Español'] as $localeCode => $localeName)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" 
                            id="content-pane-{{ $localeCode }}" 
                            role="tabpanel" 
                            aria-labelledby="content-tab-{{ $localeCode }}">
                            <div class="row g-2 mt-0">
                                @if($cfv['show_title'] ?? true)
                                <div class="col-md-12 mb-1">
                                    <label class="form-label" for="title_{{ $localeCode }}">{{ __('app.Title') }}</label>
                                    <input
                                        type="text"
                                        name="title_{{ $localeCode }}"
                                        id="title_{{ $localeCode }}"
                                        class="form-control{{ $errors->has('title_'.$localeCode) ? ' is-invalid' : '' }}"
                                        value="{{ old('title_'.$localeCode, isset($content) ? ($content->getTranslatable('title', $localeCode) ?? '') : '') }}"
                                        autocomplete="off"
                                    />
                                    @error("title_{$localeCode}")
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <p class="form-text small text-muted mt-2 mb-0">{{ __('app.Content title separate from body hint') }}</p>
                                </div>
                                @endif
                                @if($cfv['show_main_content'] ?? true)
                                <div class="col-md-12">
                                    <label for="content_{{ $localeCode }}" class="form-label">{{ __('app.Main content') }}</label>
                                    <p class="form-text small text-muted mb-1">{{ __('app.Main content hint') }}</p>
                                    <div id="content-editor-{{ $localeCode }}" style="min-height: 400px;"></div>
                                    <input type="hidden" id="content_{{ $localeCode }}" name="content_{{ $localeCode }}" 
                                        value="{{ old("content_{$localeCode}", isset($content) ? ($content->getTranslatable('content', $localeCode) ?? '') : '') }}">
                                    @error("content_{$localeCode}")
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                @if($cfv['show_subtitle'] ?? true)
                                <div class="col-md-6">
                                    <x-input-general
                                        id="subtitle_{{ $localeCode }}"
                                        name="subtitle_{{ $localeCode }}"
                                        label="{{ __('app.Subtitle') }}"
                                        value="{{ old("subtitle_{$localeCode}", isset($content) ? ($content->getTranslatable('subtitle', $localeCode) ?? '') : '') }}"
                                    />
                                </div>
                                @endif
                                @if($cfv['show_url'] ?? true)
                                <div class="col-md-6">
                                    <x-input-general
                                        id="url_{{ $localeCode }}"
                                        name="url_{{ $localeCode }}"
                                        label="{{ __('app.URL') }}"
                                        value="{{ old("url_{$localeCode}", isset($content) ? ($content->getTranslatable('url', $localeCode) ?? '') : '') }}"
                                    />
                                </div>
                                @endif
                                @if($cfv['show_seo'] ?? true)
                                <div class="col-12">
                                    <hr class="my-2">
                                    <h6 class="mb-2">{{ __('app.SEO') }}</h6>
                                    <div class="row g-2">
                                        <div class="col-md-12">
                                            <x-input-general
                                                id="seo_title_{{ $localeCode }}"
                                                name="seo_title_{{ $localeCode }}"
                                                label="{{ __('app.SEO Title') }}"
                                                value="{{ old("seo_title_{$localeCode}", isset($content) ? ($content->getTranslatable('seo_title', $localeCode) ?? '') : '') }}"
                                            />
                                        </div>
                                        <div class="col-md-12">
                                            <x-input-general
                                                id="seo_keywords_{{ $localeCode }}"
                                                name="seo_keywords_{{ $localeCode }}"
                                                label="{{ __('app.SEO Keywords') }}"
                                                value="{{ old("seo_keywords_{$localeCode}", isset($content) ? ($content->getTranslatable('seo_keywords', $localeCode) ?? '') : '') }}"
                                            />
                                        </div>
                                        <div class="col-md-12">
                                            <label for="seo_description_{{ $localeCode }}" class="form-label">{{ __('app.SEO Description') }}</label>
                                            <textarea class="form-control" id="seo_description_{{ $localeCode }}" name="seo_description_{{ $localeCode }}" rows="3">{{ old("seo_description_{$localeCode}", isset($content) ? ($content->getTranslatable('seo_description', $localeCode) ?? '') : '') }}</textarea>
                                            @error("seo_description_{$localeCode}")
                                                <div class="text-danger mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($cfv['show_featured'] ?? true)
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
            @endif

            <div class="col-md-3">
                <label for="order" class="form-label">{{ __('app.Order') }}</label>
                <input type="number" class="form-control" id="order" name="order"
                    value="{{ old('order', $content->order ?? 0) }}" min="0">
                @error('order')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            @php
                $coverData = isset($content) && is_array($content->data ?? null) ? ($content->data['cover'] ?? null) : null;
                $coverUrl = is_array($coverData) ? ($coverData['url'] ?? null) : null;
                $coverWidth = is_array($coverData) ? ($coverData['width'] ?? null) : null;
                $coverHeight = is_array($coverData) ? ($coverData['height'] ?? null) : null;
                $resolvedSectionId = (int) old('section_category_id', $content->section_category_id ?? $selectedSection->id ?? 0);
            @endphp
            <div class="col-md-6">
                <label for="cover_image" class="form-label">Cover image</label>
                <input type="file" class="form-control @error('cover_image') is-invalid @enderror" id="cover_image" name="cover_image" accept="image/*">
                @error('cover_image')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text" id="cover-settings-hint">Select a section to see cover size and crop rules.</div>
                @if($coverUrl)
                    <div class="mt-2">
                        <img src="{{ $coverUrl }}" alt="Current cover" class="img-thumbnail" style="max-height: 110px;">
                        <div class="small text-muted mt-1">
                            Current: {{ $coverWidth ?? '?' }}x{{ $coverHeight ?? '?' }} px
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="remove_cover_image" name="remove_cover_image" value="1">
                            <label class="form-check-label" for="remove_cover_image">Remove current cover</label>
                        </div>
                    </div>
                @endif
            </div>

            @if(isset($fieldConfigs) && $fieldConfigs->count() > 0)
                <div class="col-12">
                    <hr class="my-1">
                    <h6 class="mb-1">{{ __('app.Additional Fields') }}</h6>
                    @include('components.dynamic-content-fields', ['fieldConfigs' => $fieldConfigs, 'content' => $content ?? null])
                </div>
            @endif

            @if($cfv['show_multimedia'] ?? true)
            <div class="col-12">
                <hr class="my-1">
                <h6 class="mb-1">{{ __('app.Multimedia') }}</h6>
                @include('components.content-multimedia-selector', ['selectedMultimedia' => $selectedMultimedia ?? []])
            </div>
            @endif
        </div>

        <div class="pt-3 mt-3">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('app.Save') }}</button>
                <a href="{{ route('contents.index') }}" class="btn btn-label-secondary">{{ __('app.Cancel') }}</a>
            </div>
        </div>
    </form>
</div>
@endsection

@section('page-style')
<style>
.tab-content {
    padding: 0 !important;
}
.tab-pane {
    padding-top: 0 !important;
}
</style>
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


    // Initialize Quill editors for each language
    const contentEditors = {};
    const availableLocales = @json(array_keys($availableLocales ?? ['es' => 'Español']));
    
    availableLocales.forEach(function(locale) {
        const editorId = '#content-editor-' + locale;
        const inputId = '#content_' + locale;
        
        if (document.querySelector(editorId)) {
            contentEditors[locale] = new Quill(editorId, {
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
            const contentInput = document.querySelector(inputId);
            if (contentInput && contentInput.value) {
                contentEditors[locale].root.innerHTML = contentInput.value;
            }
        }
    });

    // Update hidden inputs on form submit
    document.querySelector('form').addEventListener('submit', function() {
        availableLocales.forEach(function(locale) {
            const inputId = '#content_' + locale;
            const input = document.querySelector(inputId);
            if (input && contentEditors[locale]) {
                input.value = contentEditors[locale].root.innerHTML;
            }
        });
    });

    @php
        $sectionCoverConfigMap = ($sectionCategories ?? collect())->mapWithKeys(function ($sectionCategory) {
            $cover = is_array($sectionCategory->data['cover'] ?? null) ? $sectionCategory->data['cover'] : [];

            return [
                (string) $sectionCategory->id => [
                    'max_width' => isset($cover['max_width']) ? (int) $cover['max_width'] : null,
                    'max_height' => isset($cover['max_height']) ? (int) $cover['max_height'] : null,
                    'crop' => ! empty($cover['crop']),
                ],
            ];
        })->toArray();
    @endphp
    const sectionCoverConfigMap = @json($sectionCoverConfigMap);
    const resolvedSectionId = @json((string) $resolvedSectionId);
    const coverHintEl = document.getElementById('cover-settings-hint');

    function renderCoverHint(sectionId) {
        if (!coverHintEl) {
            return;
        }

        const cfg = sectionCoverConfigMap[String(sectionId)];
        if (!cfg) {
            coverHintEl.textContent = 'Select a section to see cover size and crop rules.';
            return;
        }

        const width = cfg.max_width ? cfg.max_width + 'px' : 'auto';
        const height = cfg.max_height ? cfg.max_height + 'px' : 'auto';
        const cropText = cfg.crop ? 'enabled' : 'disabled';
        coverHintEl.textContent = 'Max: ' + width + ' x ' + height + '. Crop: ' + cropText + '.';
    }

    renderCoverHint(resolvedSectionId);

    const sectionSelectEl = document.getElementById('section_category_id');
    const initialSectionValue = sectionSelectEl ? String(sectionSelectEl.value || '') : '';

    $('#section_category_id').on('change', function () {
        const nextSectionId = String(this.value || '');
        renderCoverHint(nextSectionId);

        if (nextSectionId === initialSectionValue) {
            return;
        }

        const url = new URL(window.location.href);
        if (nextSectionId !== '') {
            url.searchParams.set('section_id', nextSectionId);
        } else {
            url.searchParams.delete('section_id');
        }
        window.location.assign(url.toString());
    });
});
</script>
@endsection
