@props(['id', 'name' => null, 'errorKey' => null, 'label', 'selected' => null, 'showNull' => true, 'moduleKey' => null, 'disabled' => false, 'allowEmpty' => false, 'multiple' => false, 'emptyText' => 'Seleccione una categoría', 'allowQuickCreate' => true, 'allowManageModal' => true, 'listingFilter' => false])

@php
    if ($listingFilter)
    {
        $allowManageModal = false;
        $allowQuickCreate = false;
    }
@endphp

@php
    $selectName = $name ?: ($multiple ? $id.'[]' : $id);
    $errorField = $errorKey ?: $id;
    $selectedValues = $multiple
        ? collect(old($errorField, $selected ?? []))->map(fn ($value) => (string) $value)->all()
        : [(string) old($errorField, $selected ?? '')];
    $showEmptyOption = ($showNull || $allowEmpty) && ! $multiple;
    $enableAllowClear = $allowEmpty && ! $multiple;
@endphp

@php
    $showSelectHeader = ($label !== null && $label !== '')
        || ($moduleKey && $allowManageModal && ! $disabled && ! $listingFilter);
@endphp

<div class="form-group">
    @if ($showSelectHeader)
    <div class="d-flex align-items-center justify-content-between flex-nowrap gap-2 mb-1" style="min-height: 2.25rem;">
        @if($label !== null && $label !== '')
            <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
        @endif
        @if($moduleKey && $allowManageModal && ! $disabled)
            @can('viewAny', \App\Models\Category::class)
                @livewire(\App\Livewire\ModuleCategoriesManagerModal::class, ['moduleKey' => $moduleKey, 'linkedSelectId' => $id], key('module-cat-mgr-'.$id.'-'.$moduleKey))
            @endcan
        @endif
    </div>
    @endif
    <select
        id="{{ $id }}"
        name="{{ $selectName }}"
        class="form-control select2 @error($errorField) is-invalid @enderror"
        data-placeholder="{{ $emptyText }}"
        data-allow-clear="{{ $enableAllowClear ? 'true' : 'false' }}"
        @if($moduleKey) data-module-key="{{ $moduleKey }}" data-empty-text="{{ $emptyText }}" data-show-empty-option="{{ $showEmptyOption ? '1' : '0' }}" data-allow-empty-select="{{ $allowEmpty ? '1' : '0' }}" @endif
        {{ $allowEmpty ? '' : 'required' }}
        @if($multiple) multiple @endif
        @if($disabled) disabled @endif
    >
        @if($showEmptyOption)
            <option value="">{{ $emptyText }}</option>
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
                    'product' => 'products',
                    'communications' => 'communications',
                    'mail' => 'mail',
                    'chat' => 'chat',
                    'multimedia' => 'multimedia',
                    'team-file' => 'team_files',
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

            $teamId = auth()->user()?->currentTeam?->id;
            $baseQuery = $module
                ? \App\Models\Category::query()->where('module_id', $module->id)
                : \App\Models\Category::query()->whereNull('module_id');

            $baseQuery
                ->where('status', '>', 0)
                ->where(function ($query) use ($teamId) {
                    $query->whereNull('team_id');
                    if ($teamId) {
                        $query->orWhere('team_id', $teamId);
                    }
                });

            $parentCategories = (clone $baseQuery)
                ->whereNull('parent_id')
                ->orderBy('order')
                ->orderBy('name')
                ->get();

            $allSubcategories = (clone $baseQuery)
                ->whereNotNull('parent_id')
                ->orderBy('order')
                ->orderBy('name')
                ->get()
                ->groupBy('parent_id');

            $parentIds = $parentCategories->pluck('id')->map(fn ($id) => (int) $id)->all();
            $categoryById = (clone $baseQuery)->get(['id', 'name', 'parent_id'])->keyBy('id');

            $nestedLabel = function ($category) use ($categoryById): string {
                $parts = [(string) $category->name];
                $parentId = $category->parent_id ? (int) $category->parent_id : null;
                $guard = 0;
                while ($parentId && $guard < 5) {
                    $parent = $categoryById->get($parentId);
                    if (! $parent) {
                        break;
                    }
                    // Stop before the root group name (shown as optgroup)
                    if ($parent->parent_id === null) {
                        break;
                    }
                    array_unshift($parts, (string) $parent->name);
                    $parentId = $parent->parent_id ? (int) $parent->parent_id : null;
                    $guard++;
                }

                return implode(' › ', $parts);
            };
        @endphp

        @foreach($parentCategories as $parentCategory)
            @php
                $directChildren = $allSubcategories[$parentCategory->id] ?? collect();
                $nestedChildren = collect();
                foreach ($directChildren as $child) {
                    foreach (($allSubcategories[$child->id] ?? collect()) as $grandChild) {
                        $nestedChildren->push($grandChild);
                    }
                }
                $hasSubcategories = $directChildren->isNotEmpty() || $nestedChildren->isNotEmpty();
            @endphp
            @if(!$hasSubcategories)
                {{-- Parent with no subcategories: make it selectable so user can choose it --}}
                <option value="{{ $parentCategory->id }}" {{ in_array((string) $parentCategory->id, $selectedValues, true) ? 'selected' : '' }}>
                    {{ $parentCategory->name }}
                </option>
            @else
                <optgroup label="{{ $parentCategory->name }}">
                    @foreach($directChildren as $subcategory)
                        <option value="{{ $subcategory->id }}" {{ in_array((string) $subcategory->id, $selectedValues, true) ? 'selected' : '' }}>
                            {{ $subcategory->name }}
                        </option>
                    @endforeach
                    @foreach($nestedChildren as $nestedCategory)
                        <option value="{{ $nestedCategory->id }}" {{ in_array((string) $nestedCategory->id, $selectedValues, true) ? 'selected' : '' }}>
                            {{ $nestedLabel($nestedCategory) }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach

        {{-- Children whose parent is missing/inactive/non-root and not already rendered --}}
        @foreach($allSubcategories as $parentId => $orphanGroup)
            @if(in_array((int) $parentId, $parentIds, true))
                @continue
            @endif
            @php
                $parentCategory = $categoryById->get((int) $parentId);
                // Skip grandchildren already rendered under a root optgroup
                $skipAsNested = $parentCategory && $parentCategory->parent_id !== null
                    && in_array((int) $parentCategory->parent_id, $parentIds, true);
            @endphp
            @if($skipAsNested)
                @continue
            @endif
            @foreach($orphanGroup as $orphanCategory)
                <option value="{{ $orphanCategory->id }}" {{ in_array((string) $orphanCategory->id, $selectedValues, true) ? 'selected' : '' }}>
                    {{ $nestedLabel($orphanCategory) }}
                </option>
            @endforeach
        @endforeach
    </select>

    @error($errorField)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('scripts')
<script>
    $(function() {
        if ($.fn.select2 && $('#{{ $id }}').length) {
            const $moduleCategorySelect = $('#{{ $id }}');
            if ($moduleCategorySelect.hasClass('select2-hidden-accessible')) {
                return;
            }
            $moduleCategorySelect.select2({
                placeholder: @json($emptyText),
                allowClear: {{ $enableAllowClear ? 'true' : 'false' }},
                closeOnSelect: {{ $multiple ? 'false' : 'true' }},
                dropdownParent: $(document.body),
                language: {
                    noResults: function () {
                        return '';
                    }
                },
                escapeMarkup: function (markup) {
                    return markup;
                },
                width: '100%',
            });
            const $container = $moduleCategorySelect.next('.select2-container');
            const isMultipleSelect = $moduleCategorySelect.prop('multiple');
            if (isMultipleSelect) {
                $container.find('.select2-selection--multiple').css({
                    minHeight: 'calc(2.25rem + 2px)',
                    padding: '0',
                    borderColor: '#d9dee3',
                    boxShadow: 'none'
                });
                $container.find('.select2-selection--multiple .select2-selection__rendered').css({
                    padding: '.375rem .75rem',
                    display: 'flex',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: '.25rem',
                    margin: '0',
                    minHeight: '0',
                    border: 'none',
                    boxShadow: 'none'
                });
                $container.find('.select2-selection--multiple .select2-search--inline').css({
                    margin: '0',
                    flex: '1 1 auto',
                    minWidth: '2rem',
                    width: 'auto',
                    lineHeight: 'normal'
                });
                $container.find('.select2-selection--multiple .select2-search--inline .select2-search__field').css({
                    margin: '0',
                    padding: '0',
                    textIndent: '0',
                    height: '1.5rem',
                    minHeight: '0',
                    border: 'none',
                    outline: 'none',
                    boxShadow: 'none'
                });
            }
        }
    });
</script>
@if ($allowQuickCreate && ! $disabled)
    @include('components.partials.select2-module-category-quick-create', [
        'selectId' => $id,
        'moduleKey' => $moduleKey,
        'multiple' => $multiple,
    ])
@endif
@endpush

@once
@push('scripts')
<script>
    (function () {
        var moduleOptionsUrl = @json(route('categories.module-options'));

        function humaRebuildModuleCategorySelect(selectId, moduleKey) {
            var $s = typeof jQuery !== 'undefined' ? jQuery('#' + selectId) : null;
            if (!$s || !$s.length || !moduleKey || typeof jQuery.fn.select2 === 'undefined') {
                return;
            }

            var emptyText = $s.data('empty-text') || '';
            var isMultiple = $s.prop('multiple');
            var showEmpty = !isMultiple && String($s.data('show-empty-option')) === '1';
            var allowClear = !isMultiple && String($s.data('allow-empty-select')) === '1';
            var prevVal = $s.val();

            jQuery.getJSON(moduleOptionsUrl, { module_key: moduleKey })
                .done(function (data) {
                    if ($s.hasClass('select2-hidden-accessible')) {
                        $s.select2('destroy');
                    }

                    $s.empty();
                    if (showEmpty) {
                        $s.append(new Option(emptyText, '', false, false));
                    }

                    (data.groups || []).forEach(function (g) {
                        if (g.type === 'option') {
                            $s.append(new Option(g.label, String(g.id), false, false));
                        } else if (g.type === 'group') {
                            var og = jQuery('<optgroup>').attr('label', g.label);
                            (g.options || []).forEach(function (o) {
                                og.append(new Option(o.label, String(o.id), false, false));
                            });
                            $s.append(og);
                        }
                    });

                    var prevValExists = prevVal && $s.find('option[value="' + String(prevVal).replace(/"/g, '\\"') + '"]').length;

                    $s.select2({
                        placeholder: emptyText,
                        allowClear: allowClear,
                        closeOnSelect: !isMultiple,
                        dropdownParent: jQuery(document.body),
                        language: {
                            noResults: function () {
                                return '';
                            }
                        },
                        escapeMarkup: function (markup) {
                            return markup;
                        },
                        width: '100%',
                    });

                    if (prevValExists) {
                        $s.val(String(prevVal)).trigger('change');
                    } else {
                        $s.val(null).trigger('change');
                    }
                });
        }

        function registerModuleCategoryListener() {
            Livewire.on('module-categories-refreshed', function (event) {
                var selectId  = event && event.selectId  ? event.selectId  : null;
                var moduleKey = event && event.moduleKey ? event.moduleKey : null;
                if (selectId && moduleKey) {
                    humaRebuildModuleCategorySelect(selectId, moduleKey);
                }
            });
        }

        if (typeof Livewire !== 'undefined') {
            registerModuleCategoryListener();
        } else {
            document.addEventListener('livewire:init', registerModuleCategoryListener);
        }
    })();
</script>
@endpush
@endonce
