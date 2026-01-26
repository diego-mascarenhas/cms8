@extends('layouts/layoutMaster')

@section('title', isset($category) ? __('app.Edit Category') : __('app.Create Category'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('content')
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ isset($category) ? __('app.Edit Category') : __('app.Create Category') }}</h5>
            </div>
            <div class="card-body">
                @if(session('error'))
                    <div class="alert alert-danger mb-3">
                        {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ isset($category) ? route('categories.update', $category->id) : route('categories.store') }}">
                    @csrf
                    @if(isset($category))
                        @method('PUT')
                        <input type="hidden" name="id" value="{{ $category->id }}">
                    @endif
                    @php
                        $categoryData = $category->data ?? [];
                    @endphp

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

                                @if(isset($parent))
                                    <div class="form-text mt-1">
                                        {{ __('app.Will be created as a subcategory of') }}: <strong>{{ $parent->name }}</strong>
                                    </div>
                                @endif
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

                    <div class="mb-3">
                        <label for="order" class="form-label">{{ __('app.Display Order') }}</label>
                        <input type="number" class="form-control @error('order') is-invalid @enderror"
                            id="order" name="order" value="{{ old('order', $category->order ?? 0) }}" min="0">
                        @error('order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">{{ __('app.Lower numbers appear first. Leave as 0 for automatic ordering.') }}</div>
                    </div>

                    <div id="content-options" class="border rounded p-3 mb-3 d-none">
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
                                
                                <div class="col-md-6">
                                    <label class="form-label">Segundo orden (opcional)</label>
                                    <select class="form-select" name="content_ordering[1][column]">
                                        <option value="">-- Sin segundo orden --</option>
                                        <option value="order" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? 'created_at') === 'order' ? 'selected' : '' }}>Orden manual</option>
                                        <option value="created_at" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Fecha de creación</option>
                                        <option value="updated_at" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? '') === 'updated_at' ? 'selected' : '' }}>Fecha de actualización</option>
                                        <option value="title" {{ old('content_ordering.1.column', $categoryData['content_ordering'][1]['column'] ?? '') === 'title' ? 'selected' : '' }}>Título</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dirección</label>
                                    <select class="form-select" name="content_ordering[1][direction]">
                                        <option value="asc" {{ old('content_ordering.1.direction', $categoryData['content_ordering'][1]['direction'] ?? 'desc') === 'asc' ? 'selected' : '' }}>Ascendente</option>
                                        <option value="desc" {{ old('content_ordering.1.direction', $categoryData['content_ordering'][1]['direction'] ?? 'desc') === 'desc' ? 'selected' : '' }}>Descendente</option>
                                    </select>
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

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">{{ __('app.Save') }}</button>
                        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">{{ __('app.Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
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
    // Initialize Select2 for dropdowns
    $('#module_id, #parent_id').select2();

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
    const moduleSelect = document.getElementById('module_id');

    function toggleModuleOptions() {
        if (!moduleSelect) {
            return;
        }

        const selectedModule = moduleSelect.value;
        
        // Toggle multimedia options
        if (multimediaOptions) {
            if (multimediaModuleId && selectedModule === multimediaModuleId.toString()) {
                multimediaOptions.classList.remove('d-none');
            } else {
                multimediaOptions.classList.add('d-none');
            }
        }
        
        // Toggle content options
        if (contentOptions) {
            if (contentsModuleId && selectedModule === contentsModuleId.toString()) {
                contentOptions.classList.remove('d-none');
            } else {
                contentOptions.classList.add('d-none');
            }
        }
    }

    toggleModuleOptions();
    moduleSelect.addEventListener('change', toggleModuleOptions);
});
</script>
@endsection
