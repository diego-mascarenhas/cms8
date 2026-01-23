@extends('layouts/layoutMaster')

@section('title', isset($category) ? 'Edit Category' : 'Create Category')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('content')
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ isset($category) ? 'Edit Category' : 'Create Category' }}</h5>
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
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                    id="name" name="name" value="{{ old('name', $category->name ?? '') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label d-block">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="status" name="status"
                                        {{ (old('status', isset($category) && $category->status) ? 'checked' : '') }}>
                                    <label class="form-check-label" for="status">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="module_id" class="form-label">Module</label>
                                <select class="form-select @error('module_id') is-invalid @enderror" id="module_id" name="module_id">
                                    <option value="">-- Select Module --</option>
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
                                <label for="parent_id" class="form-label">Parent Category</label>
                                <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                                    <option value="">-- Top Level --</option>
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
                                        Will be created as a subcategory of: <strong>{{ $parent->name }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                            id="description" name="description" rows="3">{{ old('description', $category->description ?? '') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="order" class="form-label">Display Order</label>
                        <input type="number" class="form-control @error('order') is-invalid @enderror" 
                            id="order" name="order" value="{{ old('order', $category->order ?? 0) }}" min="0">
                        @error('order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Lower numbers appear first. Leave as 0 for automatic ordering.</div>
                    </div>

                    <div id="multimedia-options" class="border rounded p-3 mb-3 d-none">
                        <h6 class="mb-3">Multimedia Settings</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="image_width" class="form-label">Image Width (px)</label>
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
                                    <label for="image_height" class="form-label">Image Height (px)</label>
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
                                    <label for="thumb_width" class="form-label">Thumbnail Width (px)</label>
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
                                    <label for="thumb_height" class="form-label">Thumbnail Height (px)</label>
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
                                    <label for="poster_width" class="form-label">Poster Width (px)</label>
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
                                    <label for="poster_height" class="form-label">Poster Height (px)</label>
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
                            <label for="fit" class="form-label">Fit Mode</label>
                            <select class="form-select @error('fit') is-invalid @enderror" id="fit" name="fit">
                                <option value="">Use Default</option>
                                <option value="crop" {{ old('fit', $categoryData['fit'] ?? '') === 'crop' ? 'selected' : '' }}>Crop</option>
                                <option value="contain" {{ old('fit', $categoryData['fit'] ?? '') === 'contain' ? 'selected' : '' }}>Contain</option>
                                <option value="max" {{ old('fit', $categoryData['fit'] ?? '') === 'max' ? 'selected' : '' }}>Max</option>
                                <option value="stretch" {{ old('fit', $categoryData['fit'] ?? '') === 'stretch' ? 'selected' : '' }}>Stretch</option>
                            </select>
                            @error('fit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Used for image, thumbnail, and poster resizing.</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Save</button>
                        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for dropdowns
    $('#module_id, #parent_id').select2();

    const multimediaModuleId = '{{ $multimediaModuleId ?? '' }}';
    const multimediaOptions = document.getElementById('multimedia-options');
    const moduleSelect = document.getElementById('module_id');

    function toggleMultimediaOptions() {
        if (!multimediaOptions || !moduleSelect) {
            return;
        }

        const selectedModule = moduleSelect.value;
        if (multimediaModuleId && selectedModule === multimediaModuleId.toString()) {
            multimediaOptions.classList.remove('d-none');
        } else {
            multimediaOptions.classList.add('d-none');
        }
    }

    toggleMultimediaOptions();
    moduleSelect.addEventListener('change', toggleMultimediaOptions);
});
</script>
@endsection