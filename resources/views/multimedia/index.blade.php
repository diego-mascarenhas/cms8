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
        <button type="button" class="btn btn-primary" id="toggleUploadZone">
            <i class="ti ti-plus me-1"></i> {{ __('app.Add Media') }}
        </button>
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
                        <option value="{{ $status->value }}" {{ (request('status') !== null ? request('status') == $status->value : $status->value == 2) ? 'selected' : '' }}>
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
                <select id="filter_tag" class="form-select" multiple>
                    {{-- Options will be loaded via AJAX --}}
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter_gallery">{{ __('Galleries') }}</label>
                <select id="filter_gallery" class="form-select" multiple>
                    {{-- Options will be loaded via AJAX --}}
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-end">
                <div class="flex-grow-1">
                    <label class="form-label" for="filter_search">{{ __('Search') }}</label>
                    <input type="text" id="filter_search" class="form-control" placeholder="{{ __('Search by title or description...') }}">
                </div>
                <button type="button" class="btn btn-icon btn-outline-secondary" id="resetFilters" title="{{ __('Reset') }}">
                    <i class="ti ti-refresh"></i>
                </button>
                @if(request('gallery_tag_id') && auth()->user()->can('create', \App\Models\Multimedia::class))
                    <a href="{{ route('multimedia.gallery', request('gallery_tag_id')) }}" class="btn btn-outline-primary">
                        <i class="ti ti-sort-ascending me-1"></i> {{ __('Order Gallery') }}
                    </a>
                @endif
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="viewMode" id="viewModeCards" value="cards" checked>
                    <label class="btn btn-outline-primary" for="viewModeCards" title="{{ __('Cards View') }}">
                        <i class="ti ti-layout-grid"></i>
                    </label>
                    <input type="radio" class="btn-check" name="viewMode" id="viewModeTable" value="table">
                    <label class="btn btn-outline-primary" for="viewModeTable" title="{{ __('Table View') }}">
                        <i class="ti ti-table"></i>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

@can('create', \App\Models\Multimedia::class)
<div class="card mb-4 d-none" id="uploadZoneCard">
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

<!-- Table View -->
<div class="card d-none" id="tableView">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>

<!-- Cards View -->
<div class="card" id="cardsView">
    <div class="card-body">
        <div class="row gy-4 mb-4" id="multimediaCardsContainer">
            <!-- Cards will be loaded here via AJAX -->
            <div class="col-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">{{ __('app.Loading...') }}</span>
                </div>
            </div>
        </div>
        <div id="multimediaCardsPagination" class="d-flex justify-content-center">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>

<!-- Edit Multimedia Offcanvas (Livewire) -->
@can('create', \App\Models\Multimedia::class)
<div id="multimedia-edit-wrapper">
    @livewire('multimedia.edit-multimedia', key('edit-multimedia-offcanvas'))
</div>
@endcan
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script>
        // Open edit multimedia offcanvas (Livewire) - Global function
        window.openEditMultimedia = function(id) {
            // Simple approach: Just dispatch the event
            if (typeof Livewire !== 'undefined') {
                try {
                    Livewire.dispatch('openEditMultimedia', { id: id });
                } catch (error) {
                    console.error('Error dispatching event:', error);
                    // Fallback: try to find component and call directly
                    setTimeout(function() {
                        try {
                            const allComponents = Livewire.all();
                            if (allComponents) {
                                for (let key in allComponents) {
                                    if (allComponents.hasOwnProperty(key)) {
                                        const component = allComponents[key];
                                        if (component && component.__instance && component.__instance.name === 'multimedia.edit-multimedia') {
                                            component.call('loadMultimedia', id);
                                            return;
                                        }
                                    }
                                }
                            }
                        } catch (e) {
                            console.error('Error finding component:', e);
                        }
                    }, 100);
                }
            } else {
                console.error('Livewire is not defined');
                // Wait for Livewire to be available
                document.addEventListener('livewire:initialized', function() {
                    Livewire.dispatch('openEditMultimedia', { id: id });
                }, { once: true });
            }
        };

        // Listen for Livewire events
        document.addEventListener('livewire:init', () => {
            // Listen for multimedia updates
            Livewire.on('multimedia:updated', () => {
                // Reload cards and DataTable
                loadMultimediaCards(1);
                if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                    $('#multimedia-table').DataTable().ajax.reload(null, false);
                }
            });

            // Listen for multimedia deletions
            Livewire.on('multimedia:deleted', () => {
                // Reload cards and DataTable
                loadMultimediaCards(1);
                if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                    $('#multimedia-table').DataTable().ajax.reload(null, false);
                }
            });
        });

        $(document).ready(function () {
            // Initialize simple selects (status, visibility, category, type)
            $('#filter_status, #filter_visibility, #filter_category, #filter_type').select2({
                width: '100%',
                minimumResultsForSearch: Infinity
            });

            // Set default filter to Active (value 2) if no filter is applied
            if (!{{ request('status') !== null ? 'true' : 'false' }}) {
                $('#filter_status').val('2').trigger('change');
            }

            // Initialize tags selector with AJAX (same as sidebar)
            $('#filter_tag').select2({
                width: '100%',
                tags: true,
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-no-jump',
                language: {
                    searching: function() { return ''; }
                },
                ajax: {
                    url: '{{ route("tags.search") }}',
                    dataType: 'json',
                    delay: 150,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            type: 'general'
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function(tag) {
                                return { id: tag.name, text: tag.name };
                            })
                        };
                    },
                    cache: true
                },
                createTag: function (params) {
                    const term = $.trim(params.term);
                    if (term === '') return null;
                    return { id: term, text: term, newTag: true };
                }
            });

            // Initialize galleries selector with AJAX (same as sidebar)
            $('#filter_gallery').select2({
                width: '100%',
                tags: true,
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-no-jump',
                language: {
                    searching: function() { return ''; }
                },
                ajax: {
                    url: '{{ route("tags.search") }}',
                    dataType: 'json',
                    delay: 150,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            type: 'gallery'
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function(tag) {
                                return { id: tag.name, text: tag.name };
                            })
                        };
                    },
                    cache: true
                },
                createTag: function (params) {
                    const term = $.trim(params.term);
                    if (term === '') return null;
                    return { id: term, text: term, newTag: true };
                }
            });

            // Ensure Livewire offcanvas is in body (will be handled by Livewire)

            // Toggle upload zone
            $('#toggleUploadZone').on('click', function() {
                const uploadZone = $('#uploadZoneCard');
                const icon = $(this).find('i');

                if (uploadZone.hasClass('d-none')) {
                    uploadZone.removeClass('d-none').hide().slideDown(300);
                    icon.removeClass('ti-plus').addClass('ti-minus');
                } else {
                    uploadZone.slideUp(300, function() {
                        $(this).addClass('d-none');
                    });
                    icon.removeClass('ti-minus').addClass('ti-plus');
                }
            });

            // View mode toggle
            $('input[name="viewMode"]').on('change', function() {
                const viewMode = $(this).val();
                if (viewMode === 'table') {
                    $('#tableView').removeClass('d-none');
                    $('#cardsView').addClass('d-none');
                } else {
                    $('#tableView').addClass('d-none');
                    $('#cardsView').removeClass('d-none');
                    loadMultimediaCards();
                }
            });

            // Load cards by default
            if ($('#viewModeCards').is(':checked')) {
                loadMultimediaCards();
            }

            // Load cards when filters change (only if cards view is active)
            $('#filter_status, #filter_visibility, #filter_category, #filter_tag, #filter_gallery, #filter_type, #filter_search')
                .on('change keyup', function(e) {
                    // For search input, add debounce
                    if ($(this).attr('id') === 'filter_search') {
                        clearTimeout(window.searchTimeout);
                        window.searchTimeout = setTimeout(function() {
                            if ($('#viewModeCards').is(':checked')) {
                                loadMultimediaCards();
                            }
                        }, 500);
                    } else {
                        if ($('#viewModeCards').is(':checked')) {
                            loadMultimediaCards();
                        }
                    }
                });

            function bindFilters(table) {
                if (!table) {
                    return;
                }

                $('#filter_status, #filter_visibility, #filter_category, #filter_tag, #filter_gallery, #filter_type, #filter_search')
                    .off('change.multimedia keyup.multimedia')
                    .on('change.multimedia keyup.multimedia', function () {
                        // For search input, add debounce
                        if ($(this).attr('id') === 'filter_search') {
                            clearTimeout(window.searchTimeout);
                            window.searchTimeout = setTimeout(function() {
                                table.ajax.reload();
                            }, 500);
                        } else {
                            table.ajax.reload();
                        }
                    });

                $('#resetFilters')
                    .off('click.multimedia')
                    .on('click.multimedia', function () {
                        $('#filter_status, #filter_visibility, #filter_category, #filter_type, #filter_search')
                            .val(null)
                            .trigger('change');
                        // Clear multiple selects properly
                        $('#filter_tag, #filter_gallery')
                            .val(null)
                            .trigger('change');
                    });

                table.off('preXhr.dt.multimedia').on('preXhr.dt.multimedia', function (e, settings, data) {
                    data.status = $('#filter_status').val();
                    data.visibility = $('#filter_visibility').val();
                    data.category_id = $('#filter_category').val();
                    // Send arrays for tags and galleries
                    data.tags = $('#filter_tag').val() || [];
                    data.galleries = $('#filter_gallery').val() || [];
                    data.type = $('#filter_type').val();
                    data.search = $('#filter_search').val();
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

                // Get current filter values
                const filterStatus = $('#filter_status').val() || '0'; // Default to UNCLASSIFIED if empty
                const filterVisibility = $('#filter_visibility').val() || '{{ \App\Enums\MultimediaVisibility::PUBLIC->value }}';
                const filterCategory = $('#filter_category').val() || '';

                // Get selected tags and filter invalid values
                let filterTags = $('#filter_tag').val() || [];
                const invalidValues = ['Todos', 'todos', 'all', 'All', '', 'null', 'undefined'];
                filterTags = filterTags.filter(tag => {
                    if (!tag) return false;
                    const trimmed = tag.trim();
                    if (invalidValues.includes(trimmed)) return false;
                    if (trimmed.startsWith('{') && trimmed.includes('Todos')) return false;
                    return true;
                });

                // Get selected galleries and filter invalid values
                let filterGalleries = $('#filter_gallery').val() || [];
                filterGalleries = filterGalleries.filter(gallery => {
                    if (!gallery) return false;
                    const trimmed = gallery.trim();
                    if (invalidValues.includes(trimmed)) return false;
                    if (trimmed.startsWith('{') && trimmed.includes('Todos')) return false;
                    return true;
                });

                // Send all files in a single request
                const formData = new FormData();
                files.forEach((file) => {
                    formData.append('files[]', file);
                });
                formData.append('_token', csrfToken);
                formData.append('status', filterStatus);
                formData.append('visibility', filterVisibility);

                // Add category if selected
                if (filterCategory) {
                    formData.append('category_id', filterCategory);
                }

                // Add tags if selected (as array)
                filterTags.forEach((tag) => {
                    formData.append('tags[]', tag);
                });

                // Add galleries if selected (as array)
                filterGalleries.forEach((gallery) => {
                    formData.append('galleries[]', gallery);
                });

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

                                // Reload current view
                                if ($('#viewModeCards').is(':checked')) {
                                    loadMultimediaCards();
                                } else {
                                    if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                                        $('#multimedia-table').DataTable().ajax.reload(null, false);
                                    }
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

        // Load multimedia cards
        function loadMultimediaCards(page = 1) {
            const container = $('#multimediaCardsContainer');
            const pagination = $('#multimediaCardsPagination');

            container.html(`
                <div class="col-12 text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('app.Loading...') }}</span>
                    </div>
                </div>
            `);

            // Get filter values - handle arrays for tags and galleries
            const filterTags = $('#filter_tag').val() || [];
            const filterGalleries = $('#filter_gallery').val() || [];
            const filterSearch = $('#filter_search').val() || '';

            const filters = {
                status: $('#filter_status').val(),
                visibility: $('#filter_visibility').val(),
                category_id: $('#filter_category').val(),
                type: $('#filter_type').val(),
                search: filterSearch,
                view: 'cards',
                page: page,
                per_page: 12
            };

            // Add array parameters properly
            filterTags.forEach((tag, index) => {
                filters[`tags[${index}]`] = tag;
            });
            filterGalleries.forEach((gallery, index) => {
                filters[`galleries[${index}]`] = gallery;
            });

            const queryString = $.param(filters);

            fetch(`{{ route('multimedia.index') }}?${queryString}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.cards && data.cards.length > 0) {
                    renderCards(data.cards);
                    renderPagination(data.pagination);
                } else {
                    container.html(`
                        <div class="col-12 text-center">
                            <p class="text-muted">{{ __('app.No multimedia found') }}</p>
                        </div>
                    `);
                    pagination.html('');
                }
            })
            .catch(error => {
                console.error('Error loading cards:', error);
                container.html(`
                    <div class="col-12 text-center">
                        <p class="text-danger">{{ __('app.Error loading multimedia') }}</p>
                    </div>
                `);
            });
        }

        // Render cards
        function renderCards(cards) {
            const container = $('#multimediaCardsContainer');
            container.html('');

            cards.forEach(card => {
                const statusClass = card.status_value === 2 ? 'bg-label-success' : card.status_value === 1 ? 'bg-label-warning' : 'bg-label-secondary';
                const visibilityClass = card.visibility_value === 2 ? 'bg-label-info' : 'bg-label-secondary';

                const tagsHtml = card.tags && card.tags.length > 0
                    ? card.tags.slice(0, 3).map(tag => `<span class="badge bg-label-info me-1">${tag}</span>`).join('')
                    : '<span class="text-muted">{{ __("app.No tags") }}</span>';

                // Make preview clickable if user can update
                const previewClickable = card.can_update
                    ? `style="cursor: pointer;" onclick="openEditMultimedia(${card.id})"`
                    : '';

                // Only show image preview for actual images, use icon for everything else
                const previewHtml = card.type === 'image' && card.preview_url
                    ? `<img class="img-fluid rounded" src="${card.preview_url}" alt="${card.title}" style="max-height: 200px; object-fit: cover; width: 100%;" ${previewClickable}>`
                    : `<div class="d-flex align-items-center justify-content-center bg-label-secondary rounded" style="height: 200px; ${card.can_update ? 'cursor: pointer;' : ''}" ${previewClickable}>
                        <i class="${card.icon}" style="font-size: 120px; color: #a8aaae;"></i>
                    </div>`;

                const cardHtml = `
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="card p-2 h-100 shadow-none border">
                            <div class="rounded-2 text-center mb-3">
                                ${previewHtml}
                            </div>
                            <div class="card-body p-3 pt-2">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge ${statusClass}">${card.status || '{{ __("app.Unknown") }}'}</span>
                                    <span class="badge ${visibilityClass}">${card.visibility || '{{ __("app.Unknown") }}'}</span>
                                </div>
                                <h5 class="mb-2">${card.title || '{{ __("app.Untitled") }}'}</h5>
                                <p class="text-muted mb-2 small">${card.description ? (card.description.length > 80 ? card.description.substring(0, 80) + '...' : card.description) : ''}</p>
                                <div class="mb-2">
                                    <small class="text-muted"><i class="ti ti-tag me-1"></i>${tagsHtml}</small>
                                </div>
                                ${card.category ? `<div class="mb-2"><small class="text-muted"><i class="ti ti-folder me-1"></i>${card.category}</small></div>` : ''}
                                <div class="mb-0">
                                    <small class="text-muted"><i class="ti ti-calendar me-1"></i>${card.created_at || ''}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                container.append(cardHtml);
            });
        }

        // Render pagination
        function renderPagination(pagination) {
            const paginationEl = $('#multimediaCardsPagination');
            paginationEl.html('');

            if (pagination.last_page <= 1) {
                return;
            }

            let paginationHtml = '<nav><ul class="pagination">';

            // Previous button
            paginationHtml += `
                <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadMultimediaCards(${pagination.current_page - 1}); return false;">
                        <i class="ti ti-chevron-left"></i>
                    </a>
                </li>
            `;

            // Page numbers
            for (let i = 1; i <= pagination.last_page; i++) {
                if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                    paginationHtml += `
                        <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="loadMultimediaCards(${i}); return false;">${i}</a>
                        </li>
                    `;
                } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                    paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            // Next button
            paginationHtml += `
                <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadMultimediaCards(${pagination.current_page + 1}); return false;">
                        <i class="ti ti-chevron-right"></i>
                    </a>
                </li>
            `;

            paginationHtml += '</ul></nav>';
            paginationEl.html(paginationHtml);
        }

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
                        // Reload current view
                        if ($('#viewModeCards').is(':checked')) {
                            loadMultimediaCards();
                        } else {
                            if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                                $('#multimedia-table').DataTable().ajax.reload();
                            }
                        }
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

        /* Prevent Select2 dropdown jump */
        .select2-dropdown-no-jump {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        .select2-dropdown-no-jump .select2-results {
            padding-top: 0 !important;
        }
        
        .select2-dropdown-no-jump .select2-results__options {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }
        
        .select2-container--open .select2-dropdown--below {
            margin-top: -1px;
        }
    </style>
@endpush
