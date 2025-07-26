@extends('layouts/layoutMaster')

@section('title', $collaborator->name . ' - Media')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Collaborator Sidebar -->
    @include('collaborator.partials.sidebar')
    <!--/ Collaborator Sidebar -->

    <!-- Collaborator Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        @include('collaborator.partials.tabs')

        <!-- Media Management -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Media Management</h5>
            </div>
            <div class="card-body">
                @can('collaborator.edit')
                <!-- Upload Zone -->
                <div class="drop-zone mb-4" id="dropZone">
                    <div class="mb-3">
                        <i class="ti ti-upload ti-lg text-muted"></i>
                    </div>
                    <h6 class="mb-2">Drag files here or click to select</h6>
                    <p class="text-muted mb-3">Supports images, videos, audio and documents</p>
                    <div class="file-input-wrapper">
                        <input type="file" id="fileInput" class="file-input" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx">
                        <label for="fileInput" class="file-input-label">
                            <i class="ti ti-plus me-1"></i>Select Files
                        </label>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div class="upload-progress d-none" id="uploadProgress">
                    <div class="d-flex align-items-center mb-2">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <span class="fw-medium">Uploading files...</span>
                                <span class="text-muted" id="uploadCount">0/0</span>
                            </div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
                    </div>
                </div>
                @endcan

                <!-- Media List -->
                <div id="mediaList">
                    @if($collaborator->getMedia('media')->count() > 0)
                        @foreach($collaborator->getMedia('media') as $media)
                            <div class="media-item" data-media-id="{{ $media->id }}">
                                <div class="d-flex align-items-center">
                                    <div class="media-preview-wrapper">
                                        @if($media->mime_type && str_starts_with($media->mime_type, 'image/'))
                                            <img src="{{ $media->getUrl() }}" alt="{{ $media->name }}" class="media-preview">
                                        @elseif($media->mime_type && str_starts_with($media->mime_type, 'video/'))
                                            <div class="media-preview video">
                                                <i class="ti ti-video ti-lg"></i>
                                            </div>
                                        @elseif($media->mime_type && str_starts_with($media->mime_type, 'audio/'))
                                            <div class="media-preview audio">
                                                <i class="ti ti-music ti-lg"></i>
                                            </div>
                                        @else
                                            <div class="media-preview document">
                                                <i class="ti ti-file ti-lg"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <h6 class="mb-0 media-name" data-media-id="{{ $media->id }}">{{ $media->name }}</h6>
                                            <span class="badge bg-secondary ms-2">{{ strtoupper($media->mime_type) }}</span>
                                        </div>
                                        <p class="text-muted mb-1">{{ number_format($media->size / 1024, 2) }} KB</p>
                                        <small class="text-muted">Uploaded on {{ $media->created_at->format('d/m/Y H:i') }}</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ $media->getUrl() }}" target="_blank" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        @can('collaborator.edit')
                                        <button class="btn btn-sm btn-outline-secondary edit-media-name" data-media-id="{{ $media->id }}" title="Edit name">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-media" data-media-id="{{ $media->id }}" title="Delete">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <!-- Empty State -->
                        <div class="text-center py-5">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-secondary">
                                    <i class="ti ti-photo ti-md"></i>
                                </span>
                            </div>
                            <h5 class="mb-2">No media files</h5>
                            <p class="mb-0 text-muted">Upload files by dragging them or using the select button.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Valoration Modal -->
@include('collaborator.partials.valoration-modal')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('progressBar');
    const uploadCount = document.getElementById('uploadCount');
    const mediaList = document.getElementById('mediaList');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const collaboratorId = {{ $collaborator->id }};

    // Drag and drop functionality
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
        const files = [...e.target.files];
        if (files.length > 0) {
            uploadFiles(files);
        }
    }

    function uploadFiles(files) {
        const totalFiles = files.length;
        let uploadedFiles = 0;

        uploadProgress.classList.remove('d-none');
        updateProgress(0, totalFiles);

        files.forEach((file, index) => {
            const formData = new FormData();
            formData.append('media', file);
            formData.append('_token', csrfToken);

            console.log(`Uploading file ${index + 1}/${totalFiles}:`, file.name);

            fetch(`/collaborator/${collaboratorId}/media`, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log(`Response for ${file.name}:`, response.status, response.statusText);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log(`Data received for ${file.name}:`, data);
                uploadedFiles++;
                updateProgress(uploadedFiles, totalFiles);

                if (data.success) {
                    addMediaItem(data.media);
                    console.log(`File ${file.name} uploaded successfully`);
                } else {
                    console.error(`Error uploading ${file.name}:`, data.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Error',
                        text: data.message || 'Unknown error uploading file'
                    });
                }

                if (uploadedFiles === totalFiles) {
                    setTimeout(() => {
                        uploadProgress.classList.add('d-none');
                        updateProgress(0, 0);
                        console.log('Upload process completed');
                    }, 2000);
                }
            })
            .catch(error => {
                console.error(`Error in fetch for ${file.name}:`, error);
                uploadedFiles++;
                updateProgress(uploadedFiles, totalFiles);

                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: `Error uploading ${file.name}: ${error.message}`
                });

                if (uploadedFiles === totalFiles) {
                    setTimeout(() => {
                        uploadProgress.classList.add('d-none');
                        updateProgress(0, 0);
                    }, 2000);
                }
            });
        });

        // Clear file input
        fileInput.value = '';
    }

    function updateProgress(current, total) {
        const percentage = total > 0 ? (current / total) * 100 : 0;
        progressBar.style.width = percentage + '%';
        uploadCount.textContent = `${current}/${total}`;
    }

    function addMediaItem(media) {
        const mediaItem = createMediaItemElement(media);

        // Remove empty state if it exists
        const emptyState = mediaList.querySelector('.text-center');
        if (emptyState) {
            emptyState.remove();
        }

        mediaList.insertBefore(mediaItem, mediaList.firstChild);
    }

    function createMediaItemElement(media) {
        const div = document.createElement('div');
        div.className = 'media-item';
        div.setAttribute('data-media-id', media.id);

        const previewClass = getPreviewClass(media.mime_type);
        const previewIcon = getPreviewIcon(media.mime_type);
        const previewContent = media.mime_type && media.mime_type.startsWith('image/')
            ? `<img src="${media.url}" alt="${media.name}" class="media-preview">`
            : `<div class="media-preview ${previewClass}"><i class="${previewIcon}"></i></div>`;

        div.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="media-preview-wrapper">
                    ${previewContent}
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-1">
                        <h6 class="mb-0 media-name" data-media-id="${media.id}">${media.name}</h6>
                        <span class="badge bg-secondary ms-2">${media.mime_type.toUpperCase()}</span>
                    </div>
                    <p class="text-muted mb-1">${(media.size / 1024).toFixed(2)} KB</p>
                    <small class="text-muted">Uploaded now</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="${media.url}" target="_blank" class="btn btn-sm btn-outline-primary" title="View">
                        <i class="ti ti-eye"></i>
                    </a>
                    @can('collaborator.edit')
                    <button class="btn btn-sm btn-outline-secondary edit-media-name" data-media-id="${media.id}" title="Edit name">
                        <i class="ti ti-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-media" data-media-id="${media.id}" title="Delete">
                        <i class="ti ti-trash"></i>
                    </button>
                    @endcan
                </div>
            </div>
        `;

        return div;
    }

    function getPreviewClass(mimeType) {
        if (mimeType && mimeType.startsWith('video/')) return 'video';
        if (mimeType && mimeType.startsWith('audio/')) return 'audio';
        return 'document';
    }

    function getPreviewIcon(mimeType) {
        if (mimeType && mimeType.startsWith('video/')) return 'ti ti-video ti-lg';
        if (mimeType && mimeType.startsWith('audio/')) return 'ti ti-music ti-lg';
        return 'ti ti-file ti-lg';
    }

    // Edit media name
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-media-name')) {
            const button = e.target.closest('.edit-media-name');
            const mediaId = button.getAttribute('data-media-id');
            const nameElement = document.querySelector(`.media-name[data-media-id="${mediaId}"]`);
            const currentName = nameElement.textContent;

            Swal.fire({
                title: 'Edit file name',
                input: 'text',
                inputValue: currentName,
                showCancelButton: true,
                confirmButtonText: 'Save',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Name cannot be empty';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    updateMediaName(mediaId, result.value);
                }
            });
        }
    });

    function updateMediaName(mediaId, newName) {
        fetch('{{ route("collaborator.media.update", ["id" => $collaborator->id, "mediaId" => ":mediaId"]) }}'.replace(':mediaId', mediaId), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ name: newName })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const nameElement = document.querySelector(`.media-name[data-media-id="${mediaId}"]`);
                nameElement.textContent = newName;

                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Name updated successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to update name'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to update name'
            });
        });
    }

    // Delete media
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-media')) {
            const button = e.target.closest('.delete-media');
            const mediaId = button.getAttribute('data-media-id');
            const mediaItem = button.closest('.media-item');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This action cannot be undone',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteMedia(mediaId, mediaItem);
                }
            });
        }
    });

    function deleteMedia(mediaId, mediaItem) {
        fetch('{{ route("collaborator.media.destroy", ["id" => $collaborator->id, "mediaId" => ":mediaId"]) }}'.replace(':mediaId', mediaId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mediaItem.remove();

                // Check if no more media items
                const remainingItems = mediaList.querySelectorAll('.media-item');
                if (remainingItems.length === 0) {
                    mediaList.innerHTML = `
                        <div class="text-center py-5">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-secondary">
                                    <i class="ti ti-photo ti-md"></i>
                                </span>
                            </div>
                            <h5 class="mb-2">No media files</h5>
                            <p class="mb-0 text-muted">Upload files by dragging them or using the select button.</p>
                        </div>
                    `;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'File deleted successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to delete file'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to delete file'
            });
        });
    }
});
</script>
@endpush

<style>
.drop-zone {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    background-color: #f9fafb;
}

.drop-zone.dragover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

.media-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.media-item:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.media-preview {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    margin-right: 1rem;
}

.media-preview.audio {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.media-preview.video {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.media-preview.document {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.upload-progress {
    display: none;
    margin-top: 1rem;
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
</style>
