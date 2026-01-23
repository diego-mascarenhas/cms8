@props(['id', 'label', 'selected' => [], 'showNull' => false, 'moduleKey' => null, 'helpText' => null])

<div class="form-group">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" name="categories[]" class="form-control select2 @error($id) is-invalid @enderror" multiple>
        @if($showNull)
            <option value="">Seleccione una categoría</option>
        @endif

        @php
            // Determinar el módulo actual
            if (!$moduleKey) {
                $routeName = Route::currentRouteName();
                $segments = request()->segments();

                // Mapeo de rutas/segmentos a módulos
                $routeModuleMap = [
                    'task' => 'tasks',
                    'project' => 'projects',
                    'invoice' => 'invoices',
                    'ticket' => 'tickets',
                    'service' => 'services',
                    'communications' => 'communications',
                    'mail' => 'mail',
                    'chat' => 'chat',
                    'multimedia' => 'multimedia',
                ];

                // Comprobar la ruta por prefijo
                foreach ($routeModuleMap as $routePrefix => $key) {
                    if ($routeName && strpos($routeName, $routePrefix) === 0) {
                        $moduleKey = $key;
                        break;
                    }
                }

                // Si no encontramos por ruta, comprobar segmentos de URL
                if (!$moduleKey && !empty($segments)) {
                    foreach ($routeModuleMap as $routePrefix => $key) {
                        if (in_array($routePrefix, $segments)) {
                            $moduleKey = $key;
                            break;
                        }
                    }
                }
            }

            // Obtener el módulo
            $module = $moduleKey ? \App\Models\Module::where('key', $moduleKey)->first() : null;

            // Obtener todas las categorías activas para este módulo (grupos y subcategorías)
            if ($module) {
                $query = \App\Models\Category::where('module_id', $module->id)
                    ->where('status', 1)
                    ->where(function($query) {
                        $query->whereNull('team_id')
                            ->orWhere('team_id', auth()->user()->currentTeam->id);
                    });
            } else {
                // Si no tenemos un módulo, mostramos las categorías sin módulo
                $query = \App\Models\Category::whereNull('module_id')
                    ->where('status', 1)
                    ->where(function($query) {
                        $query->whereNull('team_id')
                            ->orWhere('team_id', auth()->user()->currentTeam->id);
                    });
            }

            // Obtener los grupos (categorías padre)
            $parentCategories = $query->whereNull('parent_id')
                ->orderBy('order')
                ->orderBy('name')
                ->get();

            // Obtener todas las subcategorías para este módulo
            if ($module) {
                $allSubcategories = \App\Models\Category::where('module_id', $module->id)
                    ->whereNotNull('parent_id')
                    ->where('status', 1)
                    ->where(function($query) {
                        $query->whereNull('team_id')
                            ->orWhere('team_id', auth()->user()->currentTeam->id);
                    })
                    ->orderBy('order')
                    ->orderBy('name')
                    ->get()
                    ->groupBy('parent_id');
            } else {
                $allSubcategories = \App\Models\Category::whereNull('module_id')
                    ->whereNotNull('parent_id')
                    ->where('status', 1)
                    ->where(function($query) {
                        $query->whereNull('team_id')
                            ->orWhere('team_id', auth()->user()->currentTeam->id);
                    })
                    ->orderBy('order')
                    ->orderBy('name')
                    ->get()
                    ->groupBy('parent_id');
            }
        @endphp

        @foreach($parentCategories as $parentCategory)
            <optgroup label="{{ $parentCategory->name }}">
                @if(isset($allSubcategories[$parentCategory->id]))
                    @foreach($allSubcategories[$parentCategory->id] as $subcategory)
                        <option value="{{ $subcategory->id }}" {{ in_array($subcategory->id, old('categories', $selected)) ? 'selected' : '' }}>
                            {{ $subcategory->name }}
                        </option>
                    @endforeach
                @endif
            </optgroup>
        @endforeach
    </select>

    @if($helpText)
        <div class="form-text">{{ $helpText }}</div>
    @endif

    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('scripts')
<script>
    console.log('Categories select script loading for: {{ $id }}');
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded - initializing categories select for: {{ $id }}');
        const select = $('#{{ $id }}');
        console.log('Select element found:', select.length);

        if (select.length === 0) {
            console.error('Select element not found!');
            return;
        }

        const moduleKey = '{{ $moduleKey ?? "" }}';
        console.log('Module key:', moduleKey);

        select.select2({
            placeholder: 'Select categories',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });

        // Intercept Select2 results processing
        select.on('results:all', function(params) {
            setTimeout(checkAndAddButton, 50);
        });

        // Intercept when Select2 shows "no results" message
        select.on('results:message', function(params) {
            setTimeout(checkAndAddButton, 50);
        });

        // Function to check and add button
        function checkAndAddButton() {
            try {
                console.log('=== checkAndAddButton called ===');
                const select2Data = select.data('select2');
                if (!select2Data) {
                    console.log('No select2 data');
                    return;
                }

                const dropdown = select2Data.$dropdown;
                if (!dropdown || !dropdown.is(':visible')) {
                    console.log('Dropdown not visible');
                    return;
                }

                const results = dropdown.find('.select2-results__options');
                if (results.length === 0) {
                    console.log('No results container found');
                    return;
                }

                const searchInput = select2Data.dropdown.$search;
                const searchTerm = searchInput ? searchInput.val().trim() : '';
                console.log('Search term:', searchTerm);

                // Remove existing button
                results.find('#add-category-btn-{{ $id }}').closest('li').remove();

                // Find the "no results" message by class and text content
                let noResultsMsg = results.find('.select2-results__message');
                console.log('Found by class:', noResultsMsg.length);

                // Also search by text content in case class doesn't match
                if (noResultsMsg.length === 0) {
                    results.find('li.select2-results__option').each(function() {
                        const text = $(this).text().trim().toLowerCase();
                        console.log('Checking option text:', text);
                        // More flexible matching
                        if (text.includes('no results') ||
                            text.includes('no se encontraron') ||
                            text.includes('sin resultados') ||
                            text === 'no results found' ||
                            text === 'no se encontraron resultados') {
                            noResultsMsg = $(this);
                            console.log('Found by text!');
                            return false; // break
                        }
                    });
                }

                console.log('Final noResultsMsg length:', noResultsMsg.length);
                if (noResultsMsg.length > 0) {
                    console.log('No results message HTML:', noResultsMsg[0].outerHTML);
                }

                // Check if there are any matching options in the original select element
                const originalOptions = select.find('option');
                let hasMatchingOption = false;
                const searchLower = searchTerm.toLowerCase();

                if (searchTerm) {
                    originalOptions.each(function() {
                        const optionText = $(this).text().toLowerCase();
                        if (optionText.includes(searchLower)) {
                            hasMatchingOption = true;
                            return false; // break
                        }
                    });
                }

                // Count visible options in results (excluding groups and messages)
                const visibleOptions = results.find('li.select2-results__option:visible').not('.select2-results__message').not('.select2-results__group');

                console.log('Debug - Search term:', searchTerm, 'No results msg found:', noResultsMsg.length, 'Has matching:', hasMatchingOption, 'Visible options:', visibleOptions.length);

                // If "no results" message exists and search term is at least 2 characters
                if (noResultsMsg.length > 0 && searchTerm && searchTerm.length >= 2) {
                    console.log('Condition met! Replacing message with button. Search term:', searchTerm);
                    // Replace the message with the button
                    const buttonHtml = '<li class="select2-results__option" role="option" style="padding: 0;"><div class="p-2"><button type="button" class="btn btn-sm btn-primary w-100" id="add-category-btn-{{ $id }}"><i class="ti ti-plus me-1"></i>Agregar "' + searchTerm + '"</button></div></li>';
                    noResultsMsg.replaceWith(buttonHtml);
                    console.log('Button added (replaced no results message)!');
                }
                // If no visible options and no message but search term exists
                else if (visibleOptions.length === 0 && noResultsMsg.length === 0 && searchTerm && searchTerm.length >= 2 && !hasMatchingOption) {
                    console.log('Condition met (no message but no options)! Adding button. Search term:', searchTerm);
                    // Add button
                    const buttonHtml = '<li class="select2-results__option" role="option" style="padding: 0;"><div class="p-2"><button type="button" class="btn btn-sm btn-primary w-100" id="add-category-btn-{{ $id }}"><i class="ti ti-plus me-1"></i>Agregar "' + searchTerm + '"</button></div></li>';
                    results.append(buttonHtml);
                    console.log('Button added (no options found)!');
                } else {
                    console.log('Condition NOT met. noResultsMsg:', noResultsMsg.length, 'searchTerm:', searchTerm, 'length:', searchTerm ? searchTerm.length : 0, 'visibleOptions:', visibleOptions.length, 'hasMatching:', hasMatchingOption);
                }
            } catch (e) {
                console.error('Error in checkAndAddButton:', e);
            }
        }

        // Listen for when dropdown opens
        select.on('select2:open', function() {
            console.log('Select2 dropdown opened for: {{ $id }}');
            setTimeout(function() {
                const searchInput = select.data('select2').dropdown.$search;
                console.log('Search input found:', searchInput ? 'yes' : 'no');

                // Check immediately after opening
                checkAndAddButton();

                // Check on every keystroke with multiple checks
                if (searchInput) {
                    // Remove previous handlers to avoid duplicates
                    searchInput.off('input.searchCategory keyup.searchCategory');
                    searchInput.on('input.searchCategory keyup.searchCategory', function() {
                        // Multiple checks with different delays to catch the message
                        setTimeout(checkAndAddButton, 10);
                        setTimeout(checkAndAddButton, 50);
                        setTimeout(checkAndAddButton, 100);
                        setTimeout(checkAndAddButton, 200);
                        setTimeout(checkAndAddButton, 400);
                    });
                }

                // Also use MutationObserver to watch for DOM changes in results
                const dropdown = select.data('select2').$dropdown;
                const results = dropdown.find('.select2-results__options');

                if (results.length > 0) {
                    const observer = new MutationObserver(function(mutations) {
                        // Check immediately when DOM changes
                        checkAndAddButton();

                        // Also check if a "no results" message was added
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes.length > 0) {
                                mutation.addedNodes.forEach(function(node) {
                                    if (node.nodeType === 1) { // Element node
                                        const $node = $(node);
                                        if ($node.hasClass('select2-results__message') ||
                                            $node.text().trim() === 'No results found' ||
                                            $node.text().trim() === 'No se encontraron resultados') {
                                            setTimeout(checkAndAddButton, 10);
                                        }
                                    }
                                });
                            }
                        });
                    });

                    observer.observe(results[0], {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['style', 'class'],
                        characterData: true
                    });

                    // Store observer to disconnect later
                    select.data('categoryObserver', observer);
                }

                // Also check periodically while dropdown is open (more frequently)
                const checkInterval = setInterval(function() {
                    if (!select.data('select2')?.$dropdown?.is(':visible')) {
                        clearInterval(checkInterval);
                        return;
                    }
                    checkAndAddButton();
                }, 100);

                // Store interval to clear later
                select.data('categoryCheckInterval', checkInterval);
            }, 100);
        });

        // Also check when dropdown closes and reopens
        select.on('select2:close', function() {
            const searchInput = select.data('select2')?.dropdown?.$search;
            if (searchInput) {
                searchInput.off('input.searchCategory keyup.searchCategory');
            }

            // Disconnect observer
            const observer = select.data('categoryObserver');
            if (observer) {
                observer.disconnect();
                select.removeData('categoryObserver');
            }

            // Clear interval
            const checkInterval = select.data('categoryCheckInterval');
            if (checkInterval) {
                clearInterval(checkInterval);
                select.removeData('categoryCheckInterval');
            }
        });

        // Handle click on "Add category" button
        $(document).on('click', '#add-category-btn-{{ $id }}', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const select2Data = select.data('select2');
            if (!select2Data) return;

            const searchInput = select2Data.dropdown.$search;
            const searchTerm = searchInput ? searchInput.val() : '';

            if (!searchTerm || searchTerm.length < 2) {
                alert('Por favor ingresa al menos 2 caracteres para crear una categoría');
                return;
            }

            // Disable button during request
            const btn = $(this);
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="ti ti-loader me-1"></i>Creando...');

            // Create category via AJAX
            $.ajax({
                url: '{{ route("categories.quick-store") }}',
                method: 'POST',
                data: {
                    name: searchTerm,
                    module_key: moduleKey,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Add new option to select (as a simple option, not in optgroup)
                        const newOption = new Option(response.category.name, response.category.id, false, true);
                        select.append(newOption).trigger('change');

                        // Close dropdown
                        select.select2('close');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error al crear la categoría';
                    alert(message);
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    });
</script>
@endpush
