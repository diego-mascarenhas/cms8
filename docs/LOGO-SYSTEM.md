# Logo system

This document describes how logos are configured, named, and resolved in the application, including fallbacks when files are missing.

## File names and location

All logo assets live under `public/assets/` and follow this naming:

### Full logo (auth, headers)

| File | Use |
|------|-----|
| `logo.svg` | Fallback when a variant is missing |
| `logo-dark.svg` | Dark logo on light backgrounds (e.g. auth) |
| `logo-light.svg` | Light logo on dark backgrounds |

### Iso / small logo (favicon, sidebar, small contexts)

| File | Use |
|------|-----|
| `logo-iso.svg` | Fallback when an iso variant is missing |
| `logo-iso-dark.svg` | Iso on light backgrounds |
| `logo-iso-light.svg` | Iso on dark backgrounds |

## Configuration

Logo paths and fallbacks are in `config/variables.php` under the `logo` key:

- `path` — Legacy full logo (e.g. PNG); kept for backward compatibility.
- `path_dark` — Full logo dark variant (default: `assets/logo-dark.svg`).
- `path_light` — Full logo light variant (default: `assets/logo-light.svg`).
- `path_iso` — Iso default (default: `assets/logo-iso.svg`).
- `path_iso_dark` — Iso dark variant (default: `assets/logo-iso-dark.svg`).
- `path_iso_light` — Iso light variant (default: `assets/logo-iso-light.svg`).
- `fallback` — Fallback for full logo (default: `assets/logo.svg`).
- `iso_fallback` — Fallback for iso (default: `assets/logo-iso.svg`).

You can override any path via `.env` (e.g. `APP_LOGO_PATH_DARK`, `APP_LOGO_PATH_ISO`, `APP_LOGO_FALLBACK`, `APP_LOGO_ISO_FALLBACK`).

## Resolving logos: existence and fallback

The app does **not** assume every variant file exists. It resolves the URL like this:

1. Use the configured path for the requested variant (e.g. `logo-dark.svg` for `dark`).
2. If that file exists under `public/`, serve it.
3. If it does not exist, serve the appropriate fallback:
   - For full logo variants (`dark`, `light`): `logo.svg`.
   - For iso variants (`iso`, `iso_dark`, `iso_light`): `logo-iso.svg`.

This is done in code via the helper (see below), so you can ship only the files you have (e.g. only `logo.svg` and `logo-iso.svg`) and the rest will fall back automatically.

## Helper: `Helper::logoAsset($variant)`

Defined in `App\Helpers\Helpers`. Returns the **URL** (via `asset()`) of the logo for the given variant, using the existence check and fallback above.

**Variants:**

- `dark` — Full logo for light backgrounds (e.g. auth pages).
- `light` — Full logo for dark backgrounds.
- `iso` — Iso/small logo (generic).
- `iso_dark` — Iso for light backgrounds.
- `iso_light` — Iso for dark backgrounds.

**Example (Blade):**

```blade
<img src="{{ Helper::logoAsset('dark') }}" alt="{{ config('app.name') }}" height="40" style="width: auto;">
```

**Example (iso in SVG):**

```blade
<image href="{{ Helper::logoAsset('iso') }}" ... />
```

## Where each logo is used

- **Auth (login, register, etc.)** — Full logo: `resources/views/auth/partials/logo-full.blade.php` uses `Helper::logoAsset('dark')`.
- **Small/iso logo (sidebar, etc.)** — `resources/views/_partials/macros.blade.php` uses `Helper::logoAsset('iso')` for the small logo image.

To use dark/light by theme, pass the right variant (e.g. `Helper::logoAsset('iso_dark')` or `Helper::logoAsset('iso_light')`) from your layout or partial depending on the current theme.

## Summary

- **Naming:** `logo.svg`, `logo-dark.svg`, `logo-light.svg` (full); `logo-iso.svg`, `logo-iso-dark.svg`, `logo-iso-light.svg` (iso).
- **Config:** `config/variables.php` → `logo` (paths + fallbacks); optional overrides in `.env`.
- **Resolution:** Helper checks if the variant file exists; if not, uses `logo.svg` or `logo-iso.svg` as fallback.
- **Usage:** Always use `Helper::logoAsset($variant)` in views so fallbacks work; do not use `asset(config('variables.logo.path_*'))` directly for these logos.
