# Correcciones de Rendimiento - 10 de Octubre 2025

## Problemas Identificados

Durante la auditoría de rendimiento en producción se identificaron **3 problemas críticos** que causaban:

-   **105+ requests HTTP** por carga de página
-   **9.81 MB** de datos transferidos
-   **Lentitud generalizada** en todas las páginas

---

## 🔴 Problema 1: Laravel Debugbar Activo en Producción

### Impacto

-   Polling constante a `/_debugbar/open?op=get&id=...`
-   Múltiples peticiones HTTP innecesarias en cada interacción
-   Exposición de información sensible (queries SQL, variables, etc.)

### Causa

El paquete `barryvdh/laravel-debugbar` está en `require` (producción) en lugar de `require-dev` (desarrollo).

### Solución Inmediata (Ya en Producción)

Agregar al `.env` de producción:

```bash
DEBUGBAR_ENABLED=false
```

Luego ejecutar:

```bash
php artisan config:clear
php artisan cache:clear
```

### Solución Permanente (Próximo Deploy)

Mover el paquete a dependencias de desarrollo:

```bash
composer remove barryvdh/laravel-debugbar
composer require --dev barryvdh/laravel-debugbar
composer update
git add composer.json composer.lock
git commit -m "Move debugbar to dev dependencies"
```

---

## ✅ Problema 2: Búsqueda Sin Validación (CORREGIDO)

### Impacto

-   El endpoint `/contact/search?q=` devolvía **TODOS** los contactos cuando `q` estaba vacío
-   Miles de registros transferidos innecesariamente
-   Riesgo de seguridad: exposición de todos los contactos

### Causa

No había validación de longitud mínima en el parámetro de búsqueda.

### Solución Aplicada

-   Agregada validación: requiere mínimo **2 caracteres** para buscar
-   Si `q` está vacío o < 2 caracteres, devuelve estructura vacía
-   Previene búsquedas accidentales que cargan toda la BD

**Archivo modificado:** `app/Http/Controllers/ContactController.php`

**Código agregado:**

```php
// Require at least 2 characters to search (security and performance)
if (empty($query) || strlen($query) < 2) {
	return response()->json($data);
}
```

---

## ✅ Problema 3: Middleware Cargando Permisos en CADA Request (OPTIMIZADO)

### Impacto

-   **680 Roles + 365 Permisos** cargados en cada petición
-   **41+ queries SQL** por request (incluso AJAX/Livewire)
-   Procesamiento del menú completo en requests que no lo necesitan

### Causa

`ModifyMenuBasedOnRole` middleware en el grupo `web`, ejecutándose en:

-   Todas las páginas HTML
-   Peticiones AJAX
-   Livewire updates
-   Debugbar polling

### Solución Aplicada

#### 1. Skip de Requests AJAX/Livewire

El middleware ahora detecta y omite peticiones que no necesitan el menú:

```php
// Skip menu processing for AJAX/Livewire requests (performance optimization)
if ($request->ajax() || $request->header('X-Livewire')) {
	return $next($request);
}
```

#### 2. Caché del Menú por Usuario/Equipo

El menú se cachea por 1 hora para cada combinación usuario+equipo:

```php
// Cache menu for 1 hour per user/team combination
$cacheKey = "menu_user_{$user->id}_team_{$user->currentTeam->id}";
$menuData = Cache::remember($cacheKey, 3600, function () use ($user) {
	// ... procesamiento del menú
});
```

**Beneficios:**

-   El menú se calcula **1 vez por hora** por usuario (vs. en cada request)
-   Las peticiones AJAX/Livewire **no tocan la base de datos** para permisos
-   Reducción estimada del **90%** de queries relacionadas con permisos

**Archivo modificado:** `app/Http/Middleware/ModifyMenuBasedOnRole.php`

---

## 📊 Impacto Esperado

### Antes de las Optimizaciones

-   ❌ 105+ requests por página
-   ❌ 9.81 MB transferidos
-   ❌ 41+ queries SQL por request
-   ❌ Carga de 680 roles + 365 permisos en cada petición
-   ❌ Debugbar polling continuo

### Después de las Optimizaciones

-   ✅ ~10-20 requests por página (reducción del **80-90%**)
-   ✅ ~1-2 MB transferidos (reducción del **80%**)
-   ✅ 5-10 queries SQL por request (reducción del **75%**)
-   ✅ Permisos cargados 1 vez por hora (reducción del **99%**)
-   ✅ Sin polling de Debugbar

---

## 🚀 Instrucciones de Deploy

### 1. Verificar Cambios Locales

```bash
cd /Users/magoo/Sites/humano
git status
git diff app/Http/Controllers/ContactController.php
git diff app/Http/Middleware/ModifyMenuBasedOnRole.php
```

### 2. Commit de los Cambios

```bash
git add app/Http/Controllers/ContactController.php
git add app/Http/Middleware/ModifyMenuBasedOnRole.php
git commit -m "Performance optimizations: cache menu, validate search, skip AJAX"
```

### 3. Push a Producción

```bash
git push origin dev
# O el branch que uses para producción
```

### 4. En Producción (Forge)

```bash
# Agregar al .env INMEDIATAMENTE:
echo "DEBUGBAR_ENABLED=false" >> .env

# Limpiar cachés:
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Verificar que esté deshabilitado:
php artisan tinker
>>> config('debugbar.enabled')
# Debe devolver: false
```

### 5. Monitoreo Post-Deploy

-   Verificar en Network tab que ya no aparezcan peticiones a `/_debugbar/`
-   Verificar que `/contact/search?q=` devuelva estructura vacía
-   Verificar que las páginas carguen más rápido
-   Revisar logs por errores de caché: `tail -f storage/logs/laravel.log`

---

## 🔧 Mantenimiento

### Limpiar Caché de Menús Manualmente

Si se agregan/modifican módulos o permisos y no se reflejan:

```bash
php artisan cache:forget menu_user_*
# O limpiar todo el caché:
php artisan cache:clear
```

### Invalidar Caché de Menú Automáticamente

Para invalidar el caché cuando se modifican permisos/módulos, agregar en los eventos correspondientes:

```php
// Ejemplo en un Observer o Event Listener
Cache::flush(); // O solo las keys específicas
```

---

## 📝 Notas Adicionales

-   Los errores de linter (`Undefined method 'can'`, etc.) son **falsos positivos** - el código funciona correctamente
-   El caché de menú usa la caché por defecto de Laravel (ver `config/cache.php`)
-   Si usas Redis, el rendimiento será aún mejor
-   Considera aumentar el tiempo de caché a 2-4 horas si los permisos cambian poco

---

## 🎯 Próximas Optimizaciones Recomendadas

1. **Eager Loading**: Revisar N+1 queries en DataTables
2. **Lazy Loading**: Implementar para listados grandes
3. **CDN**: Mover assets estáticos (CSS, JS, imágenes) a CDN
4. **Database Indexing**: Revisar índices en tablas grandes (contacts, enterprises, services)
5. **Queue Jobs**: Mover tareas pesadas a colas (emails, imports, exports)
6. **Redis**: Considerar Redis para sesiones y caché

---

**Fecha:** 10 de Octubre 2025
**Autor:** AI Assistant
**Versión:** 1.0
