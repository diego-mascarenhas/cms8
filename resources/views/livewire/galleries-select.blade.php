<div class="mb-3" wire:ignore>
    <label class="form-label" for="{{ $id }}">{{ $label }}</label>
    <select 
        id="{{ $id }}" 
        name="{{ $name }}[]" 
        class="form-select @error($name) is-invalid @enderror" 
        @if($multiple) multiple @endif
        @if($required) required @endif
    >
        {{-- Options will be added dynamically by Select2 and Livewire --}}
        @foreach($selected as $gallery)
            <option value="{{ $gallery }}" selected>{{ $gallery }}</option>
        @endforeach
    </select>
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    function initializeGalleriesSelect2() {
        const galleriesSelect = $('#{{ $id }}');
        
        if (galleriesSelect.length === 0) {
            console.error('Galleries select element #{{ $id }} not found');
            return;
        }
        
        // Destroy existing Select2 instance if any
        if (galleriesSelect.hasClass('select2-hidden-accessible')) {
            galleriesSelect.select2('destroy');
        }
        
        // Initialize Select2 with AJAX
        galleriesSelect.select2({
            width: '100%',
            dropdownParent: galleriesSelect.closest('.offcanvas, .modal, body').first(),
            tags: true,
            tokenSeparators: [','],
            placeholder: '{{ $placeholder }}',
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
                        q: params.term,
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
        
        // Hide "No results found" message
        galleriesSelect.on('select2:open', function() {
            setTimeout(function() {
                $('.select2-results__message').hide();
            }, 10);
        });
        
        galleriesSelect.on('select2:selecting', function() {
            $('.select2-results__message').hide();
        });
        
        galleriesSelect.on('results:message', function() {
            setTimeout(function() {
                $('.select2-results__message').hide();
            }, 10);
        });
        
        // Sync with Livewire component
        galleriesSelect.on('select2:select select2:unselect change', function() {
            let values = $(this).val() || [];
            // Filter out invalid placeholder values
            const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
            values = Array.isArray(values) 
                ? values.filter(v => v && !invalidValues.includes(v.trim()))
                : [];
            console.log('Galleries syncing to Livewire:', values);
            @this.set('selected', values);
        });
        
        // Set initial values after Select2 is initialized
        setTimeout(function() {
            const initialValues = @this.get('selected') || [];
            console.log('Galleries initial values:', initialValues);
            
            // Filter out invalid placeholder values
            const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
            const filteredValues = initialValues.filter(v => v && !invalidValues.includes(v.trim()));
            
            console.log('Galleries filtered initial values:', filteredValues);
            
            if (filteredValues.length > 0) {
                // Clear existing options first
                galleriesSelect.empty();
                // Add all initial values as options
                filteredValues.forEach(function(gallery) {
                    const newOption = new Option(gallery, gallery, true, true);
                    galleriesSelect.append(newOption);
                });
                // Set values and trigger change to update Select2 display
                galleriesSelect.val(filteredValues).trigger('change');
            }
        }, 100);
    }
    
    function updateGalleriesSelect2Values(values) {
        const galleriesSelect = $('#{{ $id }}');
        if (galleriesSelect.length === 0 || !galleriesSelect.hasClass('select2-hidden-accessible')) {
            return;
        }
        
        // Filter out invalid placeholder values
        const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
        const filteredValues = values && Array.isArray(values) 
            ? values.filter(v => v && !invalidValues.includes(v.trim()))
            : [];
        
        console.log('Updating Galleries Select2 with filtered values:', filteredValues);
        
        // Clear existing options
        galleriesSelect.empty();
        
        // Add new options
        if (filteredValues.length > 0) {
            filteredValues.forEach(function(gallery) {
                const newOption = new Option(gallery, gallery, true, true);
                galleriesSelect.append(newOption);
            });
            // Update Select2 with new values
            galleriesSelect.val(filteredValues).trigger('change');
        }
    }
    
    // Initialize immediately if element exists
    if (document.getElementById('{{ $id }}')) {
        setTimeout(initializeGalleriesSelect2, 100);
    }
    
    // Also initialize when offcanvas opens - with delay to ensure data is loaded
    Livewire.on('offcanvas:show', function() {
        setTimeout(function() {
            initializeGalleriesSelect2();
            // Also update values after initialization - get from parent component
            setTimeout(function() {
                // Try to get values from parent component
                const allComponents = Livewire.all();
                let parentGalleries = [];
                for (let key in allComponents) {
                    if (allComponents.hasOwnProperty(key)) {
                        const comp = allComponents[key];
                        if (comp && comp.__instance && comp.__instance.name === 'multimedia.edit-multimedia') {
                            parentGalleries = comp.get('galleries') || [];
                            break;
                        }
                    }
                }
                
                // If no parent galleries, try from this component
                if (parentGalleries.length === 0) {
                    parentGalleries = @this.get('selected') || [];
                }
                
                console.log('Galleries to set after offcanvas open:', parentGalleries);
                if (parentGalleries.length > 0) {
                    updateGalleriesSelect2Values(parentGalleries);
                }
            }, 500);
        }, 400);
    });
    
    // Listen for when the parent component updates the selected values
    Livewire.hook('morph.updated', function({ component }) {
        if (component && component.__instance && component.__instance.name === 'multimedia.edit-multimedia') {
            // Get the galleries from the parent component
            const parentGalleries = component.get('galleries') || [];
            console.log('Parent galleries updated:', parentGalleries);
            setTimeout(function() {
                updateGalleriesSelect2Values(parentGalleries);
            }, 200);
        }
    });
    
    // Also listen for updates to this component's selected property
    Livewire.hook('morph.updated', function({ component }) {
        if (component && component.__instance) {
            const componentName = component.__instance.name || '';
            if (componentName.includes('galleries-select') || componentName.includes('galleriesSelect')) {
                const currentSelected = component.get('selected') || [];
                console.log('GalleriesSelect component selected updated:', currentSelected);
                setTimeout(function() {
                    updateGalleriesSelect2Values(currentSelected);
                }, 100);
            }
        }
    });
    
    // Listen for external updates
    Livewire.on('galleries-select-updated-{{ $id }}', function(data) {
        if (data && Array.isArray(data)) {
            updateGalleriesSelect2Values(data);
        }
    });
});
</script>
@endpush
