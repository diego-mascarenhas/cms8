@extends('layouts/layoutMaster')

@section('title', isset($multimedia) ? __('Edit Media') : __('Create Media'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Multimedia') }}/</span>
            {{ isset($multimedia) ? __('Edit') : __('Create') }}
        </h4>
        <p class="text-muted">{{ __('Manage multimedia files') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('multimedia.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ isset($multimedia) ? __('Edit Media') : __('Create Media') }}</h5>
    <form class="card-body"
        action="{{ isset($multimedia) ? route('multimedia.update', $multimedia->id) : route('multimedia.store') }}"
        method="POST"
        enctype="multipart/form-data">
        @csrf
        @if(isset($multimedia))
            @method('PUT')
        @endif

        @php
            $selectedTags = old('tags', $selectedTags ?? []);
            $selectedGalleries = old('galleries', $selectedGalleries ?? []);
            $currentStatus = (int) old('status', $multimedia->status?->value ?? \App\Enums\MultimediaStatus::ACTIVE->value);
            $currentVisibility = (int) old('visibility', $multimedia->visibility?->value ?? \App\Enums\MultimediaVisibility::PRIVATE->value);
            $selectedTags = is_array($selectedTags) ? $selectedTags : [];
            $selectedGalleries = is_array($selectedGalleries) ? $selectedGalleries : [];
        @endphp

        <div class="row g-3">
            <div class="col-md-6">
                <x-input-general
                    id="title"
                    label="{{ __('Title') }}"
                    value="{{ old('title', $multimedia->title ?? '') }}"
                />
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select select2">
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}" {{ $currentStatus === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                @error('status')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="visibility">{{ __('Visibility') }}</label>
                <select id="visibility" name="visibility" class="form-select select2">
                    @foreach($visibilityOptions as $visibility)
                        <option value="{{ $visibility->value }}" {{ $currentVisibility === $visibility->value ? 'selected' : '' }}>
                            {{ $visibility->label() }}
                        </option>
                    @endforeach
                </select>
                @error('visibility')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="category_id">{{ __('Category') }}</label>
                <select id="category_id" name="category_id" class="form-select select2">
                    <option value="">{{ __('No category') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (int) old('category_id', $multimedia->category_id ?? 0) === $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="tags">{{ __('Tags') }}</label>
                <select id="tags" name="tags[]" class="form-select select2" multiple>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->name }}" {{ in_array($tag->name, $selectedTags, true) ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
                @error('tags')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="galleries">{{ __('Galleries') }}</label>
                <select id="galleries" name="galleries[]" class="form-select select2" multiple>
                    @foreach($galleryTags as $tag)
                        <option value="{{ $tag->name }}" {{ in_array($tag->name, $selectedGalleries, true) ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
                @error('galleries')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <x-input-textarea
                    id="description"
                    label="{{ __('Description') }}"
                    value="{{ old('description', $multimedia->description ?? '') }}"
                />
            </div>

            @if(isset($multimedia))
                <div class="col-md-6">
                    <label class="form-label">{{ __('Current File') }}</label>
                    <div class="d-flex align-items-center gap-2">
                        @php
                            $previewUrl = $multimedia->getFirstMediaUrl('poster')
                                ?: $multimedia->getFirstMediaUrl('media', 'poster')
                                ?: $multimedia->getFirstMediaUrl('media', 'thumb');
                            $previewUrl = $previewUrl ?: $multimedia->getFirstMediaUrl('media');
                        @endphp
                        @if($previewUrl)
                            <img src="{{ $previewUrl }}" alt="{{ $multimedia->title }}" class="rounded" width="80" height="80">
                        @endif
                        <a href="{{ $multimedia->getFirstMediaUrl('media') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-eye me-1"></i>{{ __('View') }}
                        </a>
                    </div>
                </div>
            @endif

            <div class="col-md-6">
                <label class="form-label" for="{{ isset($multimedia) ? 'media' : 'files' }}">
                    {{ isset($multimedia) ? __('Replace File') : __('Files') }}
                </label>
                @if(isset($multimedia))
                    <input type="file" id="media" name="media" class="form-control">
                @else
                    <input type="file" id="files" name="files[]" class="form-control" multiple required>
                @endif
                @error('files')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
                @error('media')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="poster">{{ __('Poster Image (Optional)') }}</label>
                <input type="file" id="poster" name="poster" class="form-control" accept="image/*">
                @error('poster')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
                <div class="form-text">{{ __('Poster is used for video previews. For multiple files, upload one by one to set a poster.') }}</div>
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
                <a href="{{ route('multimedia.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        $('#status, #visibility, #category_id').select2({ width: '100%' });
        $('#tags, #galleries').select2({
            width: '100%',
            tags: true,
            tokenSeparators: [',']
        });
    });
</script>
@endpush
