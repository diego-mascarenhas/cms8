# Logo system

This document describes how logos are configured, named, and resolved in the application, including fallbacks when files are missing.

## File names and location

All logo assets live under `public/assets/` and follow this naming.

**Important:** `dark` / `light` refer to the **UI theme** (same idea as Vuexy `light-style` / `dark-style`), not the ink color of the SVG.

### Full logo (auth, headers, sidebar)

| File | Use |
|------|-----|
| `logo.svg` | Fallback when a variant is missing |
| `logo-light.svg` | Logo for **light** backgrounds (typically dark ink) |
| `logo-dark.svg` | Logo for **dark** backgrounds (typically light / white ink) |

### Iso / small logo (favicon, collapsed sidebar, small contexts)

| File | Use |
|------|-----|
| `logo-iso.svg` | Fallback when an iso variant is missing |
| `logo-iso-light.svg` | Iso for light backgrounds |
| `logo-iso-dark.svg` | Iso for dark backgrounds |

## Configuration

Logo paths and fallbacks are in `config/variables.php` under the `logo` key:

- `path` — Legacy full logo (e.g. PNG); kept for backward compatibility.
- `path_light` — Full logo for light UI (default: `assets/logo-light.svg`).
- `path_dark` — Full logo for dark UI (default: `assets/logo-dark.svg`).
- `path_iso` — Iso default (default: `assets/logo-iso.svg`).
- `path_iso_light` — Iso for light UI (default: `assets/logo-iso-light.svg`).
- `path_iso_dark` — Iso for dark UI (default: `assets/logo-iso-dark.svg`).
- `fallback` — Fallback for full logo (default: `assets/logo.svg`).
- `iso_fallback` — Fallback for iso (default: `assets/logo-iso.svg`).
- `budget_path` — Optional override for budget/quote logos (`APP_LOGO_BUDGET_PATH`). Empty = same as `path_light` (menu light logo) via `Helpers::budgetLogoAsset()`.

You can override any path via `.env` (e.g. `APP_LOGO_PATH_LIGHT`, `APP_LOGO_PATH_DARK`, `APP_LOGO_FALLBACK`).

## Resolving logos: existence and fallback

The app does **not** assume every variant file exists. It resolves the URL like this:

1. Use the configured path for the requested variant (e.g. `logo-light.svg` for `light`).
2. If that file exists under `public/`, serve it.
3. If that file does not exist, serve the appropriate fallback:
   - For full logo variants (`dark`, `light`): `logo.svg`.
   - For iso variants (`iso`, `iso_dark`, `iso_light`): `logo-iso.svg`.

This is done in code via the helper (see below), so you can ship only the files you have (e.g. only `logo.svg` and `logo-iso.svg`) and the rest will fall back automatically.

## Helper: `Helper::logoAsset($variant)`

Defined in `App\Helpers\Helpers`. Returns the **URL** (via `asset()`) of the logo for the given variant, using the existence check and fallback above.

**Variants:**

- `light` — Full logo for light backgrounds (e.g. auth, sidebar in light mode).
- `dark` — Full logo for dark backgrounds (e.g. dark landings).
- `iso` — Iso/small logo (generic).
- `iso_light` — Iso for light backgrounds.
- `iso_dark` — Iso for dark backgrounds.

**Theme-aware helper:**

```blade
<img src="{{ Helper::logoAssetForStyle($configData['style'] ?? 'light') }}" alt="{{ config('app.name') }}" height="40" style="width: auto;">
```

**Example (iso in SVG):**

```blade
<image href="{{ Helper::logoAsset('iso') }}" ... />
```

## Where each logo is used

- **Auth / sidebar / invoices (light UI)** — `Helper::logoAsset('light')` or `Helper::logoAssetForStyle(...)`.
- **Dark landings / newsletters** — `Helper::logoAsset('dark')`.
- **Small/iso logo (collapsed sidebar, favicon)** — `resources/views/_partials/macros.blade.php` / favicon partial use `Helper::logoAsset('iso')`.

## Summary

- **Naming:** theme-based — `logo-light.svg` for light UI, `logo-dark.svg` for dark UI.
- **Config:** `config/variables.php` → `logo` (paths + fallbacks); optional overrides in `.env`.
- **Resolution:** Helper checks if the variant file exists; if not, uses `logo.svg` or `logo-iso.svg` as fallback.
- **Usage:** Prefer `Helper::logoAssetForStyle()` in layouts; use `Helper::logoAsset($variant)` when the background is known.
