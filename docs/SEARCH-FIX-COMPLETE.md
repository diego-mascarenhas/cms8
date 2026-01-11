# Búsqueda Global - Corrección Completa

## Fecha: 2026-01-12

## Problema Original
La búsqueda global dejó de funcionar después de intentar modularizar la aplicación en packages.

## Problemas Identificados y Solucionados

### 1. **Livewire vs AJAX** ✅ RESUELTO
**Problema**: Se había intentado migrar a Livewire pero no funcionaba correctamente.
**Solución**: Revertir a la implementación AJAX original con Typeahead.js.

### 2. **Patrón de Datasets** ✅ RESUELTO
**Problema**: Dataset de Contactos no tenía template `notFound`.
**Solución**: Agregar `notFound` a todos los datasets siguiendo el patrón de Empresas.

### 3. **Filtro de Status** ✅ RESUELTO  
**Problema**: La búsqueda excluía contactos con `status_id = 6`.
**Solución**: Eliminar el filtro para incluir todos los contactos.

### 4. **Query de Búsqueda** ✅ RESUELTO
**Problema**: Usaba `CONCAT(name, ' ', surname)` que era menos flexible.
**Solución**: Cambiar a búsqueda individual por campos:
```php
$q->where('name', 'like', "%{$query}%")
  ->orWhere('surname', 'like', "%{$query}%")
  ->orWhere('email', 'like', "%{$query}%")
  ->orWhere('phone', 'like', "%{$query}%");
```

### 5. **config.js No Cargado** ✅ RESUELTO - CRÍTICO
**Problema**: `config.js` no se estaba cargando antes de `main.js`, causando que `baseUrl` fuera `undefined`.
**Solución**: 
- Agregar `<script src="{{ asset('assets/js/config.js') }}"></script>` en `scripts.blade.php`
- Copiar `config.js` actualizado a `public/assets/js/`

## Archivos Modificados

1. **resources/assets/js/main.js**
   - Implementado `fetchSearchResponse()` para compartir peticiones AJAX
   - Actualizado `dynamicSearch()` para usar caché compartido
   - Agregado template `notFound` a dataset de Contactos

2. **public/assets/js/main.js**
   - Copiado manualmente desde resources (Vite plugin tiene issues)

3. **resources/views/layouts/sections/navbar/navbar.blade.php**
   - Revertido de Livewire a AJAX pattern original

4. **config/custom.php**
   - Habilitado búsqueda: `'showSearch' => true`

5. **app/Http/Controllers/contactController.php**
   - Línea ~920: Eliminado filtro `->where('status_id', '!=', 6)`
   - Línea ~928: Cambiado query a búsqueda individual por campos

6. **resources/views/layouts/sections/scripts.blade.php** ⚡ CRÍTICO
   - Agregado `config.js` antes de `main.js`

7. **public/assets/js/config.js**
   - Copiado desde resources/assets/js/config.js

## Estructura de config.js

`config.js` define variables globales esenciales:
```javascript
let baseUrl = document.documentElement.getAttribute('data-base-url') + '/';
```

El atributo `data-base-url` se define en `commonMaster.blade.php`:
```html
<html ... data-base-url="{{url('/')}}" ...>
```

## Orden de Carga Correcto

1. jQuery
2. Vendor libraries (typeahead, select2, etc.)
3. **config.js** ✨ (define baseUrl)
4. main.js (usa baseUrl para AJAX)

## Datos de Prueba Creados

Team 2 (REVISION ALPHA's Team):
- Pepe López (status_id: 1 - Lead)
- Juan Finalizado (status_id: 6 - Finalizado)
- Empresa: Nada

## Testing

### Backend Test
```bash
php artisan tinker
$user = App\Models\User::find(X);
auth()->login($user);
auth()->user()->switchTeam($user->teams()->first());
$request = new Illuminate\Http\Request(['q' => 'pepe']);
$controller = new App\Http\Controllers\ContactController();
$response = $controller->search($request);
echo json_encode($response->getData(), JSON_PRETTY_PRINT);
```

### Frontend Test
1. Hard refresh: `Cmd + Shift + R`
2. Abrir DevTools → Network tab
3. Buscar "pepe"
4. Verificar petición a `/contact/search?q=pepe`
5. Verificar respuesta JSON con datos

## Estado Actual

✅ Backend funciona correctamente
✅ config.js cargado
✅ Peticiones AJAX se realizan
⏳ Verificando respuesta del servidor (pending user feedback)

## Troubleshooting

### Si no aparecen resultados:
1. Hard refresh del navegador
2. Limpiar caché del navegador
3. Verificar en Network tab que la petición se realiza
4. Verificar respuesta del servidor
5. Verificar que el usuario esté autenticado
6. Verificar que el team tenga el módulo activado

### Verificar módulos del team:
```php
$team = App\Models\Team::find(X);
$team->hasModule('contacts'); // debe retornar true
```

### Verificar contactos del team:
```php
App\Models\Contact::where('team_id', X)->get();
```

## Referencias

- Commit original funcional: `f5f701a3` (antes de Livewire)
- Documentación: `docs/SEARCH-AJAX-RESTORATION.md`
- Typeahead.js: https://github.com/twitter/typeahead.js/

---

**Última actualización**: 2026-01-12 00:35 UTC
