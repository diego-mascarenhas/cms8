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
            const values = $(this).val() || [];
            @this.set('selected', Array.isArray(values) ? values : []);
        });
        
        // Set initial values
        const initialValues = @this.get('selected') || [];
        if (initialValues.length > 0) {
            initialValues.forEach(function(tag) {
                if (tagsSelect.find('option[value="' + tag + '"]').length === 0) {
                    const newOption = new Option(tag, tag, true, true);
                    tagsSelect.append(newOption);
                }
            });
            tagsSelect.val(initialValues).trigger('change');
        }
    }
    
    // Initialize immediately if element exists
    if (document.getElementById('{{ $id }}')) {
        setTimeout(initializeTagsSelect2, 100);
    }
    
    // Also initialize when offcanvas opens
    Livewire.on('offcanvas:show', function() {
        setTimeout(initializeTagsSelect2, 300);
    });
    
    // Listen for external updates
    Livewire.on('tags-select-updated-{{ $id }}', function(data) {
        if (data && Array.isArray(data)) {
            const tagsSelect = $('#{{ $id }}');
            // Clear existing options
            tagsSelect.empty();
            // Add new options
            data.forEach(function(tag) {
                const newOption = new Option(tag, tag, true, true);
                tagsSelect.append(newOption);
            });
            tagsSelect.trigger('change');
        }
    });
});
</script>
@endpush
