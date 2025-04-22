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
});
</script>
@endsection