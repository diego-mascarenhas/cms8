# Collaborators Seeding Guide

Esta guía explica cómo ejecutar el proceso de importación de colaboradores desde cero.

## 📋 Requisitos Previos

1. **Archivo SQL de colaboradores**: Debes tener el archivo SQL con los datos de los colaboradores en `/Users/magoo/Downloads/inserts_colaboradoras.sql`
2. **Base de datos configurada**: Laravel debe estar configurado con acceso a la base de datos
3. **Permisos**: El usuario de la base de datos debe tener permisos para crear/modificar tablas

## 🚀 Opciones de Ejecución

### Opción 1: Ejecutar Todo desde Cero (Recomendado)

Para ejecutar todos los seeders desde cero (incluyendo colaboradores):

```bash
# Resetear y ejecutar todos los seeders
php artisan migrate:fresh --seed
```

Este comando:
- ✅ Recreará todas las tablas
- ✅ Ejecutará todos los seeders en orden correcto
- ✅ Incluirá automáticamente los colaboradores al final

### Opción 2: Solo Colaboradores

Si ya tienes la base de datos configurada y solo quieres ejecutar la parte de colaboradores:

```bash
# Ejecutar solo colaboradores (sin borrar datos existentes)
php artisan seed:collaborators

# Ejecutar colaboradores desde cero (borrando datos existentes)
php artisan seed:collaborators --fresh
```

### Opción 3: Seeders Individuales

Para ejecutar seeders específicos en orden:

```bash
# 1. Idiomas base
php artisan db:seed --class=LanguageSeeder

# 2. Variantes de idiomas
php artisan db:seed --class=LanguageVariantSeeder

# 3. Colaboradores
php artisan db:seed --class=CollaboratorsSeeder
```

## 📊 Orden de Ejecución

El `DatabaseSeeder` ejecuta los seeders en este orden:

1. **Datos básicos**: Monedas, países, roles, etc.
2. **LanguageSeeder**: Idiomas base (es, en, fr, de, it, pt, ca, zh, ja, ko, ru, ar, etc.)
3. **LanguageVariantSeeder**: Variantes de idiomas (es-ES, en-US, fr-FR, etc.)
4. **ContactSeeder**: Contactos básicos
5. **CollaboratorsSeeder**: Colaboradores importados del SQL

## 🔧 Configuración del CollaboratorsSeeder

El `CollaboratorsSeeder` está configurado para:

- **Archivo SQL**: Lee `/Users/magoo/Downloads/inserts_colaboradoras.sql`
- **Equipo por defecto**: Usa el equipo con ID 1
- **Roles**: Asigna el rol "collaborator" a los usuarios creados
- **Combinaciones**: Procesa automáticamente las combinaciones de idiomas
- **Duplicados**: Previene la creación de contactos/usuarios duplicados

## 🎯 Datos Procesados

### Idiomas Base Soportados
- Spanish (es) - Español
- English (en) - Inglés
- French (fr) - Francés
- German (de) - Alemán
- Italian (it) - Italiano
- Portuguese (pt) - Português
- Catalan (ca) - Català
- Chinese (zh) - 中文
- Japanese (ja) - 日本語
- Korean (ko) - 한국어
- Russian (ru) - Русский
- Arabic (ar) - العربية
- Y muchos más...

### Variantes de Idiomas Soportadas
- **Spanish**: es-ES, es-MX, es-AR, es-CO, es-CL, es-PE, es-VE
- **English**: en-US, en-GB, en-CA, en-AU
- **French**: fr-FR, fr-CA, fr-BE, fr-CH
- **German**: de-DE, de-AT, de-CH
- **Italian**: it-IT, it-CH
- **Portuguese**: pt-PT, pt-BR
- **Catalan**: ca-ES, ca-AD
- Y muchas más...

## 📈 Resultados Esperados

Después de ejecutar el seeding completo, deberías tener:

- **~40 idiomas base** en la tabla `languages`
- **~50+ variantes de idiomas** en la tabla `language_variants`
- **~1400+ contactos colaboradores** en la tabla `contacts`
- **~300+ combinaciones de idiomas** en la tabla `contact_language_variants`
- **~1000+ usuarios colaboradores** en la tabla `users`

## 🐛 Solución de Problemas

### Error: "Cannot add or update a child row: a foreign key constraint fails"

**Causa**: Faltan idiomas base o variantes en la base de datos.

**Solución**: Ejecutar primero los seeders de idiomas:
```bash
php artisan db:seed --class=LanguageSeeder
php artisan db:seed --class=LanguageVariantSeeder
```

### Error: "File not found"

**Causa**: El archivo SQL no está en la ruta esperada.

**Solución**: Verificar que el archivo esté en `/Users/magoo/Downloads/inserts_colaboradoras.sql`

### Error: "Duplicate entry"

**Causa**: Los datos ya existen en la base de datos.

**Solución**: Usar la opción `--fresh` para limpiar datos existentes:
```bash
php artisan seed:collaborators --fresh
```

## 🔍 Verificación

Para verificar que todo se ejecutó correctamente:

```bash
# Verificar conteos
php artisan tinker --execute="
echo 'Languages: ' . App\Models\Language::count() . PHP_EOL;
echo 'Language Variants: ' . App\Models\LanguageVariant::count() . PHP_EOL;
echo 'Contacts: ' . App\Models\Contact::count() . PHP_EOL;
echo 'Language Combinations: ' . App\Models\ContactLanguageVariant::count() . PHP_EOL;
"
```

## 🎉 ¡Listo!

Una vez completado el proceso, tendrás todos los colaboradores importados con sus combinaciones de idiomas procesadas y listas para usar en el sistema. 