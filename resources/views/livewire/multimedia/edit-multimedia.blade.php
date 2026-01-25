<div class="offcanvas offcanvas-end multimedia-edit-sidebar" tabindex="-1" id="multimediaEditOffcanvas" data-bs-backdrop="true" data-bs-scroll="true" style="visibility: hidden;" wire:ignore.self>
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">{{ __('app.Edit Media') }}</h5>
        <button type="button" class="btn-close" wire:click="close" onclick="event.stopPropagation();" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form wire:submit.prevent="update" onsubmit="console.log('Form submit triggered'); return true;">
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
                    <div wire:ignore>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach($statusOptions as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('status')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="visibility">{{ __('app.Visibility') }}</label>
                    <div wire:ignore>
                        <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror">
                            @foreach($visibilityOptions as $visibility)
                                <option value="{{ $visibility->value }}">{{ $visibility->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('visibility')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="categoryId">{{ __('app.Category') }}</label>
                <div wire:ignore>
                    <select id="categoryId" name="categoryId" class="form-select @error('categoryId') is-invalid @enderror">
                        <option value="">{{ __('app.No category') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('categoryId')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="tags">{{ __('app.Tags') }}</label>
                <div wire:ignore>
                    <select id="tags" name="tags[]" class="form-select" multiple>
                        {{-- Options will be populated by Select2 AJAX --}}
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="galleries">{{ __('app.Galleries') }}</label>
                <div wire:ignore>
                    <select id="galleries" name="galleries[]" class="form-select" multiple>
                        {{-- Options will be populated by Select2 AJAX --}}
                    </select>
                </div>
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
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" onclick="console.log('Save button clicked');">
                    <span wire:loading.remove><i class="ti ti-check me-1"></i>{{ __('app.Save') }}</span>
                    <span wire:loading>{{ __('app.Saving...') }}</span>
                </button>
                <button type="button" class="btn btn-label-secondary" wire:click="close" onclick="console.log('Cancel button clicked');">
                    {{ __('app.Cancel') }}
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    'use strict';

    function showOffcanvas() {
        console.log('showOffcanvas() called');
        const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
        if (!offcanvasEl) {
            console.error('Offcanvas element not found');
            return;
        }

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
        const existingHandler = offcanvasEl._hideHandler;
        if (existingHandler) {
            offcanvasEl.removeEventListener('hide.bs.offcanvas', existingHandler);
        }

        offcanvasEl._hideHandler = function(e) {
            if (!offcanvasEl._allowHide) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        };
        offcanvasEl.addEventListener('hide.bs.offcanvas', offcanvasEl._hideHandler, true);

        // Show the offcanvas
        console.log('Showing offcanvas...');
        offcanvasEl.style.visibility = 'visible';
        try {
            offcanvas.show();
            console.log('Offcanvas.show() called successfully');
        } catch (error) {
            console.error('Error showing offcanvas:', error);
            // Fallback: manually add show class
            offcanvasEl.classList.add('show');
            document.body.classList.add('offcanvas-backdrop');
        }

        // Ensure it's visible after Bootstrap animation
        setTimeout(() => {
            if (!offcanvasEl.classList.contains('show')) {
                offcanvasEl.classList.add('show');
            }
            const backdrop = document.querySelector('.offcanvas-backdrop');
            if (backdrop) {
                backdrop.style.display = 'block';
            }
        }, 100);

        // Initialize Select2 for status, visibility, and category
        initializeFormSelect2();
    }

    // Centralized function to initialize Select2 on form selects
    function initializeFormSelect2() {
        // Ensure elements exist
        const statusEl = $('#status');
        const visibilityEl = $('#visibility');
        const categoryEl = $('#categoryId');
        const tagsEl = $('#tags');
        const galleriesEl = $('#galleries');

        if (statusEl.length === 0 || visibilityEl.length === 0 || categoryEl.length === 0 || tagsEl.length === 0 || galleriesEl.length === 0) {
            setTimeout(initializeFormSelect2, 50);
            return;
        }

        // Destroy existing Select2 instances
        [statusEl, visibilityEl, categoryEl, tagsEl, galleriesEl].forEach(el => {
            if (el.hasClass('select2-hidden-accessible')) {
                el.select2('destroy');
            }
        });

        // Initialize Select2 for status and visibility (no search)
        statusEl.select2({
            width: '100%',
            dropdownParent: $('#multimediaEditOffcanvas'),
            minimumResultsForSearch: Infinity
        });

        visibilityEl.select2({
            width: '100%',
            dropdownParent: $('#multimediaEditOffcanvas'),
            minimumResultsForSearch: Infinity
        });

        // Initialize Select2 for categoryId (with search)
        categoryEl.select2({
            width: '100%',
            dropdownParent: $('#multimediaEditOffcanvas'),
            placeholder: '{{ __("app.No category") }}',
            allowClear: true,
            closeOnSelect: false
        });

        // Initialize Select2 for tags with AJAX
        tagsEl.select2({
            width: '100%',
            dropdownParent: $('#multimediaEditOffcanvas'),
            tags: true,
            placeholder: 'Buscar o crear etiquetas...',
            allowClear: true,
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
                    // Return all results - Select2 will mark selected ones automatically
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
        }).on('select2:select', function(e) {
            // Close only when selecting (not when unselecting)
            $(this).select2('close');
        });
        
        // Initialize Select2 for galleries with AJAX
        galleriesEl.select2({
            width: '100%',
            dropdownParent: $('#multimediaEditOffcanvas'),
            tags: true,
            placeholder: 'Buscar o crear galerías...',
            allowClear: true,
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
                    // Return all results - Select2 will mark selected ones automatically
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
        }).on('select2:select', function(e) {
            // Close only when selecting (not when unselecting)
            $(this).select2('close');
        });

        // Add change event listeners to sync with Livewire
        statusEl.off('change').on('change', function() {
            @this.set('status', $(this).val());
        });

        visibilityEl.off('change').on('change', function() {
            @this.set('visibility', $(this).val());
        });

        categoryEl.off('change').on('change', function() {
            @this.set('categoryId', $(this).val() || null);
        });

        // Sync tags with Livewire - use select2:select and select2:unselect for proper handling
        tagsEl.off('select2:select select2:unselect').on('select2:select select2:unselect', function() {
            let values = $(this).val() || [];
            const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
            values = Array.isArray(values) ? values.filter(v => v && !invalidValues.includes(v.trim())) : [];
            @this.set('tags', values);
        });

        // Sync galleries with Livewire - use select2:select and select2:unselect for proper handling
        galleriesEl.off('select2:select select2:unselect').on('select2:select select2:unselect', function() {
            let values = $(this).val() || [];
            const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
            values = Array.isArray(values) ? values.filter(v => v && !invalidValues.includes(v.trim())) : [];
            @this.set('galleries', values);
        });

        // Set initial values from Livewire component
        const currentStatus = @this.get('status');
        const currentVisibility = @this.get('visibility');
        const currentCategoryId = @this.get('categoryId');
        const currentTags = @this.get('tags') || [];
        const currentGalleries = @this.get('galleries') || [];

        if (currentStatus !== undefined && currentStatus !== null) {
            statusEl.val(currentStatus).trigger('change.select2');
        }
        if (currentVisibility !== undefined && currentVisibility !== null) {
            visibilityEl.val(currentVisibility).trigger('change.select2');
        }
        if (currentCategoryId) {
            categoryEl.val(currentCategoryId).trigger('change.select2');
        } else {
            categoryEl.val('').trigger('change.select2');
        }

        // Set tags - don't empty, just set the values directly
        if (currentTags.length > 0) {
            // Clear existing options first
            tagsEl.find('option').remove();
            // Add new options as selected
            currentTags.forEach(function(tag) {
                const newOption = new Option(tag, tag, true, true);
                tagsEl.append(newOption);
            });
            tagsEl.trigger('change.select2');
        } else {
            // Clear all if no tags
            tagsEl.find('option').remove();
            tagsEl.val(null).trigger('change.select2');
        }

        // Set galleries - don't empty, just set the values directly
        if (currentGalleries.length > 0) {
            // Clear existing options first
            galleriesEl.find('option').remove();
            // Add new options as selected
            currentGalleries.forEach(function(gallery) {
                const newOption = new Option(gallery, gallery, true, true);
                galleriesEl.append(newOption);
            });
            galleriesEl.trigger('change.select2');
        } else {
            // Clear all if no galleries
            galleriesEl.find('option').remove();
            galleriesEl.val(null).trigger('change.select2');
        }
    }

    // Register event listener as soon as possible
    if (typeof Livewire !== 'undefined') {
        Livewire.on('offcanvas:show', showOffcanvas);
        console.log('Registered offcanvas:show listener');
    } else {
        // Wait for Livewire to be available
        document.addEventListener('livewire:initialized', function() {
            Livewire.on('offcanvas:show', showOffcanvas);
            console.log('Registered offcanvas:show listener (after initialization)');
        }, { once: true });
    }

    // Also register when Livewire initializes (in case the above didn't catch it)
    document.addEventListener('livewire:initialized', function() {
        const offcanvasEl = document.getElementById('multimediaEditOffcanvas');
        if (offcanvasEl && offcanvasEl.parentElement !== document.body) {
            document.body.appendChild(offcanvasEl);
        }

        // Re-register listener to ensure it's active
        Livewire.on('offcanvas:show', showOffcanvas);
        console.log('Re-registered offcanvas:show listener');
    });

    // Watch for show property changes and update Select2 values
    Livewire.hook('morph.updated', function(data) {
        try {
            const component = data.component;
            if (component && component.__instance && component.__instance.name === 'multimedia.edit-multimedia') {
                const show = component.get('show');
                let offcanvasEl = document.getElementById('multimediaEditOffcanvas');

                console.log('morph.updated - show:', show);

                // If show is false, allow hide and close the offcanvas IMMEDIATELY
                if (!show && offcanvasEl) {
                    console.log('Show is false, allowing hide and closing offcanvas');
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
                    return; // CRITICAL: Exit early to prevent keeping it open
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

                    // Re-initialize Select2 after Livewire updates to prevent losing styles
                    initializeFormSelect2();

                    // Update Select2 values when Livewire updates
                    setTimeout(function() {
                        const currentStatus = component.get('status');
                        const currentVisibility = component.get('visibility');
                        const currentCategoryId = component.get('categoryId') || '';

                        $('#status').val(currentStatus).trigger('change');
                        $('#visibility').val(currentVisibility).trigger('change');
                        $('#categoryId').val(currentCategoryId).trigger('change');
                    }, 300);
                }
            }
        } catch (error) {
            console.error('Error in morph.updated hook:', error);
        }
    });

    Livewire.hook('morph.before', function(data) {
        let offcanvasEl = document.getElementById('multimediaEditOffcanvas');
        if (offcanvasEl && offcanvasEl._isLivewireManaged) {
            try {
                const component = data?.component;
                if (component && component.__instance?.name === 'multimedia.edit-multimedia') {
                    const show = component.get('show');
                    if (show) {
                        offcanvasEl._allowHide = false;
                    } else {
                        offcanvasEl._allowHide = true;
                    }
                } else {
                    offcanvasEl._allowHide = true;
                }
            } catch (e) {
                offcanvasEl._allowHide = true;
            }
        }
    });

    Livewire.hook('commit', function({ component, commit }) {
        let offcanvasEl = document.getElementById('multimediaEditOffcanvas');
        if (offcanvasEl && offcanvasEl._isLivewireManaged && component.__instance?.name === 'multimedia.edit-multimedia') {
            const show = component.get('show');
            console.log('commit hook - show:', show);

            // If show is false, allow hide IMMEDIATELY
            if (!show) {
                console.log('Show is false in commit, allowing hide');
                offcanvasEl._allowHide = true;
                return; // Exit early
            }

            if (show) {
                offcanvasEl._allowHide = false;
                if (!offcanvasEl.classList.contains('show')) {
                    offcanvasEl.classList.add('show');
                    offcanvasEl.style.visibility = 'visible';
                    const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                    if (offcanvas) {
                        offcanvas._isShown = true;
                    }
                }
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
        console.log('offcanvas:hide event received');
        let offcanvasEl = document.getElementById('multimediaEditOffcanvas');
        if (offcanvasEl) {
            console.log('Hiding offcanvas...');
            offcanvasEl._allowHide = true;

            if (offcanvasEl._hideHandler) {
                offcanvasEl.removeEventListener('hide.bs.offcanvas', offcanvasEl._hideHandler, true);
            }

            offcanvasEl._isLivewireManaged = false;

            const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
            if (offcanvas) {
                offcanvas.hide();
                console.log('Offcanvas.hide() called');
            } else {
                console.log('No offcanvas instance, hiding manually');
                offcanvasEl.classList.remove('show');
                offcanvasEl.style.visibility = 'hidden';
                const backdrop = document.querySelector('.offcanvas-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                document.body.classList.remove('offcanvas-backdrop');
            }

            setTimeout(() => {
                if (offcanvasEl._hideHandler) {
                    offcanvasEl.removeEventListener('hide.bs.offcanvas', offcanvasEl._hideHandler, true);
                    offcanvasEl._hideHandler = null;
                }
                offcanvasEl._isLivewireManaged = false;
                offcanvasEl._allowHide = true;
                console.log('Offcanvas cleanup completed');
            }, 500);
        } else {
            console.error('Offcanvas element not found when trying to hide');
        }
    });

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
})();
</script>
@endpush
