@props(['id', 'label', 'selected' => null, 'showNull' => true, 'moduleKey' => null, 'disabled' => false, 'allowEmpty' => false, 'emptyText' => 'Seleccione una categoría'])

<div class="form-group">
    @if($label !== null && $label !== '')
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    @endif
    <select id="{{ $id }}" name="{{ $id }}" class="form-control select2 @error($id) is-invalid @enderror" data-placeholder="{{ $emptyText }}" data-allow-clear="true" {{ $allowEmpty ? '' : 'required' }} @if($disabled) disabled @endif>
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
                <option value="{{ $parentCategory->id }}" {{ $selected == $parentCategory->id ? 'selected' : '' }}>
                    {{ $parentCategory->name }}
                </option>
            @else
                <optgroup label="{{ $parentCategory->name }}">
                    @foreach($allSubcategories[$parentCategory->id] as $subcategory)
                        <option value="{{ $subcategory->id }}" {{ $selected == $subcategory->id ? 'selected' : '' }}>
                            {{ $subcategory->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach
    </select>

    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if ($.fn.select2 && $('#{{ $id }}').length) {
            $('#{{ $id }}').select2({
                placeholder: '{{ $emptyText }}',
                allowClear: {{ $allowEmpty ? 'true' : 'false' }},
                width: '100%',
            });
        }
    });
</script>
@endpush
