# SEO & Meta Tags - Social Media Previews

## 📋 Resumen de Cambios

Se han actualizado todas las meta descripciones del proyecto para mejorar la presentación en redes sociales (WhatsApp, Facebook, Twitter, LinkedIn, etc.) y SEO.

---

## ✅ Descripción Principal del Sitio

**Archivo**: `config/variables.php`

### Antes:
```
'templateDescription' => 'Start your development with a Dashboard for Bootstrap 5'
```

### Ahora:
```
'templateDescription' => 'Sistema de gestión de relaciones con clientes (CRM) completo y profesional. Gestiona contactos, proyectos, facturación y comunicaciones en una sola plataforma.'
```

**Keywords Actualizados:**
```
'templateKeyword' => 'crm, gestión de clientes, contactos, proyectos, facturación, laravel, dashboard'
```

---

## 🌐 Meta Tags Open Graph (WhatsApp, Facebook)

**Archivo**: `resources/views/layouts/commonMaster.blade.php`

Se agregaron las siguientes meta tags para mejorar las previsualizaciones al compartir URLs:

```html
<!-- Open Graph / Facebook / WhatsApp -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="@yield('title') | {{ config('variables.templateName') }}" />
<meta property="og:description" content="{{ config('variables.templateDescription') }}" />
<meta property="og:image" content="{{ asset('assets/logo.png') }}" />
<meta property="og:site_name" content="{{ config('variables.templateName') }}" />
```

**¿Qué hace cada tag?**
- `og:type`: Define el tipo de contenido (website)
- `og:url`: URL actual de la página
- `og:title`: Título que aparecerá en el preview
- `og:description`: Descripción que aparecerá en el preview
- `og:image`: Imagen/logo que aparecerá en el preview
- `og:site_name`: Nombre del sitio

---

## 🐦 Twitter Card

Se agregaron meta tags específicas para Twitter:

```html
<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:url" content="{{ url()->current() }}" />
<meta name="twitter:title" content="@yield('title') | {{ config('variables.templateName') }}" />
<meta name="twitter:description" content="{{ config('variables.templateDescription') }}" />
<meta name="twitter:image" content="{{ asset('assets/logo.png') }}" />
<meta name="twitter:site" content="{{ config('variables.twitterUrl') }}" />
```

---

## 🚨 Páginas de Error

### 404 - Página No Encontrada
**Archivo**: `resources/views/errors/404.blade.php`

**Antes**: `"Estamos trabajando en esta funcionalidad"`
**Ahora**: `"Página no encontrada - La página que buscas no existe o ha sido movida."`

### 403 - Acceso Denegado
**Archivo**: `resources/views/errors/403.blade.php`

**Antes**: `"No tienes permisos para acceder a esta página"`
**Ahora**: `"Acceso no autorizado - No tienes los permisos necesarios para acceder a esta página."`

### 503 - Mantenimiento
**Archivo**: `resources/views/errors/503.blade.php`

**Antes**: `"Sitio en mantenimiento"`
**Ahora**: `"Mantenimiento programado - Estamos mejorando nuestros servicios. Volveremos pronto."`

---

## 🖼️ Imagen para Previsualizaciones

**Actual**: `assets/logo.png`

### Recomendaciones:
Para mejorar las previsualizaciones en redes sociales, considera crear una imagen específica:

**Dimensiones recomendadas:**
- **WhatsApp/Facebook**: 1200 x 630 px
- **Twitter**: 1200 x 675 px (16:9)
- **LinkedIn**: 1200 x 627 px

**Ubicación sugerida**: `public/assets/img/og-image.png`

**Actualizar en**: `config/variables.php`
```php
'ogImage' => 'assets/img/og-image.png',
```

Y en `commonMaster.blade.php`:
```html
<meta property="og:image" content="{{ asset(config('variables.ogImage', 'assets/logo.png')) }}" />
```

---

## 🧪 Cómo Probar las Previsualizaciones

### 1. **WhatsApp**
- Copia la URL de tu sitio
- Pégala en un chat de WhatsApp
- Verás el preview antes de enviar

### 2. **Facebook Debugger**
https://developers.facebook.com/tools/debug/

### 3. **Twitter Card Validator**
https://cards-dev.twitter.com/validator

### 4. **LinkedIn Post Inspector**
https://www.linkedin.com/post-inspector/

---

## 📝 Estructura SEO Completa

Cada página ahora incluye:

✅ **Title**: Dinámico según la página  
✅ **Description**: Descripción del CRM  
✅ **Keywords**: Palabras clave relevantes  
✅ **Canonical**: URL canónica  
✅ **Favicon**: Logo del proyecto  
✅ **Open Graph**: Para redes sociales  
✅ **Twitter Card**: Para Twitter  
✅ **CSRF Token**: Seguridad Laravel  

---

## 🎯 Resultado Esperado

Cuando compartas una URL de tu aplicación por WhatsApp, ahora verás:

```
┌────────────────────────────────────┐
│  [LOGO]                            │
│                                    │
│  Título de la Página | HUMANO     │
│                                    │
│  Sistema de gestión de relaciones │
│  con clientes (CRM) completo y    │
│  profesional. Gestiona contactos, │
│  proyectos, facturación y...      │
│                                    │
│  humano.test                       │
└────────────────────────────────────┘
```

En lugar de:
```
"Start your development with a Dashboard for Bootstrap 5"
```

---

## 🔄 Caché

Después de estos cambios, ejecuta:

```bash
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

**Nota importante**: Las redes sociales cachean las previsualizaciones. Si compartes una URL antes de estos cambios, usa los debuggers mencionados arriba para forzar la actualización del caché.

---

## 📊 Beneficios

✅ **Mejor SEO**: Descripciones claras y keywords relevantes  
✅ **Previsualizaciones profesionales**: En WhatsApp, Facebook, Twitter, LinkedIn  
✅ **Mayor CTR**: Previews atractivos generan más clics  
✅ **Branding consistente**: Logo y descripción unificados  
✅ **Experiencia de usuario mejorada**: Información clara desde el preview  

---

## 🔧 Mantenimiento

### Para actualizar la descripción general:
Edita: `config/variables.php` → `templateDescription`

### Para actualizar la imagen de preview:
Reemplaza: `public/assets/logo.png`  
O usa una imagen dedicada en `public/assets/img/og-image.png`

### Para páginas específicas con descripción personalizada:
En tu blade, antes del `@extends`:
```blade
@section('metaDescription', 'Descripción personalizada para esta página')
```

Y actualiza `commonMaster.blade.php` para soportarlo:
```html
<meta name="description" content="@yield('metaDescription', config('variables.templateDescription'))" />
```
