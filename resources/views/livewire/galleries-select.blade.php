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
            const values = $(this).val() || [];
            @this.set('selected', Array.isArray(values) ? values : []);
        });
        
        // Set initial values
        const initialValues = @this.get('selected') || [];
        if (initialValues.length > 0) {
            initialValues.forEach(function(gallery) {
                if (galleriesSelect.find('option[value="' + gallery + '"]').length === 0) {
                    const newOption = new Option(gallery, gallery, true, true);
                    galleriesSelect.append(newOption);
                }
            });
            galleriesSelect.val(initialValues).trigger('change');
        }
    }
    
    // Initialize immediately if element exists
    if (document.getElementById('{{ $id }}')) {
        setTimeout(initializeGalleriesSelect2, 100);
    }
    
    // Also initialize when offcanvas opens
    Livewire.on('offcanvas:show', function() {
        setTimeout(initializeGalleriesSelect2, 300);
    });
    
    // Listen for external updates
    Livewire.on('galleries-select-updated-{{ $id }}', function(data) {
        if (data && Array.isArray(data)) {
            const galleriesSelect = $('#{{ $id }}');
            // Clear existing options
            galleriesSelect.empty();
            // Add new options
            data.forEach(function(gallery) {
                const newOption = new Option(gallery, gallery, true, true);
                galleriesSelect.append(newOption);
            });
            galleriesSelect.trigger('change');
        }
    });
});
</script>
@endpush
