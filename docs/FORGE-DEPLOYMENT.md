# 🚀 Forge Deployment Script (Zero Downtime)

## Script para Laravel Forge con Zero Downtime

Usa **release directories** y cambio atómico del symlink (`$CREATE_RELEASE` / `$ACTIVATE_RELEASE`). La app sigue sirviendo desde el release anterior hasta que el nuevo está listo.

### 📋 Script para Forge Deploy (Zero Downtime)

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Install PHP dependencies
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install and build frontend assets
npm ci && npm run build

# Optimize Laravel
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link

# Run migrations (before activate: new release has correct schema when it goes live)
$FORGE_PHP artisan migrate --force
# $FORGE_PHP artisan migrate:fresh --seed
# $FORGE_PHP artisan db:seed --class=TeamHumanoSeeder

# Import data (uncomment if needed)
# $FORGE_PHP artisan import:interactive --auto

# Activate the new release (atomic symlink switch → zero downtime)
$ACTIVATE_RELEASE()

# Restart queue workers so they run the new code
$RESTART_QUEUES()

# --- WhatsApp Node service (Baileys) ---
# Use the release we just activated so PM2 runs the new code.
# Staging + production on same server: use unique PM2 name and PORT per site (see below).
WHATSAPP_DIR="$FORGE_RELEASE_DIRECTORY/whatsapp-service"
WHATSAPP_PM2_NAME="${WHATSAPP_PM2_NAME:-whatsapp-service-${APP_ENV:-production}}"
if [ -d "$WHATSAPP_DIR" ] && [ -f "$WHATSAPP_DIR/package.json" ]; then
  cd "$WHATSAPP_DIR"
  npm ci --production 2>/dev/null || npm install --production
  if command -v pm2 >/dev/null 2>&1; then
    pm2 reload "$WHATSAPP_PM2_NAME" --update-env 2>/dev/null || pm2 start server.js --name "$WHATSAPP_PM2_NAME"
    pm2 save 2>/dev/null || true
  else
    echo "PM2 not found. Install with: npm install -g pm2; pm2 startup"
  fi
  cd - >/dev/null
fi
```

### Staging y producción en el mismo servidor

Si staging y producción están en el mismo servidor, **cada sitio debe usar**:

| Concepto | Staging | Producción |
|----------|---------|------------|
| **Nombre PM2** | Distinto (ej. `whatsapp-service-staging`) | Distinto (ej. `whatsapp-service-production`) |
| **Puerto Node** | Distinto (ej. `3001`) | Distinto (ej. `3000`) |

- **Nombre PM2**: El script usa `WHATSAPP_PM2_NAME` si está definido, si no `whatsapp-service-${APP_ENV}`. En Forge → Site → Environment, define por sitio:
  - Staging: `WHATSAPP_PM2_NAME=whatsapp-service-staging` (o `APP_ENV=staging`).
  - Producción: `WHATSAPP_PM2_NAME=whatsapp-service-production` (o `APP_ENV=production`).
- **Puerto**: En cada sitio, en `whatsapp-service/.env` pon un `PORT` distinto (ej. staging `3001`, producción `3000`). En el `.env` de Laravel de cada sitio, `WHATSAPP_LOCAL_BASE_URL` debe apuntar a la URL/puerto correcto (ej. staging `https://staging.humano.com:3001` o `http://127.0.0.1:3001` según cómo expongas el servicio).

Si no separas nombre y puerto, los dos entornos compiten por el mismo proceso PM2 y el mismo puerto y uno falla o pisa al otro.

## 🔧 Cómo funciona el Zero Downtime

1. **`$CREATE_RELEASE()`** – Crea un nuevo directorio de release y clona el repo ahí.
2. **Todo el build** (composer, npm, optimize, migrate) se hace **dentro de ese release**; el tráfico sigue yendo al release anterior.
3. **`$ACTIVATE_RELEASE()`** – Cambia el symlink `current` al nuevo release de forma atómica. A partir de ahí el tráfico usa la nueva versión.
4. **`$RESTART_QUEUES()`** – Reinicia workers para que ejecuten el código del release recién activado.
5. **WhatsApp (PM2)** – Se usa `$FORGE_RELEASE_DIRECTORY/whatsapp-service` (el release que acabamos de activar) y `pm2 reload` para recargar sin caída.

### ✅ Ventajas

- **Sin maintenance mode** – No hace falta `artisan down` / `artisan up`.
- **Cambio atómico** – Un solo cambio de symlink; no ventana en la que la app esté “a medias”.
- **Migraciones antes de activar** – El nuevo código ve el esquema ya actualizado cuando pasa a ser el activo.

### ✅ Seguridad en producción

- **`migrate --force`** (nunca `migrate:fresh` en producción).
- Seeders e importaciones comentados; descomentar solo cuando haga falta.

## 🧪 Health check (opcional)

Si quieres un chequeo después del deploy, puedes añadirlo **antes** de `$ACTIVATE_RELEASE()`:

```bash
# Optional: run before $ACTIVATE_RELEASE()
# $FORGE_PHP artisan test --testsuite=Unit --filter=ProductionHealthTest || echo "Health check warnings"
```

## 📝 Notas

- **No usar `migrate:fresh`** en producción (borra datos).
- **WhatsApp**: La ruta usa `$FORGE_RELEASE_DIRECTORY` para que PM2 ejecute siempre el código del release activo.
- **Mismo servidor (staging + prod)**: Definir `WHATSAPP_PM2_NAME` (o `APP_ENV`) y `PORT` en `whatsapp-service/.env` distintos por sitio.
- En Forge, el rollback se hace desde el panel (volver a un release anterior); no hace falta script de rollback manual con `git reset`.
