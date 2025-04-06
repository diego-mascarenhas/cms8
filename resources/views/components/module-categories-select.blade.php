@props(['id', 'label', 'selected' => null, 'showNull' => true, 'moduleKey' => null])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror">
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
            
            // Obtener el módulo y categorías
            $module = $moduleKey ? \App\Models\Module::where('key', $moduleKey)->first() : null;
            
            // Si tenemos un módulo, buscamos sus categorías
            if ($module) {
                $categories = \App\Models\Category::where('module_id', $module->id)
                    ->where(function($query) {
                        $query->whereNull('team_id')
                            ->orWhere('team_id', auth()->user()->currentTeam->id);
                    })
                    ->orderBy('name')
                    ->get();
            } else {
                // Si no tenemos un módulo, mostramos todas las categorías
                $categories = \App\Models\Category::whereNull('module_id')
                    ->where(function($query) {
                        $query->whereNull('team_id')
                            ->orWhere('team_id', auth()->user()->currentTeam->id);
                    })
                    ->orderBy('name')
                    ->get();
            }
        @endphp
        
        @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ $selected == $category->id ? 'selected' : '' }}>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    
    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div> 