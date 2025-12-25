# 🚀 Forge Deployment Script

## Script Optimizado para Laravel Forge

Este script está optimizado para evitar errores de Gateway 502/504 durante el despliegue.

### 📋 Script para Forge Deploy

```bash
cd /home/forge/mailer.revisionalpha.com

# Dev - Clean working directory
git reset --hard && git clean -df

# Put the application in maintenance mode FIRST
$FORGE_PHP artisan down --retry=60 --secret="forge-deploy-$(date +%s)"

# Pull latest changes
git pull origin $FORGE_SITE_BRANCH

# Install dependencies
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Run migrations and seeders
if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan db:seed --class=TeamRevisionAlphaSeeder --force
fi

# Clear and optimize caches (BEFORE PHP-FPM reload)
$FORGE_PHP artisan cache:clear
$FORGE_PHP artisan config:clear
$FORGE_PHP artisan route:clear
$FORGE_PHP artisan view:clear
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan optimize

# Create storage link and restart queues
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan queue:restart

# Wait for caches to be ready
sleep 2

# Reload PHP-FPM with proper timing
touch /tmp/fpmlock 2>/dev/null || true
( flock -w 10 9 || exit 1
    echo 'Reloading PHP FPM...'; sudo -S service $FORGE_PHP_FPM reload
    sleep 3
) 9</tmp/fpmlock

# Health check (optional)
$FORGE_PHP artisan test --testsuite=Unit --filter=ProductionHealthTest || echo "Health check warnings"

# Bring back online
$FORGE_PHP artisan up

echo "✅ Deployment completed successfully!"
```

## 🔧 Mejoras Clave

### ✅ **Orden Optimizado**
1. **Maintenance mode PRIMERO** - Evita requests durante deploy
2. **Caches ANTES de PHP-FPM reload** - Evita errores de Gateway
3. **Sleep después de reload** - Da tiempo a PHP-FPM para reiniciar

### ✅ **Seguridad en Producción**
- **`migrate` en lugar de `migrate:fresh`** - No borra datos existentes
- **`--retry=60`** en maintenance mode - Mejor experiencia de usuario
- **Secret token** para acceso durante maintenance

### ✅ **Prevención de Errores Gateway**
- **Caches preparados antes del reload** de PHP-FPM
- **Timeouts apropiados** con sleep
- **Locking mejorado** para evitar reloads concurrentes

## 🧪 Health Check

El script incluye un health check opcional usando unit tests:

```bash
# Para ejecutar manualmente
php artisan test --testsuite=Unit --filter=ProductionHealthTest
```

## 📝 Notas Importantes

- **No usar `migrate:fresh`** en producción (borra datos)
- **Siempre poner en maintenance mode** antes de cambios
- **Esperar después de PHP-FPM reload** para evitar Gateway errors
- **El secret token** permite acceso durante maintenance para debugging

## 🔄 Rollback Rápido

Si algo falla, puedes hacer rollback rápido:

```bash
cd /home/forge/mailer.revisionalpha.com
git reset --hard HEAD~1
$FORGE_COMPOSER install --no-dev --optimize-autoloader
$FORGE_PHP artisan optimize:clear
$FORGE_PHP artisan optimize
sudo service $FORGE_PHP_FPM reload
$FORGE_PHP artisan up
```
