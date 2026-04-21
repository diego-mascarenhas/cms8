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
@endphp

<div class="form-group">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
        @if($label !== null && $label !== '')
            <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
        @else
            <span class="d-none d-md-block"></span>
        @endif
        @if($moduleKey && $allowManageModal && ! $disabled)
            @can('viewAny', \App\Models\Category::class)
                @livewire(\App\Livewire\ModuleCategoriesManagerModal::class, ['moduleKey' => $moduleKey, 'linkedSelectId' => $id], key('module-cat-mgr-'.$id.'-'.$moduleKey))
            @endcan
        @endif
    </div>
    <select
        id="{{ $id }}"
        name="{{ $selectName }}"
        class="form-control select2 @error($errorField) is-invalid @enderror"
        data-placeholder="{{ $emptyText }}"
        data-allow-clear="true"
        @if($moduleKey) data-module-key="{{ $moduleKey }}" data-empty-text="{{ $emptyText }}" data-show-empty-option="{{ ($showNull || $allowEmpty) ? '1' : '0' }}" data-allow-empty-select="{{ $allowEmpty ? '1' : '0' }}" @endif
        {{ $allowEmpty ? '' : 'required' }}
        @if($multiple) multiple @endif
        @if($disabled) disabled @endif
    >
        @if($showNull || $allowEmpty)
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

            $isContactsModule = ($moduleKey === 'contacts');
        @endphp

        @foreach($parentCategories as $parentCategory)
            @php
                $hasSubcategories = isset($allSubcategories[$parentCategory->id]);
            @endphp
            @if(!$hasSubcategories)
                {{-- Parent with no subcategories: make it selectable so user can choose it --}}
                <option value="{{ $parentCategory->id }}" {{ in_array((string) $parentCategory->id, $selectedValues, true) ? 'selected' : '' }}>
                    {{ $parentCategory->name }}
                </option>
            @else
                <optgroup label="{{ $parentCategory->name }}">
                    @foreach($allSubcategories[$parentCategory->id] as $subcategory)
                        <option value="{{ $subcategory->id }}" {{ in_array((string) $subcategory->id, $selectedValues, true) ? 'selected' : '' }}>
                            {{ $subcategory->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
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
    document.addEventListener('DOMContentLoaded', function() {
        if ($.fn.select2 && $('#{{ $id }}').length) {
            const $moduleCategorySelect = $('#{{ $id }}');
            $moduleCategorySelect.select2({
                placeholder: @json($emptyText),
                allowClear: {{ $allowEmpty ? 'true' : 'false' }},
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
            $container.find('.select2-selection--multiple .select2-selection__rendered').css({
                paddingLeft: '0.75rem',
                paddingRight: '0.75rem'
            });
            $container.find('.select2-selection--multiple .select2-search--inline .select2-search__field').css({
                marginLeft: '0',
                marginRight: '0',
                textIndent: '0'
            });
            if (isMultipleSelect) {
                $container.find('.select2-selection--multiple .select2-search--inline').css({
                    display: 'none'
                });
                $container.find('.select2-selection--multiple').css({
                    paddingLeft: '0',
                    paddingRight: '0',
                    borderColor: '#d9dee3',
                    boxShadow: 'none'
                });
                $container.find('.select2-selection--multiple .select2-selection__rendered').css({
                    minHeight: '44px',
                    lineHeight: '44px'
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
            var showEmpty = String($s.data('show-empty-option')) === '1';
            var allowClear = String($s.data('allow-empty-select')) === '1';
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
