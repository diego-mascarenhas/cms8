# SEO & Meta Tags - Social Media Previews

## Change summary

All project meta descriptions were updated to improve presentation on social networks (WhatsApp, Facebook, Twitter, LinkedIn, etc.) and SEO.

---

## Main site description

**File**: `config/variables.php`

### Before:
```
'templateDescription' => 'Start your development with a Dashboard for Bootstrap 5'
```

### Now:
*(Spanish locale app string example — English equivalent: "Complete professional CRM. Manage contacts, projects, billing, and communications on one platform.")*
```
'templateDescription' => 'Sistema de gestión de relaciones con clientes (CRM) completo y profesional. Gestiona contactos, proyectos, facturación y comunicaciones en una sola plataforma.'
```

**Updated keywords:**
*(Spanish locale app string example — English equivalent: "crm, customer management, contacts, projects, billing, laravel, dashboard")*
```
'templateKeyword' => 'crm, gestión de clientes, contactos, proyectos, facturación, laravel, dashboard'
```

---

## Open Graph meta tags (WhatsApp, Facebook)

**File**: `resources/views/layouts/commonMaster.blade.php`

The following meta tags were added to improve previews when sharing URLs:

```html
<!-- Open Graph / Facebook / WhatsApp -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="@yield('title') | {{ config('variables.templateName') }}" />
<meta property="og:description" content="{{ config('variables.templateDescription') }}" />
<meta property="og:image" content="{{ asset('assets/logo.png') }}" />
<meta property="og:site_name" content="{{ config('variables.templateName') }}" />
```

**What each tag does:**
- `og:type`: Content type (website)
- `og:url`: Current page URL
- `og:title`: Title shown in the preview
- `og:description`: Description shown in the preview
- `og:image`: Image/logo shown in the preview
- `og:site_name`: Site name

---

## Twitter Card

Twitter-specific meta tags were added:

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

## Error pages

*(The "Before" / "Now" strings below are Spanish locale app content as stored in the views.)*

### 404 - Page not found
**File**: `resources/views/errors/404.blade.php`

**Before**: `"Estamos trabajando en esta funcionalidad"`  
*(English: "We are working on this feature")*

**Now**: `"Página no encontrada - La página que buscas no existe o ha sido movida."`  
*(English: "Page not found - The page you are looking for does not exist or has been moved.")*

### 403 - Access denied
**File**: `resources/views/errors/403.blade.php`

**Before**: `"No tienes permisos para acceder a esta página"`  
*(English: "You do not have permission to access this page")*

**Now**: `"Acceso no autorizado - No tienes los permisos necesarios para acceder a esta página."`  
*(English: "Unauthorized access - You do not have the required permissions to access this page.")*

### 503 - Maintenance
**File**: `resources/views/errors/503.blade.php`

**Before**: `"Sitio en mantenimiento"`  
*(English: "Site under maintenance")*

**Now**: `"Mantenimiento programado - Estamos mejorando nuestros servicios. Volveremos pronto."`  
*(English: "Scheduled maintenance - We are improving our services. We will be back soon.")*

---

## Preview image

**Current**: `assets/logo.png`

### Recommendations:
To improve social media previews, consider creating a dedicated image:

**Recommended dimensions:**
- **WhatsApp/Facebook**: 1200 x 630 px
- **Twitter**: 1200 x 675 px (16:9)
- **LinkedIn**: 1200 x 627 px

**Suggested location**: `public/assets/img/og-image.png`

**Update in**: `config/variables.php`
```php
'ogImage' => 'assets/img/og-image.png',
```

And in `commonMaster.blade.php`:
```html
<meta property="og:image" content="{{ asset(config('variables.ogImage', 'assets/logo.png')) }}" />
```

---

## How to test previews

### 1. WhatsApp
- Copy your site URL
- Paste it into a WhatsApp chat
- You will see the preview before sending

### 2. Facebook Debugger
https://developers.facebook.com/tools/debug/

### 3. Twitter Card Validator
https://cards-dev.twitter.com/validator

### 4. LinkedIn Post Inspector
https://www.linkedin.com/post-inspector/

---

## Full SEO structure

Each page now includes:

- **Title**: Dynamic per page
- **Description**: CRM description
- **Keywords**: Relevant keywords
- **Canonical**: Canonical URL
- **Favicon**: Project logo
- **Open Graph**: For social networks
- **Twitter Card**: For Twitter
- **CSRF Token**: Laravel security

---

## Expected result

When you share an application URL on WhatsApp, you will now see:

*(Preview text below reflects Spanish locale content; English equivalent: "Page Title | HUMANO" and the CRM description.)*

```
┌────────────────────────────────────┐
│  [LOGO]                            │
│                                    │
│  Page Title | HUMANO               │
│                                    │
│  Complete professional CRM.        │
│  Manage contacts, projects,        │
│  billing, and communications...    │
│                                    │
│  humano.test                       │
└────────────────────────────────────┘
```

Instead of:
```
"Start your development with a Dashboard for Bootstrap 5"
```

---

## Cache

After these changes, run:

```bash
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

**Important note**: Social networks cache previews. If you shared a URL before these changes, use the debuggers listed above to force a cache refresh.

---

## Benefits

- **Better SEO**: Clear descriptions and relevant keywords
- **Professional previews**: On WhatsApp, Facebook, Twitter, LinkedIn
- **Higher CTR**: Attractive previews drive more clicks
- **Consistent branding**: Unified logo and description
- **Improved user experience**: Clear information from the preview

---

## Maintenance

### To update the general description:
Edit: `config/variables.php` → `templateDescription`

### To update the preview image:
Replace: `public/assets/logo.png`
Or use a dedicated image at `public/assets/img/og-image.png`

### For pages with a custom description:
In your Blade view, before `@extends`:
```blade
@section('metaDescription', 'Custom description for this page')
```

And update `commonMaster.blade.php` to support it:
```html
<meta name="description" content="@yield('metaDescription', config('variables.templateDescription'))" />
```
