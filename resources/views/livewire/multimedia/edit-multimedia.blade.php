<div class="offcanvas offcanvas-end multimedia-edit-sidebar" tabindex="-1" id="multimediaEditOffcanvas" data-bs-backdrop="true" data-bs-scroll="true" style="visibility: hidden;" wire:ignore.self>
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">{{ __('app.Edit Media') }}</h5>
        <button type="button" class="btn btn-sm btn-icon btn-danger" onclick="confirmDelete()" aria-label="Delete">
            <i class="ti ti-trash"></i>
        </button>
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
                    <input id="tags" class="form-control" placeholder="{{ __('Select tags...') }}" value="">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="galleries">{{ __('app.Galleries') }}</label>
                <div wire:ignore>
                    <input id="galleries" class="form-control" placeholder="{{ __('Select galleries...') }}" value="">
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
// Global Tagify instances for sidebar
let sidebarTagifyTags, sidebarTagifyGalleries;

// Global function for delete confirmation (must be outside IIFE)
window.confirmDelete = function() {
    Swal.fire({
        title: '{{ __("app.Are you sure you want to delete this record?") }}',
        text: '{{ __("app.This action cannot be undone") }}',
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: false,
        confirmButtonText: '{{ __("app.Yes, delete") }}',
        cancelButtonText: '{{ __("app.Cancel") }}',
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger me-2 waves-effect waves-light',
            cancelButton: 'btn btn-label-secondary waves-effect'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('multimedia:delete-confirmed');
            } else {
                console.error('Livewire not available');
            }
        }
    });
};

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

        // Prevent Bootstrap from closing during Livewire updates, but allow manual close
        const existingHandler = offcanvasEl._hideHandler;
        if (existingHandler) {
            offcanvasEl.removeEventListener('hide.bs.offcanvas', existingHandler);
        }

        offcanvasEl._hideHandler = function(e) {
            // Allow closing if triggered by user action (backdrop click or close button)
            // Block only if it's an automatic hide during Livewire updates
            if (!offcanvasEl._allowHide && !e.isTrusted) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            // User wants to close, notify Livewire
            if (e.isTrusted) {
                @this.set('show', false);
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
                
                // Add click handler to backdrop to close offcanvas
                if (!backdrop._hasClickHandler) {
                    backdrop.addEventListener('click', function(e) {
                        console.log('Backdrop clicked, closing offcanvas');
                        offcanvasEl._allowHide = true;
                        @this.set('show', false);
                        const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasEl);
                        if (offcanvasInstance) {
                            offcanvasInstance.hide();
                        }
                    });
                    backdrop._hasClickHandler = true;
                }
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
        const tagsEl = document.querySelector('#tags');
        const galleriesEl = document.querySelector('#galleries');

        if (statusEl.length === 0 || visibilityEl.length === 0 || categoryEl.length === 0 || !tagsEl || !galleriesEl) {
            setTimeout(initializeFormSelect2, 50);
            return;
        }

        // Destroy existing Select2 instances (only for status, visibility, category)
        [statusEl, visibilityEl, categoryEl].forEach(el => {
            if (el.hasClass('select2-hidden-accessible')) {
                el.select2('destroy');
            }
        });

        // Destroy existing Tagify instances
        if (sidebarTagifyTags) {
            sidebarTagifyTags.destroy();
        }
        if (sidebarTagifyGalleries) {
            sidebarTagifyGalleries.destroy();
        }

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

        // Initialize Tagify for tags with inline suggestions
        sidebarTagifyTags = new Tagify(tagsEl, {
            whitelist: [],
            maxTags: 10,
            dropdown: {
                maxItems: 20,
                classname: 'tags-inline',
                enabled: 0,
                closeOnSelect: false,
                appendTarget: document.querySelector('#multimediaEditOffcanvas')
            }
        });

        // Open dropdown on click
        const tagsContainer = sidebarTagifyTags.DOM.scope;
        tagsContainer.addEventListener('click', function(e) {
            if (sidebarTagifyTags.whitelist.length === 0) {
                fetch('{{ route("tags.search") }}?' + new URLSearchParams({
                    q: '',
                    type: 'general'
                }))
                .then(response => response.json())
                .then(data => {
                    sidebarTagifyTags.whitelist = data.map(tag => tag.name);
                    sidebarTagifyTags.dropdown.show();
                });
            } else {
                sidebarTagifyTags.dropdown.show();
            }
        });

        // Update suggestions as user types
        sidebarTagifyTags.on('input', function(e) {
            const value = e.detail.value;
            
            fetch('{{ route("tags.search") }}?' + new URLSearchParams({
                q: value,
                type: 'general'
            }))
            .then(response => response.json())
            .then(data => {
                sidebarTagifyTags.whitelist = data.map(tag => tag.name);
                sidebarTagifyTags.dropdown.show(value);
            });
        });

        // Initialize Tagify for galleries with inline suggestions
        sidebarTagifyGalleries = new Tagify(galleriesEl, {
            whitelist: [],
            maxTags: 10,
            dropdown: {
                maxItems: 20,
                classname: 'tags-inline',
                enabled: 0,
                closeOnSelect: false,
                appendTarget: document.querySelector('#multimediaEditOffcanvas')
            }
        });

        // Open dropdown on click
        const galleriesContainer = sidebarTagifyGalleries.DOM.scope;
        galleriesContainer.addEventListener('click', function(e) {
            if (sidebarTagifyGalleries.whitelist.length === 0) {
                fetch('{{ route("tags.search") }}?' + new URLSearchParams({
                    q: '',
                    type: 'gallery'
                }))
                .then(response => response.json())
                .then(data => {
                    sidebarTagifyGalleries.whitelist = data.map(tag => tag.name);
                    sidebarTagifyGalleries.dropdown.show();
                });
            } else {
                sidebarTagifyGalleries.dropdown.show();
            }
        });

        // Update suggestions as user types
        sidebarTagifyGalleries.on('input', function(e) {
            const value = e.detail.value;
            
            fetch('{{ route("tags.search") }}?' + new URLSearchParams({
                q: value,
                type: 'gallery'
            }))
            .then(response => response.json())
            .then(data => {
                sidebarTagifyGalleries.whitelist = data.map(tag => tag.name);
                sidebarTagifyGalleries.dropdown.show(value);
            });
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

        // Sync tags with Livewire
        sidebarTagifyTags.on('add remove', function(e) {
            const tags = sidebarTagifyTags.value.map(tag => tag.value);
            // Filter invalid values
            const invalidValues = ['Todos', 'todos', 'all', 'All', '', 'null', 'undefined'];
            const filteredTags = tags.filter(v => {
                if (!v) return false;
                const trimmed = v.trim();
                if (invalidValues.includes(trimmed)) return false;
                if (trimmed.startsWith('{') && trimmed.includes('Todos')) return false;
                return true;
            });
            @this.set('tags', filteredTags);
        });

        // Sync galleries with Livewire
        sidebarTagifyGalleries.on('add remove', function(e) {
            const galleries = sidebarTagifyGalleries.value.map(tag => tag.value);
            // Filter invalid values
            const invalidValues = ['Todos', 'todos', 'all', 'All', '', 'null', 'undefined'];
            const filteredGalleries = galleries.filter(v => {
                if (!v) return false;
                const trimmed = v.trim();
                if (invalidValues.includes(trimmed)) return false;
                if (trimmed.startsWith('{') && trimmed.includes('Todos')) return false;
                return true;
            });
            @this.set('galleries', filteredGalleries);
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

        // Set tags for Tagify
        if (currentTags.length > 0) {
            sidebarTagifyTags.removeAllTags();
            sidebarTagifyTags.addTags(currentTags);
        } else {
            sidebarTagifyTags.removeAllTags();
        }

        // Set galleries for Tagify
        if (currentGalleries.length > 0) {
            sidebarTagifyGalleries.removeAllTags();
            sidebarTagifyGalleries.addTags(currentGalleries);
        } else {
            sidebarTagifyGalleries.removeAllTags();
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
                            // Allow closing if triggered by user action (backdrop click or close button)
                            // Block only if it's an automatic hide during Livewire updates
                            if (!offcanvasEl._allowHide && !e.isTrusted) {
                                e.preventDefault();
                                e.stopPropagation();
                                e.stopImmediatePropagation();
                                return false;
                            }
                            // User wants to close, notify Livewire
                            if (e.isTrusted) {
                                @this.set('show', false);
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
                    
                    // Add click handler to backdrop to close offcanvas
                    if (backdrop && !backdrop._hasClickHandler) {
                        backdrop.addEventListener('click', function(e) {
                            console.log('Backdrop clicked, closing offcanvas');
                            offcanvasEl._allowHide = true;
                            @this.set('show', false);
                            const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasEl);
                            if (offcanvasInstance) {
                                offcanvasInstance.hide();
                            }
                        });
                        backdrop._hasClickHandler = true;
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

@push('styles')
<style>
    /* Prevent Select2 dropdown jump in offcanvas */
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
    
    .select2-dropdown-no-jump .select2-results__message {
        display: none !important;
    }
</style>
@endpush
