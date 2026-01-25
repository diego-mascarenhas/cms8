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
        @foreach($selected as $tag)
            <option value="{{ $tag }}" selected>{{ $tag }}</option>
        @endforeach
    </select>
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    function initializeTagsSelect2() {
        const tagsSelect = $('#{{ $id }}');
        
        if (tagsSelect.length === 0) {
            console.error('Tags select element #{{ $id }} not found');
            return;
        }
        
        // Destroy existing Select2 instance if any
        if (tagsSelect.hasClass('select2-hidden-accessible')) {
            tagsSelect.select2('destroy');
        }
        
        // Initialize Select2 with AJAX
        tagsSelect.select2({
            width: '100%',
            dropdownParent: tagsSelect.closest('.offcanvas, .modal, body').first(),
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
        
        // Hide "No results found" message
        tagsSelect.on('select2:open', function() {
            setTimeout(function() {
                $('.select2-results__message').hide();
            }, 10);
        });
        
        tagsSelect.on('select2:selecting', function() {
            $('.select2-results__message').hide();
        });
        
        tagsSelect.on('results:message', function() {
            setTimeout(function() {
                $('.select2-results__message').hide();
            }, 10);
        });
        
        // Sync with Livewire component
        tagsSelect.on('select2:select select2:unselect change', function() {
            let values = $(this).val() || [];
            // Filter out invalid placeholder values
            const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
            values = Array.isArray(values) 
                ? values.filter(v => v && !invalidValues.includes(v.trim()))
                : [];
            console.log('Tags syncing to Livewire:', values);
            @this.set('selected', values);
        });
        
        // Set initial values after Select2 is initialized
        setTimeout(function() {
            const initialValues = @this.get('selected') || [];
            console.log('Tags initial values:', initialValues);
            
            // Filter out invalid placeholder values
            const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
            const filteredValues = initialValues.filter(v => v && !invalidValues.includes(v.trim()));
            
            console.log('Tags filtered initial values:', filteredValues);
            
            if (filteredValues.length > 0) {
                // Clear existing options first
                tagsSelect.empty();
                // Add all initial values as options
                filteredValues.forEach(function(tag) {
                    const newOption = new Option(tag, tag, true, true);
                    tagsSelect.append(newOption);
                });
                // Set values and trigger change to update Select2 display
                tagsSelect.val(filteredValues).trigger('change');
            }
        }, 100);
    }
    
    function updateTagsSelect2Values(values) {
        const tagsSelect = $('#{{ $id }}');
        if (tagsSelect.length === 0 || !tagsSelect.hasClass('select2-hidden-accessible')) {
            return;
        }
        
        // Filter out invalid placeholder values
        const invalidValues = ['Todos', 'todos', 'all', 'All', ''];
        const filteredValues = values && Array.isArray(values) 
            ? values.filter(v => v && !invalidValues.includes(v.trim()))
            : [];
        
        console.log('Updating Tags Select2 with filtered values:', filteredValues);
        
        // Clear existing options
        tagsSelect.empty();
        
        // Add new options
        if (filteredValues.length > 0) {
            filteredValues.forEach(function(tag) {
                const newOption = new Option(tag, tag, true, true);
                tagsSelect.append(newOption);
            });
            // Update Select2 with new values
            tagsSelect.val(filteredValues).trigger('change');
        }
    }
    
    // Initialize immediately if element exists
    if (document.getElementById('{{ $id }}')) {
        setTimeout(initializeTagsSelect2, 100);
    }
    
    // Also initialize when offcanvas opens - with delay to ensure data is loaded
    Livewire.on('offcanvas:show', function() {
        setTimeout(function() {
            initializeTagsSelect2();
            // Also update values after initialization - get from parent component
            setTimeout(function() {
                // Try to get values from parent component
                const allComponents = Livewire.all();
                let parentTags = [];
                for (let key in allComponents) {
                    if (allComponents.hasOwnProperty(key)) {
                        const comp = allComponents[key];
                        if (comp && comp.__instance && comp.__instance.name === 'multimedia.edit-multimedia') {
                            parentTags = comp.get('tags') || [];
                            break;
                        }
                    }
                }
                
                // If no parent tags, try from this component
                if (parentTags.length === 0) {
                    parentTags = @this.get('selected') || [];
                }
                
                console.log('Tags to set after offcanvas open:', parentTags);
                if (parentTags.length > 0) {
                    updateTagsSelect2Values(parentTags);
                }
            }, 500);
        }, 400);
    });
    
    // Listen for when the parent component updates the selected values
    Livewire.hook('morph.updated', function({ component }) {
        if (component && component.__instance && component.__instance.name === 'multimedia.edit-multimedia') {
            // Get the tags from the parent component
            const parentTags = component.get('tags') || [];
            console.log('Parent tags updated:', parentTags);
            setTimeout(function() {
                updateTagsSelect2Values(parentTags);
            }, 200);
        }
    });
    
    // Also listen for updates to this component's selected property
    Livewire.hook('morph.updated', function({ component }) {
        if (component && component.__instance) {
            const componentName = component.__instance.name || '';
            if (componentName.includes('tags-select') || componentName.includes('tagsSelect')) {
                const currentSelected = component.get('selected') || [];
                console.log('TagsSelect component selected updated:', currentSelected);
                setTimeout(function() {
                    updateTagsSelect2Values(currentSelected);
                }, 100);
            }
        }
    });
    
    // Listen for external updates
    Livewire.on('tags-select-updated-{{ $id }}', function(data) {
        if (data && Array.isArray(data)) {
            updateTagsSelect2Values(data);
        }
    });
});
</script>
@endpush
