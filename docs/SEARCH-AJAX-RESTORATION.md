# Restauración del Buscador Global a AJAX (Versión Original)

**Fecha:** 2026-01-11  
**Estado:** ✅ Completado

## Resumen

Se restauró el buscador global del navbar a la **versión AJAX original** (pre-packages) que utilizaba un **toggler button** y un **input oculto**, eliminando la implementación Livewire que nunca funcionó correctamente.

## Problema Original

- El componente Livewire `GlobalSearch` fue implementado pero nunca funcionó correctamente
- La separación en packages rompió la funcionalidad
- El buscador estaba deshabilitado (`showSearch => false` en `config/custom.php`)
- La implementación no coincidía con el patrón original de Vuexy

## Versión Original (Pre-Livewire)

La versión original usaba:
1. **Botón toggler visible** - `<a class="search-toggler">` que muestra "Buscar (Ctrl+/)"
2. **Input oculto** - `<div class="search-input-wrapper d-none">` que aparece al hacer clic
3. **Atajo de teclado** - `Ctrl+/` para abrir el buscador
4. **Spinner de loading** - Indicador visual mientras busca
5. **Typeahead.js** - Para autocompletado con resultados agrupados

## Cambios Realizados

### 1. Configuración Habilitada
**Archivo:** `config/custom.php`
- Cambió `'showSearch' => false` a `'showSearch' => true` (línea 38)

### 2. Navbar Restaurado a Versión Original
**Archivo:** `resources/views/layouts/sections/navbar/navbar.blade.php`

**Versión Correcta (Restaurada):**
**Versión Correcta (Restaurada):**
```blade
@if (!isset($menuHorizontal) && (($configData['showSearch'] ?? true) === true))
    <!-- Search -->
    <div class="navbar-nav align-items-center">
        <div class="nav-item navbar-search-wrapper mb-0">
            <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
                <i class="ti ti-search ti-md me-2"></i>
                <span class="d-none d-md-inline-block text-muted">{{ __('app.search_with_shortcut') }}</span>
            </a>
        </div>
    </div>
    <!-- /Search -->
@endif
```

**Input Oculto (ya existente al final del navbar):**
```blade
<!-- Search Small Screens -->
<div class="navbar-search-wrapper search-input-wrapper {{ isset($menuHorizontal) ? $containerNav : '' }} d-none">
    <input type="text"
        class="form-control search-input {{ isset($menuHorizontal) ? '' : $containerNav }} border-0"
        placeholder="{{ __('app.search') }}..." aria-label="Search...">
    <i class="ti ti-x ti-sm search-toggler cursor-pointer"></i>
</div>

<div id="search-spinner" class="spinner-border text-primary d-none" role="status">
    <span class="visually-hidden">{{ __('app.searching') }}...</span>
</div>
```

### 3. JavaScript Ctrl+/ Habilitado
**Archivo:** `resources/assets/js/main.js` (líneas 383-395)

**Antes (Comentado para Livewire):**
```javascript
// Open search on 'CTRL+/' - Disabled for Livewire search
// $(document).on('keydown', function (event) {
//   let ctrlKey = event.ctrlKey,
//     slashKey = event.which === 191;
//   if (ctrlKey && slashKey) {
//     if (searchInputWrapper.length) {
//       searchInputWrapper.toggleClass('d-none');
//       searchInput.focus();
//     }
//   }
// });
```

**Después (Descomentado):**
```javascript
// Open search on 'CTRL+/'
$(document).on('keydown', function (event) {
  let ctrlKey = event.ctrlKey,
    slashKey = event.which === 191;
  if (ctrlKey && slashKey) {
    if (searchInputWrapper.length) {
      searchInputWrapper.toggleClass('d-none');
      searchInput.focus();
    }
  }
});
```

### 4. Endpoint AJAX Existente
**Archivo:** `app/Http/Controllers/contactController.php`  
**Ruta:** `/contact/search` (línea 865-1100+)

El endpoint ya existía y está completamente funcional. Búsqueda en:
- **Contactos** (`members`): Búsqueda por nombre, apellido, email, teléfono
- **Empresas** (`enterprises`): Búsqueda por nombre, código, teléfono, email
- **Servicios** (`services`): Búsqueda por descripción y datos JSON
- **Proyectos** (`projects`): Búsqueda por nombre y descripción
- **Facturas** (`invoices`): Búsqueda por número de factura

**Características:**
- ✅ Scoped por equipo (`team_id`)
- ✅ Respeta permisos de usuario (admin vs no-admin)
- ✅ Búsqueda optimizada con límites (20 resultados por categoría)
- ✅ Respeta módulos activos del equipo
- ✅ Caché en frontend para evitar llamadas duplicadas
- ✅ Debouncing de 150ms por tipo de búsqueda

### 5. JavaScript Typeahead
**Archivo:** `resources/assets/js/main.js` (líneas 409-736)

Ya implementado con:
- **Typeahead.js** para autocompletado
- **Fetch API** para peticiones asíncronas
- **Caché** de resultados por query
- **Debouncing** para optimizar peticiones
- **Templates personalizados** para cada tipo de resultado
- **Iconos específicos** por categoría:
  - `ti-user` para contactos
  - `ti-building` para empresas
  - `ti-world` para servicios
  - `ti-folder` para proyectos
  - `ti-file-invoice` para facturas

### 6. Compilación de Assets
```bash
npm run build
```
✅ Compilado exitosamente

## Cómo Funciona

### Flujo de Búsqueda:

1. **Usuario hace clic** en "Buscar (Ctrl+/)" o presiona `Ctrl+/`
2. **Se muestra input oculto** - Se remueve clase `d-none` del `search-input-wrapper`
3. **Usuario escribe** - Se activa Typeahead.js
4. **Debouncing 150ms** - Espera que el usuario termine de escribir
5. **AJAX fetch** - Llama a `/contact/search?q=término`
6. **Respuesta JSON** - Retorna resultados agrupados por categoría
7. **Renderiza resultados** - Typeahead muestra dropdown con templates personalizados
8. **Usuario selecciona** - Navega a la URL del resultado
9. **Input se oculta** - Vuelve a `d-none` al cerrar

### Atajos de Teclado:

- **Ctrl+/** - Abre/cierra el buscador
- **Flechas ↑↓** - Navega entre resultados
- **Enter** - Selecciona resultado actual
- **Esc** - Cierra el buscador

## Componentes Obsoletos (NO Eliminados)

Los siguientes archivos de Livewire **NO se eliminaron** por si se necesitan de referencia:
- `app/Livewire/GlobalSearch.php`
- `resources/views/livewire/global-search.blade.php`

**Nota:** Pueden eliminarse manualmente si ya no se necesitan.

## Características de la Búsqueda

### Funcionalidades
1. **Búsqueda en tiempo real** mientras escribes (debouncing 150ms)
2. **Resultados agrupados** por categoría con headers
3. **Navegación con teclado** (Ctrl+/ para abrir)
4. **Caché local** para mejorar rendimiento
5. **Backdrop oscuro** cuando hay resultados
6. **Redirección automática** al seleccionar resultado
7. **Scope automático por equipo** y permisos

### Ejemplo de Respuesta JSON
```json
{
  "members": [
    {
      "name": "John Doe",
      "subtitle": "john@example.com",
      "url": "https://humano.test/contact/123"
    }
  ],
  "enterprises": [...],
  "services": [...],
  "projects": [...],
  "invoices": [...]
}
```

## Pruebas

### Para Probar:
1. Navegar a cualquier página de la app: `https://humano.test/dashboard`
2. Hacer clic en el campo de búsqueda o presionar `Ctrl+/`
3. Escribir al menos 1 carácter
4. Ver resultados agrupados por categoría
5. Hacer clic en un resultado para navegar

### Verificaciones:
- ✅ Búsqueda de contactos funciona
- ✅ Búsqueda de empresas funciona
- ✅ Búsqueda de servicios funciona (si módulo activo)
- ✅ Búsqueda de proyectos funciona (si módulo activo)
- ✅ Búsqueda de facturas funciona (admin only)
- ✅ Respeta permisos por equipo
- ✅ Caché funciona correctamente
- ✅ Resultados se muestran con iconos apropiados

## Ventajas vs Livewire

| Aspecto | AJAX (Actual) | Livewire (Anterior) |
|---------|--------------|---------------------|
| **Rendimiento** | ⚡ Muy rápido (fetch + caché) | 🐢 Más lento (WebSocket overhead) |
| **Compatibilidad** | ✅ No depende de packages | ❌ Se rompió con packages |
| **Debugging** | ✅ Fácil en Network tab | ❌ Complejo (WebSocket) |
| **Código** | ✅ JavaScript estándar | ❌ Lógica dividida PHP/JS |
| **Mantenimiento** | ✅ Código Vuexy original | ❌ Custom implementation |
| **Browser Cache** | ✅ Implementado | ❌ No implementado |

## Próximos Pasos (Opcional)

1. **Eliminar componentes Livewire** si ya no se necesitan:
   ```bash
   rm app/Livewire/GlobalSearch.php
   rm resources/views/livewire/global-search.blade.php
   ```

2. **Agregar más categorías** de búsqueda si se requiere (ej: productos, órdenes)

3. **Mejorar UI** del dropdown de resultados si se desea

4. **Agregar búsqueda avanzada** con filtros adicionales

## Notas Técnicas

- El buscador usa **Typeahead.js** v0.11+
- El endpoint retorna JSON con estructura de Vuexy
- La búsqueda es case-insensitive
- Se usa `CONCAT` para búsqueda de nombre completo en contactos
- Se usa `JSON_SEARCH` para búsqueda en campos JSON de servicios
- El placeholder es traducible vía `__('Search')`

## Referencias

- Template original: `https://vuexy.test`
- Ruta del endpoint: `/contact/search`
- Archivo JS principal: `resources/assets/js/main.js`
- Configuración: `config/custom.php`

---

**✅ La búsqueda global está completamente funcional y lista para uso.**
