@extends('layouts/layoutMaster')

@section('title', isset($category) ? __('app.Edit Category') : __('app.Create Category'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <form method="POST" action="{{ isset($category) ? route('categories.update', $category->id) : route('categories.store') }}">
            <div class="row g-3 align-items-start">
                <div class="col-12 col-xl-8">
                    @csrf
                    @php
                        $indexReturnModuleId = old('return_module_id', $returnModuleIdForIndex ?? null);
                    @endphp
                    @if($indexReturnModuleId)
                        <input type="hidden" name="return_module_id" value="{{ (int) $indexReturnModuleId }}">
                    @endif
                    @if(isset($category))
                        @method('PUT')
                        <input type="hidden" name="id" value="{{ $category->id }}">
                    @endif
                    @php
                        $categoryData = isset($category) ? ($category->data ?? []) : [];
                    @endphp

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ isset($category) ? __('app.Edit Category') : __('app.Create Category') }}</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger mb-3">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __('app.Name') }}</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name', $category->name ?? '') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label d-block">{{ __('app.Status') }}</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="status" name="status"
                                        {{ (old('status', isset($category) && $category->status) ? 'checked' : '') }}>
                                    <label class="form-check-label" for="status">{{ __('app.Active') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="module_id" class="form-label">{{ __('app.Module') }}</label>
                                <select class="form-select @error('module_id') is-invalid @enderror" id="module_id" name="module_id">
                                    <option value="">{{ __('app.Select Module') }}</option>
                                    @foreach($modules as $module)
                                        <option value="{{ $module->id }}"
                                            {{ old('module_id', $category->module_id ?? request()->get('module_id')) == $module->id ? 'selected' : '' }}>
                                            {{ $module->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('module_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parent_id" class="form-label">{{ __('app.Parent Category') }}</label>
                                <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                                    <option value="">{{ __('app.Top Level') }}</option>
                                    @foreach($parentCategories as $parentCategory)
                                        <option value="{{ $parentCategory->id }}"
                                            {{ old('parent_id', $category->parent_id ?? request()->get('parent_id')) == $parentCategory->id ? 'selected' : '' }}>
                                            {{ $parentCategory->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 d-none">
                        <label for="description" class="form-label">{{ __('app.Description') }}</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                            id="description" name="description" rows="3">{{ old('description', $category->description ?? '') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Order is adjusted from the categories list (drag); value is preserved on save. --}}
                    <input type="hidden" name="order" value="{{ old('order', isset($category) ? ($category->order ?? 0) : 0) }}">
                    @error('order')
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div id="content-ordering-section" class="card mb-3 d-none">
                <div class="card-body">
                    <h6 class="mb-3">Configuración de Ordenamiento de Contenidos</h6>
                    <div class="mb-3">
                        <label class="form-label">Ordenamiento por defecto</label>
                        <div class="form-text mb-2">Configura cómo se ordenarán los contenidos en esta categoría</div>

                        <div class="row g-3" id="content-ordering-rules">
                            <div class="col-md-6">
                                <label class="form-label">Primer orden</label>
                                <select class="form-select" name="content_ordering[0][column]">
                                    <option value="order" {{ old('content_ordering.0.column', $categoryData['content_ordering'][0]['column'] ?? 'order') === 'order' ? 'selected' : '' }}>Orden manual</option>
                                    <option value="created_at" {{ old('content_ordering.0.column', $categoryData['content_ordering'][0]['column'] ?? '') === 'created_at' ? 'selected' : '' }}>Fecha de creación</option>
                                    <option value="updated_at" {{ old('content_ordering.0.column', $categoryData['content_ordering'][0]['column'] ?? '') === 'updated_at' ? 'selected' : '' }}>Fecha de actualización</option>
                                    <option value="title" {{ old('content_ordering.0.column', $categoryData['content_ordering'][0]['column'] ?? '') === 'title' ? 'selected' : '' }}>Título</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dirección</label>
                                <select class="form-select" name="content_ordering[0][direction]">
                                    <option value="asc" {{ old('content_ordering.0.direction', $categoryData['content_ordering'][0]['direction'] ?? 'asc') === 'asc' ? 'selected' : '' }}>Ascendente</option>
                                    <option value="desc" {{ old('content_ordering.0.direction', $categoryData['content_ordering'][0]['direction'] ?? '') === 'desc' ? 'selected' : '' }}>Descendente</option>
                                </select>
                            </div>

                            {{-- Second sort criterion: hidden in UI for now; inputs remain so saves keep defaults / stored values. --}}
                            <div class="col-md-6 d-none" aria-hidden="true">
                                <label class="form-label">Segundo orden (opcional)</label>
                                <select class="form-select" name="content_ordering[1][column]">
                                    <option value="">-- Sin segundo orden --</option>
                                    <option value="order" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? 'created_at') === 'order' ? 'selected' : '' }}>Orden manual</option>
                                    <option value="created_at" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Fecha de creación</option>
                                    <option value="updated_at" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? '') === 'updated_at' ? 'selected' : '' }}>Fecha de actualización</option>
                                    <option value="title" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? '') === 'title' ? 'selected' : '' }}>Título</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-none" aria-hidden="true">
                                <label class="form-label">Dirección</label>
                                <select class="form-select" name="content_ordering[1][direction]">
                                    <option value="asc" {{ old('content_ordering.1.direction', $categoryData['content_ordering'][1]['direction'] ?? 'desc') === 'asc' ? 'selected' : '' }}>Ascendente</option>
                                    <option value="desc" {{ old('content_ordering.1.direction', $categoryData['content_ordering'][1]['direction'] ?? 'desc') === 'desc' ? 'selected' : '' }}>Descendente</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="content-public-api-section" class="card mb-3 d-none">
                <div class="card-body">
                    <h6 class="mb-2">{{ __('app.External site and API') }}</h6>
                    <p class="form-text mb-3">{{ __('app.External site and API contents hint') }}</p>
                    <div class="row g-3 mb-2">
                        <div class="col-12">
                            <label for="contents_section_slug" class="form-label">{{ __('app.Section slug') }}</label>
                            <input type="text" class="form-control @error('contents_section_slug') is-invalid @enderror" id="contents_section_slug" name="contents_section_slug"
                                value="{{ old('contents_section_slug', $categoryData['slug'] ?? '') }}" placeholder="oba-about" autocomplete="off">
                            @error('contents_section_slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('app.Section slug hint') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="content-options-secondary" class="card mb-3 d-none">
                <div class="card-body">
                    @php
                        $storedCoverVariants = is_array($categoryData['cover']['variants'] ?? null) ? $categoryData['cover']['variants'] : [];
                        $presetVariantLabels = [
                            'logo_strip' => 'Logo strip',
                            'thumb' => 'Thumbnail',
                            'hero' => 'Hero',
                            'square' => 'Square',
                            'og' => 'Open Graph',
                            'web' => 'Web',
                        ];
                        $presetVariantDefaults = [
                            'logo_strip' => ['width' => 100, 'height' => 60, 'fit' => 'contain'],
                            'thumb' => ['width' => 320, 'height' => 320, 'fit' => 'crop'],
                            'hero' => ['width' => 1600, 'height' => 900, 'fit' => 'crop'],
                            'square' => ['width' => 800, 'height' => 800, 'fit' => 'crop'],
                            'og' => ['width' => 1200, 'height' => 630, 'fit' => 'crop'],
                            'web' => ['width' => 1400, 'height' => 1400, 'fit' => 'max'],
                        ];
                        $storedCustomVariantKey = collect(array_keys($storedCoverVariants))
                            ->first(fn($k) => ! array_key_exists($k, $presetVariantLabels));
                        $customVariantKey = 'custom';
                        $customVariantEnabledByDefault = $storedCustomVariantKey !== null;
                        $customVariantEnabled = in_array($customVariantKey, old('cover_variants', $customVariantEnabledByDefault ? [$customVariantKey] : []), true)
                            || (is_array(old('cover_variants')) && in_array($customVariantKey, old('cover_variants', []), true));
                    @endphp
                    <h6 class="mb-2">Cover variants</h6>
                    <p class="form-text mb-3">Select predefined variants and optional custom variant. Use fit = contain/max for logos to avoid cropping.</p>
                    <div class="row g-3 mb-2">
                        @foreach($presetVariantLabels as $variantKey => $variantLabel)
                            @php
                                $enabledByDefault = array_key_exists($variantKey, $storedCoverVariants);
                                $enabled = in_array($variantKey, old('cover_variants', $enabledByDefault ? [$variantKey] : []), true)
                                    || (is_array(old('cover_variants')) && in_array($variantKey, old('cover_variants', []), true));
                                $variantCfg = is_array($storedCoverVariants[$variantKey] ?? null) ? $storedCoverVariants[$variantKey] : [];
                                $variantWidth = old("cover_variant_width.{$variantKey}", $variantCfg['width'] ?? $presetVariantDefaults[$variantKey]['width']);
                                $variantHeight = old("cover_variant_height.{$variantKey}", $variantCfg['height'] ?? $presetVariantDefaults[$variantKey]['height']);
                                $variantFit = old("cover_variant_fit.{$variantKey}", $variantCfg['fit'] ?? $presetVariantDefaults[$variantKey]['fit']);
                            @endphp
                            <div class="col-12 border rounded p-2">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="cover_variant_{{ $variantKey }}" name="cover_variants[]" value="{{ $variantKey }}" @checked($enabled)>
                                    <label class="form-check-label" for="cover_variant_{{ $variantKey }}">{{ $variantLabel }} ({{ $variantKey }})</label>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input type="number" class="form-control" name="cover_variant_width[{{ $variantKey }}]" value="{{ $variantWidth }}" min="1" max="10000" placeholder="Width">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" class="form-control" name="cover_variant_height[{{ $variantKey }}]" value="{{ $variantHeight }}" min="1" max="10000" placeholder="Height">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="cover_variant_fit[{{ $variantKey }}]">
                                            <option value="crop" @selected($variantFit === 'crop')>crop</option>
                                            <option value="contain" @selected($variantFit === 'contain')>contain</option>
                                            <option value="max" @selected($variantFit === 'max')>max</option>
                                            <option value="stretch" @selected($variantFit === 'stretch')>stretch</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="col-12 border rounded p-2">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="cover_variant_custom" name="cover_variants[]" value="custom" @checked($customVariantEnabled)>
                                <label class="form-check-label" for="cover_variant_custom">Custom (custom)</label>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="number" class="form-control" name="cover_custom_variant_width" value="{{ old('cover_custom_variant_width', $storedCustomVariantKey ? ($storedCoverVariants[$storedCustomVariantKey]['width'] ?? '') : '') }}" min="1" max="10000" placeholder="Width">
                                </div>
                                <div class="col-md-4">
                                    <input type="number" class="form-control" name="cover_custom_variant_height" value="{{ old('cover_custom_variant_height', $storedCustomVariantKey ? ($storedCoverVariants[$storedCustomVariantKey]['height'] ?? '') : '') }}" min="1" max="10000" placeholder="Height">
                                </div>
                                <div class="col-md-4">
                                    @php
                                        $customFit = old('cover_custom_variant_fit', $storedCustomVariantKey ? ($storedCoverVariants[$storedCustomVariantKey]['fit'] ?? 'max') : 'max');
                                    @endphp
                                    <select class="form-select" name="cover_custom_variant_fit">
                                        <option value="crop" @selected($customFit === 'crop')>crop</option>
                                        <option value="contain" @selected($customFit === 'contain')>contain</option>
                                        <option value="max" @selected($customFit === 'max')>max</option>
                                        <option value="stretch" @selected($customFit === 'stretch')>stretch</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div id="multimedia-options" class="border rounded p-3 mb-3 d-none">
                        <h6 class="mb-3">{{ __('app.Multimedia Settings') }}</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="image_width" class="form-label">{{ __('app.Image Width') }} (px)</label>
                                    <input type="number" class="form-control @error('image_width') is-invalid @enderror"
                                        id="image_width" name="image_width"
                                        value="{{ old('image_width', $categoryData['image_width'] ?? '') }}" min="1" max="10000">
                                    @error('image_width')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="image_height" class="form-label">{{ __('app.Image Height') }} (px)</label>
                                    <input type="number" class="form-control @error('image_height') is-invalid @enderror"
                                        id="image_height" name="image_height"
                                        value="{{ old('image_height', $categoryData['image_height'] ?? '') }}" min="1" max="10000">
                                    @error('image_height')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="thumb_width" class="form-label">{{ __('app.Thumbnail Width') }} (px)</label>
                                    <input type="number" class="form-control @error('thumb_width') is-invalid @enderror"
                                        id="thumb_width" name="thumb_width"
                                        value="{{ old('thumb_width', $categoryData['thumb_width'] ?? '') }}" min="1" max="10000">
                                    @error('thumb_width')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="thumb_height" class="form-label">{{ __('app.Thumbnail Height') }} (px)</label>
                                    <input type="number" class="form-control @error('thumb_height') is-invalid @enderror"
                                        id="thumb_height" name="thumb_height"
                                        value="{{ old('thumb_height', $categoryData['thumb_height'] ?? '') }}" min="1" max="10000">
                                    @error('thumb_height')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="poster_width" class="form-label">{{ __('app.Poster Width') }} (px)</label>
                                    <input type="number" class="form-control @error('poster_width') is-invalid @enderror"
                                        id="poster_width" name="poster_width"
                                        value="{{ old('poster_width', $categoryData['poster_width'] ?? '') }}" min="1" max="10000">
                                    @error('poster_width')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="poster_height" class="form-label">{{ __('app.Poster Height') }} (px)</label>
                                    <input type="number" class="form-control @error('poster_height') is-invalid @enderror"
                                        id="poster_height" name="poster_height"
                                        value="{{ old('poster_height', $categoryData['poster_height'] ?? '') }}" min="1" max="10000">
                                    @error('poster_height')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="fit" class="form-label">{{ __('app.Fit Mode') }}</label>
                            <select class="form-select @error('fit') is-invalid @enderror" id="fit" name="fit">
                                <option value="">{{ __('app.Use Default') }}</option>
                                <option value="crop" {{ old('fit', $categoryData['fit'] ?? '') === 'crop' ? 'selected' : '' }}>{{ __('app.Crop') }}</option>
                                <option value="contain" {{ old('fit', $categoryData['fit'] ?? '') === 'contain' ? 'selected' : '' }}>{{ __('app.Contain') }}</option>
                                <option value="max" {{ old('fit', $categoryData['fit'] ?? '') === 'max' ? 'selected' : '' }}>{{ __('app.Max') }}</option>
                                <option value="stretch" {{ old('fit', $categoryData['fit'] ?? '') === 'stretch' ? 'selected' : '' }}>{{ __('app.Stretch') }}</option>
                            </select>
                            @error('fit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('app.Used for image, thumbnail, and poster resizing.') }}</div>
                        </div>
            </div>

            <div class="row mb-3 d-none">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tags" class="form-label">Etiquetas</label>
                        <select id="tags" name="tags[]" class="form-select select2" multiple>
                            @php
                                $selectedTags = old('tags', isset($category) ? $category->tags->pluck('name')->toArray() : []);
                            @endphp
                            @foreach($tags as $tag)
                                <option value="{{ $tag->name }}" {{ in_array($tag->name, $selectedTags, true) ? 'selected' : '' }}>
                                    {{ $tag->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('tags')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mt-1 mb-3">
                <button type="submit" class="btn btn-primary me-2">{{ __('app.Save') }}</button>
                <a href="{{ route('categories.index', array_filter(['module_id' => $indexReturnModuleId])) }}" class="btn btn-outline-secondary">{{ __('app.Cancel') }}</a>
            </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div id="content-options" class="card mb-3 d-none">
                        <div class="card-body">
                            <input type="hidden" name="content_locales_present" value="1">
                            @php
                                $localeLabels = \App\Support\ContentsSectionCategoryData::supportedLocaleLabels();
                                $mergedLocales = \App\Support\ContentsSectionCategoryData::mergeContentLocalesFromStorage($categoryData['content_locales'] ?? null);
                                $contentFormFields = \App\Support\ContentsSectionCategoryData::mergeContentFormVisibility($categoryData['content_form'] ?? null);
                                $isCreatingCategory = ! isset($category);

                                if ($isCreatingCategory && old('content_locales') === null)
                                {
                                    // New category: start with no locales selected.
                                    $mergedLocales = [];
                                }

                                if ($isCreatingCategory && old('content_form') === null)
                                {
                                    // New category: start with selected fields disabled by default.
                                    $contentFormFields['show_subtitle'] = false;
                                    $contentFormFields['show_main_content'] = false;
                                    $contentFormFields['show_url'] = false;
                                    $contentFormFields['show_seo'] = false;
                                    $contentFormFields['show_multimedia'] = false;
                                }
                            @endphp
                            <h6 class="mb-2">{{ __('app.Content form visibility') }}</h6>
                            <p class="form-text mb-3">{{ __('app.Content form visibility hint') }}</p>
                            <div class="row g-3 text-start mb-4">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="content_form[show_title]" value="0">
                                        <input type="checkbox" class="form-check-input" id="cff_show_title" name="content_form[show_title]" value="1"
                                            @checked(old('content_form.show_title', $contentFormFields['show_title']))>
                                        <label class="form-check-label" for="cff_show_title">{{ __('app.Show title on content form') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="content_form[show_subtitle]" value="0">
                                        <input type="checkbox" class="form-check-input" id="cff_show_subtitle" name="content_form[show_subtitle]" value="1"
                                            @checked(old('content_form.show_subtitle', $contentFormFields['show_subtitle']))>
                                        <label class="form-check-label" for="cff_show_subtitle">{{ __('app.Show subtitle on content form') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="content_form[show_main_content]" value="0">
                                        <input type="checkbox" class="form-check-input" id="cff_show_main_content" name="content_form[show_main_content]" value="1"
                                            @checked(old('content_form.show_main_content', $contentFormFields['show_main_content']))>
                                        <label class="form-check-label" for="cff_show_main_content">{{ __('app.Show main content on content form') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="content_form[show_featured]" value="0">
                                        <input type="checkbox" class="form-check-input" id="cff_show_featured" name="content_form[show_featured]" value="1"
                                            @checked(old('content_form.show_featured', $contentFormFields['show_featured']))>
                                        <label class="form-check-label" for="cff_show_featured">{{ __('app.Show cover image options on content form') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="content_form[show_url]" value="0">
                                        <input type="checkbox" class="form-check-input" id="cff_show_url" name="content_form[show_url]" value="1"
                                            @checked(old('content_form.show_url', $contentFormFields['show_url']))>
                                        <label class="form-check-label" for="cff_show_url">{{ __('app.Show caption on content form') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="content_form[show_seo]" value="0">
                                        <input type="checkbox" class="form-check-input" id="cff_show_seo" name="content_form[show_seo]" value="1"
                                            @checked(old('content_form.show_seo', $contentFormFields['show_seo']))>
                                        <label class="form-check-label" for="cff_show_seo">{{ __('app.Show SEO block on content form') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="content_form[show_multimedia]" value="0">
                                        <input type="checkbox" class="form-check-input" id="cff_show_multimedia" name="content_form[show_multimedia]" value="1"
                                            @checked(old('content_form.show_multimedia', $contentFormFields['show_multimedia']))>
                                        <label class="form-check-label" for="cff_show_multimedia">{{ __('app.Show multimedia on content form') }}</label>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-2">
                            <h6 class="mb-2">{{ __('app.Content form languages') }}</h6>
                            <p class="form-text mb-3">{{ __('app.Content form languages hint') }}</p>
                            <div class="row g-2 mb-4">
                                @foreach($localeLabels as $localeCode => $localeLabel)
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="content_locale_{{ $localeCode }}" name="content_locales[]" value="{{ $localeCode }}"
                                                @checked(in_array($localeCode, old('content_locales', $mergedLocales), true))>
                                            <label class="form-check-label" for="content_locale_{{ $localeCode }}">{{ $localeLabel }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <style>
        /* Hide "No results found" message for tags select */
        #select2-tags-results .select2-results__message {
            display: none !important;
        }
    </style>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const parentCategoriesByModule = @json($parentCategoriesByModule ?? []);
    const topLevelParentLabel = @json(__('app.Top Level'));
    const moduleSelectEl = document.getElementById('module_id');
    const $parentId = $('#parent_id');

    function rebuildParentCategoryOptions() {
        if (! moduleSelectEl || ! $parentId.length) {
            return;
        }

        if ($parentId.hasClass('select2-hidden-accessible')) {
            $parentId.select2('destroy');
        }

        const moduleId = moduleSelectEl.value;
        const list = parentCategoriesByModule[moduleId] || parentCategoriesByModule[String(moduleId)] || [];
        const previous = String($parentId.val() || '');

        $parentId.empty();
        $parentId.append(new Option(topLevelParentLabel, '', false, previous === ''));

        let matched = previous === '';
        list.forEach(function (row) {
            const idStr = String(row.id);
            const selected = idStr === previous;
            if (selected) {
                matched = true;
            }
            $parentId.append(new Option(row.name, idStr, false, selected));
        });

        if (! matched) {
            $parentId.prop('selectedIndex', 0);
        }

        $parentId.select2();
    }

    $('#module_id').select2();
    rebuildParentCategoryOptions();

    // Initialize Select2 for tags with autocomplete and creation
    const tagsSelect = $('#tags');
    tagsSelect.select2({
        width: '100%',
        tags: true,
        tokenSeparators: [','],
        placeholder: 'Buscar o crear etiquetas...',
        language: {
            noResults: function() {
                return '';
            },
            searching: function() {
                return 'Buscando...';
            }
        },
        ajax: {
            url: '{{ route("tags.search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    type: 'general'
                };
            },
            processResults: function (data) {
                return {
                    results: data.map(function(tag) {
                        return {
                            id: tag.name,
                            text: tag.name
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        createTag: function (params) {
            const term = $.trim(params.term);
            if (term === '') {
                return null;
            }
            return {
                id: term,
                text: term,
                newTag: true
            };
        }
    });

    // Hide "No results found" message
    tagsSelect.on('select2:open', function() {
        setTimeout(function() {
            const dropdown = $('.select2-results');
            dropdown.find('.select2-results__message').hide();
        }, 10);
    });

    tagsSelect.on('select2:selecting', function() {
        $('.select2-results__message').hide();
    });

    // Also hide on results update
    tagsSelect.on('results:message', function() {
        setTimeout(function() {
            $('.select2-results__message').hide();
        }, 10);
    });

    const multimediaModuleId = '{{ $multimediaModuleId ?? '' }}';
    const contentsModuleId = '{{ \App\Models\Module::where("key", "contents")->value("id") ?? "" }}';
    const multimediaOptions = document.getElementById('multimedia-options');
    const contentOptions = document.getElementById('content-options');
    const contentPublicApiSection = document.getElementById('content-public-api-section');
    const contentOptionsSecondary = document.getElementById('content-options-secondary');
    const contentOrderingSection = document.getElementById('content-ordering-section');

    function toggleModuleOptions() {
        if (! moduleSelectEl) {
            return;
        }

        const selectedModule = moduleSelectEl.value;
        
        // Toggle multimedia options
        if (multimediaOptions) {
            if (multimediaModuleId && selectedModule === multimediaModuleId.toString()) {
                multimediaOptions.classList.remove('d-none');
            } else {
                multimediaOptions.classList.add('d-none');
            }
        }
        
        // Toggle content options (languages + form visibility) and ordering — Contents module only
        const showContentsOptions = contentsModuleId && selectedModule === contentsModuleId.toString();
        if (contentOptions) {
            if (showContentsOptions) {
                contentOptions.classList.remove('d-none');
            } else {
                contentOptions.classList.add('d-none');
            }
        }
        if (contentPublicApiSection) {
            if (showContentsOptions) {
                contentPublicApiSection.classList.remove('d-none');
            } else {
                contentPublicApiSection.classList.add('d-none');
            }
        }
        if (contentOptionsSecondary) {
            if (showContentsOptions) {
                contentOptionsSecondary.classList.remove('d-none');
            } else {
                contentOptionsSecondary.classList.add('d-none');
            }
        }
        if (contentOrderingSection) {
            if (showContentsOptions) {
                contentOrderingSection.classList.remove('d-none');
            } else {
                contentOrderingSection.classList.add('d-none');
            }
        }
    }

    toggleModuleOptions();

    // Select2 updates the underlying <select> but the UI selection may only fire
    // jQuery's change handler; bind with jQuery so Contents blocks hide/show correctly.
    $('#module_id').on('change', function () {
        toggleModuleOptions();
        rebuildParentCategoryOptions();
    });
});
</script>
@endsection
