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
            $currentVisibility = (int) old('visibility', $multimedia->visibility?->value ?? \App\Enums\MultimediaVisibility::PUBLIC->value);
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
                <label class="form-label" for="visibility">{{ __('app.Visibility') }}</label>
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
                    <label class="form-label">{{ __('app.Current File') }}</label>
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
                            <i class="ti ti-eye me-1"></i>{{ __('app.View') }}
                        </a>
                    </div>
                </div>
            @endif

            @if(!isset($multimedia))
            <div class="col-12">
                <label class="form-label mb-3">{{ __('app.Files') }}</label>
                <!-- Drop Zone -->
                <div class="drop-zone mb-3" id="dropZone">
                    <div class="mb-3">
                        <i class="ti ti-upload ti-lg text-muted"></i>
                    </div>
                    <h6 class="mb-2">{{ __('app.Drag files here or click to select') }}</h6>
                    <p class="text-muted mb-3">{{ __('app.Supports images, videos, audio and documents') }}</p>
                    <div class="file-input-wrapper">
                        <input type="file" id="files" name="files[]" class="file-input" multiple required accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.zip,.rar">
                        <label for="files" class="file-input-label">
                            <i class="ti ti-plus me-1"></i>{{ __('app.Select Files') }}
                        </label>
                    </div>
                </div>

                <!-- Selected Files Preview -->
                <div id="selectedFilesPreview" class="d-none">
                    <h6 class="mb-3">{{ __('app.Selected Files') }}</h6>
                    <div id="filesList" class="row g-2"></div>
                </div>
                @error('files')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>
            @else
            <div class="col-md-6">
                <label class="form-label" for="media">{{ __('app.Replace File') }}</label>
                <input type="file" id="media" name="media" class="form-control">
                @error('media')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>
            @endif

            <div class="col-md-6">
                <label class="form-label" for="poster">{{ __('app.Poster Image (Optional)') }}</label>
                <input type="file" id="poster" name="poster" class="form-control" accept="image/*">
                @error('poster')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
                <div class="form-text">{{ __('app.Poster is used for video previews. For multiple files, upload one by one to set a poster.') }}</div>
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

        // Drag and drop functionality (only for create form)
        @if(!isset($multimedia))
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('files');
        const selectedFilesPreview = document.getElementById('selectedFilesPreview');
        const filesList = document.getElementById('filesList');
        const removeText = '{{ __('app.Remove') }}';
        let selectedFiles = [];

        if (dropZone && fileInput) {
            // Drag and drop events
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });

            dropZone.addEventListener('drop', handleDrop, false);
            fileInput.addEventListener('change', handleFiles);

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight(e) {
                dropZone.classList.add('dragover');
            }

            function unhighlight(e) {
                dropZone.classList.remove('dragover');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles({ target: { files } });
            }

            function handleFiles(e) {
                const files = Array.from(e.target.files);
                if (files.length > 0) {
                    // Add new files to selectedFiles array
                    files.forEach(file => {
                        if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                            selectedFiles.push(file);
                        }
                    });
                    updateFileInput();
                    renderFilesList();
                }
            }

            function updateFileInput() {
                // Create a new DataTransfer object to update the file input
                const dataTransfer = new DataTransfer();
                selectedFiles.forEach(file => {
                    dataTransfer.items.add(file);
                });
                fileInput.files = dataTransfer.files;
            }

            function renderFilesList() {
                if (selectedFiles.length === 0) {
                    selectedFilesPreview.classList.add('d-none');
                    return;
                }

                selectedFilesPreview.classList.remove('d-none');
                filesList.innerHTML = '';

                selectedFiles.forEach((file, index) => {
                    const fileCard = createFileCard(file, index);
                    filesList.appendChild(fileCard);
                });
            }

            function createFileCard(file, index) {
                const col = document.createElement('div');
                col.className = 'col-md-4 col-lg-3';

                const isImage = file.type.startsWith('image/');
                const isVideo = file.type.startsWith('video/');
                const isAudio = file.type.startsWith('audio/');
                const previewClass = isVideo ? 'video' : (isAudio ? 'audio' : 'document');
                const previewIcon = isVideo ? 'ti ti-video' : (isAudio ? 'ti ti-music' : 'ti ti-file');
                const fileSize = (file.size / 1024).toFixed(2);

                let previewContent = '';
                if (isImage) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = col.querySelector('.file-preview img');
                        if (img) img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    previewContent = '<img src="" alt="' + file.name + '" class="file-preview-img">';
                } else {
                    previewContent = '<div class="file-preview ' + previewClass + '"><i class="' + previewIcon + ' ti-lg"></i></div>';
                }

                col.innerHTML = `
                    <div class="card file-card">
                        <div class="card-body p-2">
                            <div class="file-preview-wrapper mb-2">
                                ${isImage ? '<div class="file-preview">' + previewContent + '</div>' : previewContent}
                            </div>
                            <h6 class="file-name mb-1" title="${file.name}">${file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name}</h6>
                            <small class="text-muted d-block mb-2">${fileSize} KB</small>
                            <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-file" data-index="${index}">
                                <i class="ti ti-trash me-1"></i>${removeText}
                            </button>
                        </div>
                    </div>
                `;

                // Add remove event listener
                col.querySelector('.remove-file').addEventListener('click', function() {
                    selectedFiles.splice(index, 1);
                    updateFileInput();
                    renderFilesList();
                });

                return col;
            }

            // Remove file handler
            document.addEventListener('click', function(e) {
                if (e.target.closest('.remove-file')) {
                    const button = e.target.closest('.remove-file');
                    const index = parseInt(button.getAttribute('data-index'));
                    selectedFiles.splice(index, 1);
                    updateFileInput();
                    renderFilesList();
                }
            });
        }
        @endif
    });
</script>

<style>
.drop-zone {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    background-color: #f9fafb;
    cursor: pointer;
}

.drop-zone.dragover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

.drop-zone:hover {
    border-color: #9ca3af;
    background-color: #f3f4f6;
}

.file-input-wrapper {
    position: relative;
    display: inline-block;
}

.file-input {
    position: absolute;
    left: -9999px;
}

.file-input-label {
    display: inline-block;
    padding: 0.5rem 1rem;
    background-color: #3b82f6;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.file-input-label:hover {
    background-color: #2563eb;
}

.file-card {
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.file-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.file-preview-wrapper {
    width: 100%;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 6px;
    background-color: #f9fafb;
}

.file-preview {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.file-preview.video {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.file-preview.audio {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.file-preview.document {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.file-name {
    font-size: 0.875rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
@endpush
