# Forge Deployment Script (Zero Downtime)

## Script for Laravel Forge with zero downtime

Uses **release directories** and an atomic symlink switch (`$CREATE_RELEASE` / `$ACTIVATE_RELEASE`). The app keeps serving from the previous release until the new one is ready.

### Forge deploy script (zero downtime)

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

### Staging and production on the same server

If staging and production share the same server, **each site must use**:

| Concept | Staging | Production |
|---------|---------|------------|
| **PM2 name** | Distinct (e.g. `whatsapp-service-staging`) | Distinct (e.g. `whatsapp-service-production`) |
| **Node port** | Distinct (e.g. `3001`) | Distinct (e.g. `3000`) |

- **PM2 name**: The script uses `WHATSAPP_PM2_NAME` if defined; otherwise `whatsapp-service-${APP_ENV}`. In Forge → Site → Environment, set per site:
  - Staging: `WHATSAPP_PM2_NAME=whatsapp-service-staging` (or `APP_ENV=staging`).
  - Production: `WHATSAPP_PM2_NAME=whatsapp-service-production` (or `APP_ENV=production`).
- **Port**: In each site's `whatsapp-service/.env`, set a distinct `PORT` (e.g. staging `3001`, production `3000`). In each site's Laravel `.env`, `WHATSAPP_LOCAL_BASE_URL` must point to the correct URL/port (e.g. staging `https://staging.humano.com:3001` or `http://127.0.0.1:3001`, depending on how you expose the service).

If you do not separate name and port, both environments compete for the same PM2 process and port, and one will fail or overwrite the other.

## How zero downtime works

1. **`$CREATE_RELEASE()`** — Creates a new release directory and clones the repo there.
2. **The full build** (composer, npm, optimize, migrate) runs **inside that release**; traffic continues to the previous release.
3. **`$ACTIVATE_RELEASE()`** — Atomically switches the `current` symlink to the new release. From that point, traffic uses the new version.
4. **`$RESTART_QUEUES()`** — Restarts workers so they run code from the newly activated release.
5. **WhatsApp (PM2)** — Uses `$FORGE_RELEASE_DIRECTORY/whatsapp-service` (the release just activated) and `pm2 reload` for a no-downtime reload.

### Advantages

- **No maintenance mode** — No need for `artisan down` / `artisan up`.
- **Atomic switch** — A single symlink change; no half-deployed window.
- **Migrations before activation** — New code sees the updated schema when it becomes active.

### Production safety

- **`migrate --force`** (never `migrate:fresh` in production).
- Seeders and imports remain commented; uncomment only when needed.

## Health check (optional)

If you want a check after deploy, you can add it **before** `$ACTIVATE_RELEASE()`:

```bash
# Optional: run before $ACTIVATE_RELEASE()
# $FORGE_PHP artisan test --testsuite=Unit --filter=ProductionHealthTest || echo "Health check warnings"
```

## Notes

- **Do not use `migrate:fresh`** in production (it deletes data).
- **WhatsApp**: The path uses `$FORGE_RELEASE_DIRECTORY` so PM2 always runs code from the active release.
- **Same server (staging + prod)**: Define distinct `WHATSAPP_PM2_NAME` (or `APP_ENV`) and `PORT` in `whatsapp-service/.env` per site.
- In Forge, rollback is done from the panel (return to a previous release); no manual rollback script with `git reset` is required.
