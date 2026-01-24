<div class="offcanvas offcanvas-end multimedia-edit-sidebar" tabindex="-1" id="multimediaEditOffcanvas" data-bs-backdrop="true" data-bs-scroll="true" style="visibility: hidden;" wire:ignore.self>
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">{{ __('app.Edit Media') }}</h5>
        <button type="button" class="btn-close" wire:click="close" onclick="event.stopPropagation();" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form wire:submit="update">
            <div class="mb-3">
                <label class="form-label" for="title">{{ __('app.Title') }}</label>
                <input type="text" id="title" name="title" wire:model.blur="title" class="form-control @error('title') is-invalid @enderror" required>
                @error('title')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">{{ __('app.Description') }}</label>
                <textarea id="description" name="description" wire:model.blur="description" class="form-control @error('description') is-invalid @enderror" rows="3"></textarea>
                @error('description')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="status">{{ __('app.Status') }}</label>
                    <select id="status" name="status" wire:model.blur="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach($statusOptions as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="visibility">{{ __('app.Visibility') }}</label>
                    <select id="visibility" name="visibility" wire:model.blur="visibility" class="form-select @error('visibility') is-invalid @enderror">
                        @foreach($visibilityOptions as $visibility)
                            <option value="{{ $visibility->value }}">{{ $visibility->label() }}</option>
                        @endforeach
                    </select>
                    @error('visibility')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="categoryId">{{ __('app.Category') }}</label>
                <select id="categoryId" name="categoryId" wire:model.blur="categoryId" class="form-select @error('categoryId') is-invalid @enderror">
                    <option value="">{{ __('app.No category') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('categoryId')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="tags">{{ __('app.Tags') }}</label>
                <select id="tags" name="tags[]" wire:model="tags" class="form-select @error('tags') is-invalid @enderror" multiple>
                    {{-- Options will be added dynamically by Select2 and Livewire --}}
                </select>
                @error('tags')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="galleries">{{ __('app.Galleries') }}</label>
                <select id="galleries" name="galleries[]" wire:model="galleries" class="form-select @error('galleries') is-invalid @enderror" multiple>
                    {{-- Options will be added dynamically by Select2 and Livewire --}}
                </select>
                @error('galleries')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="media">{{ __('app.Replace File') }}</label>
                <input type="file" id="media" name="media" wire:model="media" class="form-control @error('media') is-invalid @enderror">
                @error('media')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @if($media)
                    <div class="form-text">{{ __('app.Uploading...') }}</div>
                @endif
            </div>

            <div class="mb-3">
                <label class="form-label" for="poster">{{ __('app.Poster Image (Optional)') }}</label>
                <input type="file" id="poster" name="poster" wire:model="poster" class="form-control @error('poster') is-invalid @enderror" accept="image/*">
                @error('poster')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @if($poster)
                    <div class="form-text">{{ __('app.Uploading...') }}</div>
                @endif
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="ti ti-check me-1"></i>{{ __('app.Save') }}</span>
                    <span wire:loading>{{ __('app.Saving...') }}</span>
                </button>
                <button type="button" class="btn btn-label-secondary" wire:click="close">
                    {{ __('app.Cancel') }}
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Ensure offcanvas is in body on mount
    document.addEventListener('livewire:initialized', () => {
        const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
        if (offcanvasEl && offcanvasEl.parentElement !== document.body) {
            document.body.appendChild(offcanvasEl);
        }
        
        // Listen for offcanvas show/hide events
        Livewire.on('offcanvas:show', () => {
            const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
            if (offcanvasEl) {
                // Ensure offcanvas is in body
                if (offcanvasEl.parentElement !== document.body) {
                    document.body.appendChild(offcanvasEl);
                }
                
                // Get or create offcanvas instance
                let offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                if (!offcanvas) {
                    offcanvas = new bootstrap.Offcanvas(offcanvasEl, {
                        backdrop: true,
                        scroll: true
                    });
                }
                
                // Store offcanvas instance and flag
                offcanvasEl._livewireOffcanvas = offcanvas;
                offcanvasEl._isLivewireManaged = true;
                offcanvasEl._allowHide = false;
                
                // Prevent Bootstrap from closing during Livewire updates
                // Remove any existing listener first
                const existingHandler = offcanvasEl._hideHandler;
                if (existingHandler) {
                    offcanvasEl.removeEventListener('hide.bs.offcanvas', existingHandler);
                }
                
                // Add new handler - use capture phase to catch it early
                offcanvasEl._hideHandler = function(e) {
                    // Only allow hide if explicitly allowed
                    if (!offcanvasEl._allowHide) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                };
                // Use capture phase to intercept before Bootstrap's handlers
                offcanvasEl.addEventListener('hide.bs.offcanvas', offcanvasEl._hideHandler, true);
                
                // Show the offcanvas
                offcanvasEl.style.visibility = 'visible';
                offcanvas.show();
                
                // Ensure it's visible after Bootstrap animation
                setTimeout(() => {
                    if (!offcanvasEl.classList.contains('show')) {
                        offcanvasEl.classList.add('show');
                    }
                    // Ensure backdrop is visible
                    const backdrop = document.querySelector('.offcanvas-backdrop');
                    if (backdrop) {
                        backdrop.style.display = 'block';
                    }
                }, 100);
                
                // Wait for Livewire to update the DOM and offcanvas to be visible
                setTimeout(() => {
                    // Destroy any existing Select2 instances to avoid conflicts
                    if ($('#status').hasClass('select2-hidden-accessible')) {
                        $('#status').select2('destroy');
                    }
                    if ($('#visibility').hasClass('select2-hidden-accessible')) {
                        $('#visibility').select2('destroy');
                    }
                    if ($('#categoryId').hasClass('select2-hidden-accessible')) {
                        $('#categoryId').select2('destroy');
                    }
                    
                    // Initialize Select2 after offcanvas is shown
                    // Initialize status and visibility
                    $('#status, #visibility').select2({
                        width: '100%',
                        dropdownParent: $('#multimediaEditOffcanvas'),
                        minimumResultsForSearch: Infinity
                    });
                    
                    // Initialize category - same pattern as categories-select component
                    // Destroy existing instance first
                    if ($('#categoryId').hasClass('select2-hidden-accessible')) {
                        $('#categoryId').select2('destroy');
                    }
                    
                    $('#categoryId').select2({
                        width: '100%',
                        dropdownParent: $('#multimediaEditOffcanvas'),
                        placeholder: '{{ __("app.No category") }}',
                        allowClear: true,
                        closeOnSelect: false
                    });
                    
                    // Get initial values BEFORE initializing Select2
                    const initialTags = @this.get('tags') || [];
                    const initialGalleries = @this.get('galleries') || [];
                    
                    // Add initial tags to the select element before Select2 initialization
                    if (initialTags.length > 0) {
                        initialTags.forEach(function(tag) {
                            if ($('#tags option[value="' + tag + '"]').length === 0) {
                                const newOption = new Option(tag, tag, true, true);
                                $('#tags').append(newOption);
                            }
                        });
                    }
                    
                    // Add initial galleries to the select element before Select2 initialization
                    if (initialGalleries.length > 0) {
                        initialGalleries.forEach(function(gallery) {
                            if ($('#galleries option[value="' + gallery + '"]').length === 0) {
                                const newOption = new Option(gallery, gallery, true, true);
                                $('#galleries').append(newOption);
                            }
                        });
                    }
                    
                    // Initialize tags with autocomplete and creation - same pattern as category/form.blade.php
                    // First, destroy any existing Select2 instance
                    if ($('#tags').hasClass('select2-hidden-accessible')) {
                        $('#tags').select2('destroy');
                    }
                    
                    const tagsSelect = $('#tags');
                    tagsSelect.select2({
                        width: '100%',
                        dropdownParent: $('#multimediaEditOffcanvas'),
                        tags: true,
                        tokenSeparators: [','],
                        placeholder: '{{ __("app.Search or create tags...") }}',
                        allowClear: false,
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
                    
                    // Hide "No results found" message for tags
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
                    
                    // Ensure initial values are set after Select2 initialization
                    if (initialTags.length > 0) {
                        setTimeout(function() {
                            tagsSelect.val(initialTags).trigger('change');
                        }, 50);
                    }
                    
                    // Initialize galleries with autocomplete and creation - same pattern as category/form.blade.php
                    // First, destroy any existing Select2 instance
                    if ($('#galleries').hasClass('select2-hidden-accessible')) {
                        $('#galleries').select2('destroy');
                    }
                    
                    const galleriesSelect = $('#galleries');
                    galleriesSelect.select2({
                        width: '100%',
                        dropdownParent: $('#multimediaEditOffcanvas'),
                        tags: true,
                        tokenSeparators: [','],
                        placeholder: '{{ __("app.Search or create galleries...") }}',
                        allowClear: false,
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
                                    type: 'gallery'
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
                    
                    // Hide "No results found" message for galleries
                    galleriesSelect.on('select2:open', function() {
                        setTimeout(function() {
                            const dropdown = $('.select2-results');
                            dropdown.find('.select2-results__message').hide();
                        }, 10);
                    });
                    
                    galleriesSelect.on('select2:selecting', function() {
                        $('.select2-results__message').hide();
                    });
                    
                    // Also hide on results update
                    galleriesSelect.on('results:message', function() {
                        setTimeout(function() {
                            $('.select2-results__message').hide();
                        }, 10);
                    });
                    
                    // Ensure initial values are set after Select2 initialization
                    if (initialGalleries.length > 0) {
                        setTimeout(function() {
                            galleriesSelect.val(initialGalleries).trigger('change');
                        }, 50);
                    }
                    
                    // Sync Select2 changes with Livewire
                    $('#status').on('change', function() {
                        @this.set('status', $(this).val());
                    });
                    
                    $('#visibility').on('change', function() {
                        @this.set('visibility', $(this).val());
                    });
                    
                    $('#categoryId').on('change', function() {
                        @this.set('categoryId', $(this).val() || null);
                    });
                    
                    $('#tags').on('change', function() {
                        const values = $(this).val();
                        @this.set('tags', Array.isArray(values) ? values : []);
                    });
                    
                    $('#galleries').on('change', function() {
                        const values = $(this).val();
                        @this.set('galleries', Array.isArray(values) ? values : []);
                    });
                }, 300);
            }
        });
        
        // Watch for show property changes and update Select2 values
        Livewire.hook('morph.updated', function(data) {
            try {
                const component = data.component;
                if (component && component.__instance && component.__instance.name === 'multimedia.edit-multimedia') {
                    const show = component.get('show');
                    const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
                    
                    // If show is false, allow hide and close the offcanvas
                    if (!show && offcanvasEl) {
                        offcanvasEl._allowHide = true;
                        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                        if (offcanvas && offcanvasEl.classList.contains('show')) {
                            offcanvas.hide();
                        } else if (offcanvasEl.classList.contains('show')) {
                            // Fallback: hide manually
                            offcanvasEl.classList.remove('show');
                            offcanvasEl.style.visibility = 'hidden';
                            const backdrop = document.querySelector('.offcanvas-backdrop');
                            if (backdrop) {
                                backdrop.remove();
                            }
                            document.body.classList.remove('offcanvas-backdrop');
                        }
                        return;
                    }
                    
                    if (show && offcanvasEl) {
                        // Check if listener still exists, re-add if needed
                        if (!offcanvasEl._hideHandler || !offcanvasEl._isLivewireManaged) {
                            offcanvasEl._isLivewireManaged = true;
                            offcanvasEl._hideHandler = function(e) {
                                if (!offcanvasEl._allowHide) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    e.stopImmediatePropagation();
                                    return false;
                                }
                            };
                            offcanvasEl.addEventListener('hide.bs.offcanvas', offcanvasEl._hideHandler, true);
                        }
                        // Prevent closing during Livewire updates
                        offcanvasEl._allowHide = false;
                        
                        // Ensure offcanvas stays open during Livewire updates
                        const hadShow = offcanvasEl.classList.contains('show');
                        if (!hadShow) {
                            offcanvasEl.classList.add('show');
                            offcanvasEl.style.visibility = 'visible';
                            // Also ensure Bootstrap instance knows it's shown
                            const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                            if (offcanvas && !offcanvas._isShown) {
                                offcanvas._isShown = true;
                            }
                        }
                        
                        // Ensure backdrop stays visible
                        let backdrop = document.querySelector('.offcanvas-backdrop');
                        if (!backdrop) {
                            backdrop = document.createElement('div');
                            backdrop.className = 'offcanvas-backdrop fade show';
                            document.body.appendChild(backdrop);
                        } else {
                            backdrop.classList.add('show');
                        }
                        
                        // Ensure body has offcanvas class
                        if (!document.body.classList.contains('offcanvas-backdrop')) {
                            document.body.classList.add('offcanvas-backdrop');
                        }
                        
                        // CRITICAL: Force Bootstrap to recognize offcanvas is shown
                        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                        if (offcanvas) {
                            offcanvas._isShown = true;
                        }
                        
                        // Update Select2 values when Livewire updates
                        setTimeout(function() {
                            const currentStatus = component.get('status');
                            const currentVisibility = component.get('visibility');
                            const currentCategoryId = component.get('categoryId') || '';
                            const currentTags = component.get('tags') || [];
                            const currentGalleries = component.get('galleries') || [];
                            
                            $('#status').val(currentStatus).trigger('change');
                            $('#visibility').val(currentVisibility).trigger('change');
                            $('#categoryId').val(currentCategoryId).trigger('change');
                            
                            // For tags and galleries with AJAX, we need to add options first if they don't exist
                            if (currentTags.length > 0) {
                                currentTags.forEach(function(tag) {
                                    if ($('#tags option[value="' + tag + '"]').length === 0) {
                                        const newOption = new Option(tag, tag, true, true);
                                        $('#tags').append(newOption);
                                    }
                                });
                                $('#tags').val(currentTags).trigger('change');
                            } else {
                                $('#tags').val([]).trigger('change');
                            }
                            
                            if (currentGalleries.length > 0) {
                                currentGalleries.forEach(function(gallery) {
                                    if ($('#galleries option[value="' + gallery + '"]').length === 0) {
                                        const newOption = new Option(gallery, gallery, true, true);
                                        $('#galleries').append(newOption);
                                    }
                                });
                                $('#galleries').val(currentGalleries).trigger('change');
                            } else {
                                $('#galleries').val([]).trigger('change');
                            }
                        }, 100);
                    }
                }
            } catch (error) {
                console.error('Error in morph.updated hook:', error);
            }
        });
        
        // Also hook into before morph to prevent closing (only if show is true)
        Livewire.hook('morph.before', function(data) {
            const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
            if (offcanvasEl && offcanvasEl._isLivewireManaged) {
                // Check if component exists and show is true before blocking
                try {
                    const component = data?.component;
                    if (component && component.__instance?.name === 'multimedia.edit-multimedia') {
                        const show = component.get('show');
                        // Only block if show is true
                        if (show) {
                            offcanvasEl._allowHide = false;
                        } else {
                            offcanvasEl._allowHide = true;
                        }
                    } else {
                        // If we can't determine, allow hide to be safe
                        offcanvasEl._allowHide = true;
                    }
                } catch (e) {
                    // If there's an error, allow hide to be safe
                    offcanvasEl._allowHide = true;
                }
            }
        });
        
        // Hook into commit to ensure offcanvas stays open after updates
        Livewire.hook('commit', function({ component, commit }) {
            const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
            if (offcanvasEl && offcanvasEl._isLivewireManaged && component.__instance?.name === 'multimedia.edit-multimedia') {
                const show = component.get('show');
                // If show is false, allow hide
                if (!show) {
                    offcanvasEl._allowHide = true;
                    return;
                }
                
                if (show) {
                    offcanvasEl._allowHide = false;
                    // Force offcanvas to stay open immediately and in setTimeout
                    if (!offcanvasEl.classList.contains('show')) {
                        offcanvasEl.classList.add('show');
                        offcanvasEl.style.visibility = 'visible';
                        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                        if (offcanvas) {
                            offcanvas._isShown = true;
                        }
                    }
                    // Force offcanvas to stay open
                    setTimeout(() => {
                        const stillExists = document.getElementById('multimediaEditOffcanvas');
                        if (stillExists && !stillExists.classList.contains('show')) {
                            stillExists.classList.add('show');
                            stillExists.style.visibility = 'visible';
                            const offcanvas = bootstrap.Offcanvas.getInstance(stillExists);
                            if (offcanvas) {
                                offcanvas._isShown = true;
                            }
                        }
                    }, 10);
                }
            }
        });
        
        Livewire.on('offcanvas:hide', () => {
            const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
            if (offcanvasEl) {
                // Allow hide to proceed
                offcanvasEl._allowHide = true;
                
                // Remove the event listener temporarily to allow hide
                if (offcanvasEl._hideHandler) {
                    offcanvasEl.removeEventListener('hide.bs.offcanvas', offcanvasEl._hideHandler, true);
                }
                
                // Reset Livewire managed flag
                offcanvasEl._isLivewireManaged = false;
                
                const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                if (offcanvas) {
                    // Use Bootstrap's hide method
                    offcanvas.hide();
                } else {
                    // Fallback: hide manually
                    offcanvasEl.classList.remove('show');
                    offcanvasEl.style.visibility = 'hidden';
                    const backdrop = document.querySelector('.offcanvas-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    document.body.classList.remove('offcanvas-backdrop');
                }
                
                // Clean up after hide animation completes
                setTimeout(() => {
                    // Remove any remaining handlers
                    if (offcanvasEl._hideHandler) {
                        offcanvasEl.removeEventListener('hide.bs.offcanvas', offcanvasEl._hideHandler, true);
                        offcanvasEl._hideHandler = null;
                    }
                    offcanvasEl._isLivewireManaged = false;
                    offcanvasEl._allowHide = true;
                }, 500);
            }
        });
        
        // Reload table/cards when multimedia is updated
        Livewire.on('multimedia:updated', () => {
            setTimeout(() => {
                if ($('#viewModeCards').is(':checked')) {
                    if (typeof loadMultimediaCards === 'function') {
                        loadMultimediaCards();
                    }
                } else {
                    if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                        $('#multimedia-table').DataTable().ajax.reload(null, false);
                    }
                }
            }, 500);
        });
    });
</script>
@endpush
