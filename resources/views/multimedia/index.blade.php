@extends('layouts/layoutMaster')

@section('title', __('Multimedia'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('app.Multimedia') }}</h4>
        <p class="text-muted">{{ __('app.Manage media files and galleries') }}</p>
    </div>
    @can('create', \App\Models\Multimedia::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('multimedia.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('app.Add Media') }}
        </a>
    </div>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="filter_status">{{ __('app.Status') }}</label>
                <select id="filter_status" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_visibility">{{ __('app.Visibility') }}</label>
                <select id="filter_visibility" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($visibilityOptions as $visibility)
                        <option value="{{ $visibility->value }}" {{ request('visibility') == $visibility->value ? 'selected' : '' }}>
                            {{ $visibility->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_category">{{ __('app.Category') }}</label>
                <select id="filter_category" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_type">{{ __('app.Type') }}</label>
                <select id="filter_type" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>{{ __('app.Image') }}</option>
                    <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>{{ __('app.Video') }}</option>
                    <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>{{ __('app.Audio') }}</option>
                    <option value="document" {{ request('type') === 'document' ? 'selected' : '' }}>{{ __('app.Document') }}</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter_tag">{{ __('app.Tags') }}</label>
                <select id="filter_tag" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter_gallery">{{ __('app.Gallery') }}</label>
                <select id="filter_gallery" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($galleryTags as $tag)
                        <option value="{{ $tag->id }}" {{ request('gallery_tag_id') == $tag->id ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                    <i class="ti ti-refresh me-1"></i> {{ __('app.Reset') }}
                </button>
                @if(request('gallery_tag_id') && auth()->user()->can('create', \App\Models\Multimedia::class))
                    <a href="{{ route('multimedia.gallery', request('gallery_tag_id')) }}" class="btn btn-outline-primary">
                        <i class="ti ti-sort-ascending me-1"></i> {{ __('app.Order Gallery') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

@can('create', \App\Models\Multimedia::class)
<div class="card mb-4">
    <div class="card-body">
        <!-- Drop Zone -->
        <div class="drop-zone" id="dropZone">
            <div class="mb-3">
                <i class="ti ti-upload ti-lg text-muted"></i>
            </div>
            <h6 class="mb-2">{{ __('app.Drag files here or click to select') }}</h6>
            <p class="text-muted mb-3">{{ __('app.Supports images, videos, audio and documents') }}</p>
            <div class="file-input-wrapper">
                <input type="file" id="fileInput" class="file-input" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.zip,.rar">
                <label for="fileInput" class="file-input-label">
                    <i class="ti ti-plus me-1"></i>{{ __('app.Select Files') }}
                </label>
            </div>
        </div>

        <!-- Upload Progress -->
        <div class="upload-progress d-none" id="uploadProgress">
            <div class="d-flex align-items-center mb-2">
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <span class="fw-medium">{{ __('app.Uploading files...') }}</span>
                        <span class="text-muted" id="uploadCount">0/0</span>
                    </div>
                </div>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
            </div>
        </div>
    </div>
</div>
@endcan

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script>
        $(document).ready(function () {
            $('.select2').select2({ width: '100%' });

            function bindFilters(table) {
                if (!table) {
                    return;
                }

                $('#filter_status, #filter_visibility, #filter_category, #filter_tag, #filter_gallery, #filter_type')
                    .off('change.multimedia')
                    .on('change.multimedia', function () {
                        table.ajax.reload();
                    });

                $('#resetFilters')
                    .off('click.multimedia')
                    .on('click.multimedia', function () {
                        $('#filter_status, #filter_visibility, #filter_category, #filter_tag, #filter_gallery, #filter_type')
                            .val(null)
                            .trigger('change');
                    });

                table.off('preXhr.dt.multimedia').on('preXhr.dt.multimedia', function (e, settings, data) {
                    data.status = $('#filter_status').val();
                    data.visibility = $('#filter_visibility').val();
                    data.category_id = $('#filter_category').val();
                    data.tag_id = $('#filter_tag').val();
                    data.gallery_tag_id = $('#filter_gallery').val();
                    data.type = $('#filter_type').val();
                });
            }

            if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                bindFilters($('#multimedia-table').DataTable());
            } else {
                $('#multimedia-table').on('init.dt', function () {
                    bindFilters($('#multimedia-table').DataTable());
                });
            }
        });

        // Drag and drop functionality
        @can('create', \App\Models\Multimedia::class)
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        const uploadCount = document.getElementById('uploadCount');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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
                    uploadFiles(files);
                }
            }

            function uploadFiles(files) {
                const totalFiles = files.length;
                let uploadedFiles = 0;
                let failedFiles = 0;

                uploadProgress.classList.remove('d-none');
                updateProgress(0, totalFiles);

                // Send all files in a single request
                const formData = new FormData();
                files.forEach((file) => {
                    formData.append('files[]', file);
                });
                formData.append('_token', csrfToken);
                formData.append('status', '0'); // UNCLASSIFIED
                formData.append('visibility', '{{ \App\Enums\MultimediaVisibility::PUBLIC->value }}');

                // Add AJAX header to get JSON response
                fetch('{{ route("multimedia.store") }}', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            try {
                                const json = JSON.parse(text);
                                throw new Error(json.message || `HTTP ${response.status}`);
                            } catch (e) {
                                throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    uploadedFiles = totalFiles;
                    updateProgress(uploadedFiles, totalFiles);

                    setTimeout(() => {
                        uploadProgress.classList.add('d-none');
                        updateProgress(0, 0);
                        
                        // Reload DataTable
                        if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                            $('#multimedia-table').DataTable().ajax.reload(null, false);
                        }
                        
                        fileInput.value = '';
                        
                        Swal.fire({
                            icon: 'success',
                            title: '{{ __("app.Uploaded") }}',
                            text: data.message || '{{ __("app.Files uploaded successfully") }}',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }, 500);
                })
                .catch(error => {
                    console.error('Error uploading files:', error);
                    failedFiles = totalFiles;
                    
                    setTimeout(() => {
                        uploadProgress.classList.add('d-none');
                        updateProgress(0, 0);
                    }, 2000);

                    Swal.fire({
                        icon: 'error',
                        title: '{{ __("app.Error") }}',
                        text: error.message || '{{ __("app.Error uploading files") }}'
                    });
                });
            }

            function updateProgress(current, total) {
                const percentage = total > 0 ? (current / total) * 100 : 0;
                progressBar.style.width = percentage + '%';
                uploadCount.textContent = `${current}/${total}`;
            }
        }
        @endcan

        function deleteRecord(id) {
            Swal.fire({
                title: '{{ __("app.Are you sure you want to delete this record?") }}',
                text: '{{ __("app.This action cannot be undone") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: '{{ __("app.Yes, delete") }}',
                cancelButtonText: '{{ __("app.Cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('multimedia.destroy', ['multimedia' => ':ID']) }}".replace(':ID', id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).then(response => response.json()).then(data => {
                        Swal.fire({
                            icon: 'success',
                            title: '{{ __("app.Deleted") }}',
                            text: data.success
                        });
                        $('#multimedia-table').DataTable().ajax.reload();
                    }).catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("app.Error") }}',
                            text: '{{ __("app.Failed to delete multimedia.") }}'
                        });
                    });
                }
            });
        }
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

        .upload-progress {
            margin-top: 1rem;
        }
    </style>
@endpush
