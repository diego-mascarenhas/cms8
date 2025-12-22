@props(['id', 'label', 'selected' => [], 'showNull' => false, 'moduleKey' => null, 'helpText' => null])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
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
    document.addEventListener('DOMContentLoaded', function() {
        $('#{{ $id }}').select2({
            placeholder: 'Select categories',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });
    });
</script>
@endpush
