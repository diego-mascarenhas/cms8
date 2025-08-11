# Importación de Productos desde CMS7

Este documento explica cómo importar productos desde la base de datos CMS7 usando el comando `import:interactive`.

> **Nota**: La funcionalidad de importación de productos ha sido integrada en el importador general `import:interactive` para mejor organización y consistencia.

## Configuración Previa

### 1. Configurar conexión a CMS7

Agregar las siguientes variables al archivo `.env`:

```env
# Conexión a base de datos CMS7
CMS7_DB_HOST=tu_host_cms7
CMS7_DB_PORT=3306
CMS7_DB_DATABASE=cms7
CMS7_DB_USERNAME=tu_usuario
CMS7_DB_PASSWORD=tu_password
```

### 2. Verificar que existan las categorías

Ejecutar el seeder para crear las categorías necesarias:

```bash
php artisan db:seed --class=ProductCategoriesSeeder
```

## Uso del Comando

### Sintaxis

```bash
php artisan import:interactive
```

### Proceso de Importación

1. **Ejecutar el comando**:
   ```bash
   php artisan import:interactive
   ```

2. **Seleccionar "11. Products (CMS7)"** del menú principal

3. **Elegir acción**:
   - **Preview All**: Ver productos que se importarían
   - **Preview Specific ID**: Ver un producto específico por ID
   - **Import All**: Importar todos los productos del grupo CMS
   - **Import Specific ID**: Importar un producto específico

### Importación Automática

Para importación automática (sin menú interactivo):

```bash
php artisan import:interactive --auto
```

> **Nota**: El modo automático importa empresas y contactos. Para productos, usar el modo interactivo.

## Proceso de Importación

### 1. Datos que se importan

El comando importa registros de la tabla `categorias_generales` donde:
- `grupo` = el valor especificado
- `padre` IS NULL (solo categorías padre)
- `estado` = 1 (activos)

### 2. Mapeo de campos

| CMS7 Campo | Producto Campo | Notas |
|------------|----------------|-------|
| `categoria` | `name` | Nombre del producto |
| `descripcion` + `caracteristicas` | `description` | Descripción combinada |
| `valor` | `price` | Precio del producto |
| `id_moneda` | `currency_id` | Mapeado a monedas existentes |
| `estado` | `status` | Convertido a boolean |
| - | `whatsapp_enabled` | Siempre true |
| - | `team_id` | Según parámetro |

### 3. Mapeo de monedas

El comando mapea las monedas de CMS7 de la siguiente manera:

| CMS7 id_moneda | Código Moneda |
|----------------|---------------|
| 1 | USD |
| 2 | EUR |
| 3 | ARS |

### 4. Categorías

Los productos se asignan a la categoría "Productos" del equipo especificado. Si no existe, se crea automáticamente.

## Resultados

### Salida del comando

El comando muestra:
- Productos encontrados para importar
- Productos importados exitosamente
- Productos omitidos (duplicados)
- Errores durante la importación
- Resumen final

### Ejemplo de salida

```
🚀 Starting product import from CMS7
📊 Parameters:
   - Grupo: 501
   - Team ID: 1
   - Dry Run: No

📦 Found 15 products to import

✅ Imported: Hosting Web Básico
✅ Imported: Dominio .com
⏭️ Skipped (already exists): SSL Certificate

📊 Import Summary:
   - Products processed: 15
   - Successfully imported: 13
   - Skipped (duplicates): 2
   - Errors: 0
```

## Verificación Post-Importación

### 1. Verificar productos importados

```bash
php artisan tinker --execute="
use App\Models\Product;
echo 'Productos del Team 1: ' . Product::where('team_id', 1)->count() . PHP_EOL;
foreach(Product::where('team_id', 1)->latest()->limit(5)->get() as \$p) {
    echo '- ' . \$p->name . ' (' . \$p->price . ' ' . \$p->currency->code . ')' . PHP_EOL;
}
"
```

### 2. Probar catálogo WhatsApp

```bash
php artisan test:products +1234567890
```

### 3. Verificar importación desde el menú

```bash
php artisan import:interactive
# Seleccionar: 11. Products (CMS7)
# Seleccionar: 1. Preview All
```

## Solución de Problemas

### Error de conexión a CMS7

1. Verificar variables de entorno en `.env`
2. Verificar conectividad a la base de datos
3. Verificar permisos del usuario de base de datos

### Productos duplicados

El comando omite automáticamente productos que ya existen (mismo nombre y team_id).

### Errores de moneda

Si una moneda no existe, el comando usa USD por defecto y la crea si es necesario.

### Errores de categoría

Si no existe una categoría apropiada, el comando crea "Productos Importados" automáticamente.

## Logs

Los errores se registran en:
- Laravel log: `storage/logs/laravel.log`
- Contexto: "CMS7 product import"

## Consideraciones

### Rendimiento

- El comando procesa productos uno por uno
- Para grandes volúmenes (>1000 productos), considerar ejecutar en horarios de baja actividad
- Usar `--dry-run` primero para estimar tiempo

### Duplicados

- Los productos se consideran duplicados por `name` y `team_id`
- No se actualizan productos existentes, solo se omiten

### Datos faltantes

- Si `valor` es NULL, se asigna precio 0.00
- Si `descripcion` está vacía, se usa solo el nombre
- Si `caracteristicas` existe, se agrega a la descripción

## Mantenimiento

### Agregar nuevas monedas

Editar el array `$currencyMap` en el método `getCurrencyForProduct()` del comando.

### Cambiar categoría por defecto

Editar el método `getCategoryForProduct()` del comando.

### Personalizar descripción

Editar el método `buildProductDescription()` del comando.
