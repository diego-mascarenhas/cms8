# 📧 Newsletter System - Guía de Implementación

## ✅ Estado Actual del Sistema

El sistema de newsletter **está completamente funcional** y incluye:

- ✅ **Queue Jobs**: Envío asíncrono con `SendMessageCampaignJob`
- ✅ **Tracking**: Pixel de apertura y enlaces rastreados
- ✅ **UI Dinámica**: Livewire para stats y deliveries en tiempo real
- ✅ **Responsive**: Layout adaptativo para móvil y desktop
- ✅ **SweetAlert2**: Confirmaciones elegantes para start/pause
- ✅ **Error Handling**: Reintentos automáticos y logging

---

## 🧪 **Pruebas Locales**

### 1. **Verificar Queue Worker**
```bash
# Iniciar el worker de colas (en terminal separado)
php artisan queue:work --queue=mailer

# O para todas las colas
php artisan queue:work
```

### 2. **Verificar Configuración de Email**
```bash
# Revisar configuración actual
php artisan config:show mail

# Para testing local, usar mail trap o log
# En .env:
MAIL_MAILER=log
# o
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=tu_username
MAIL_PASSWORD=tu_password
```

### 3. **Crear y Enviar Campaña de Prueba**
```bash
# 1. Ejecutar seeder para datos demo
php artisan db:seed --class=TeamDemoSeeder

# 2. Ir a la interfaz web
# https://humano.test/message/2

# 3. Hacer clic en "Send" para iniciar campaña
```

### 4. **Monitorear el Proceso**
```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Ver jobs pendientes en BD
php artisan tinker
>>> DB::table('jobs')->count()
>>> DB::table('jobs')->get()

# Ver deliveries
>>> App\Models\MessageDelivery::where('message_id', 2)->get()
```

### 5. **Comandos de Testing**
```bash
# Actualizar stats manualmente
php artisan app:update-message-stats

# Simular stats para testing
php artisan app:simulate-delivery-stats 2

# Verificar tracking
# Abrir: https://humano.test/track/delivery/{token}
```

---

## 🚀 **Implementación en Producción**

### 1. **Configuración del Servidor**

#### **A. Configurar Queue Worker como Servicio**
```bash
# Crear archivo supervisor
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/humano/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --queue=mailer
directory=/path/to/humano
autostart=true
autorestart=true
startsecs=1
startretries=3
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/humano/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Actualizar supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

#### **B. Configurar Cron para Comandos**
```bash
# Editar crontab
crontab -e

# Agregar líneas:
* * * * * cd /path/to/humano && php artisan schedule:run >> /dev/null 2>&1
*/5 * * * * cd /path/to/humano && php artisan app:update-message-stats >> /dev/null 2>&1
```

### 2. **Variables de Entorno (.env)**
```env
# Queue Configuration
QUEUE_CONNECTION=database

# Email Configuration (producción)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=tu_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Tu Empresa"

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info
```

### 3. **Optimizaciones de Producción**
```bash
# Cache de configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimización de autoloader
composer install --optimize-autoloader --no-dev

# Permisos
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 4. **Monitoreo en Producción**

#### **A. Logs importantes**
```bash
# Worker logs
tail -f /path/to/humano/storage/logs/worker.log

# Laravel logs
tail -f /path/to/humano/storage/logs/laravel-$(date +%Y-%m-%d).log

# Failed jobs
php artisan queue:failed
```

#### **B. Comandos de mantenimiento**
```bash
# Limpiar jobs antiguos
php artisan queue:prune-batches --hours=48

# Reiniciar workers después de deploy
php artisan queue:restart

# Ver estadísticas
php artisan queue:monitor
```

---

## 📊 **Flujo Completo del Sistema**

### 1. **Creación de Campaña**
- ✅ Crear template en GrapesJS: `/template/create`
- ✅ Crear mensaje: `/message/create`
- ✅ Seleccionar categoría de contactos

### 2. **Envío de Campaña**
- ✅ Click "Send" → `startCampaign()`
- ✅ Populate deliveries → `populateMessageDeliveries()`
- ✅ Dispatch jobs → `SendMessageCampaignJob::dispatch()`
- ✅ Jobs ejecutados en queue `mailer`

### 3. **Tracking y Estadísticas**
- ✅ Email enviado con pixel tracking
- ✅ Links wrapped para tracking de clicks
- ✅ Stats actualizadas en tiempo real (Livewire)
- ✅ Logs detallados de todo el proceso

### 4. **Monitoreo en Tiempo Real**
- ✅ UI responsive con auto-refresh cada 5s
- ✅ Tabla de deliveries dinámica
- ✅ Botones Start/Pause con SweetAlert2
- ✅ Logs en `/storage/logs/laravel.log`

---

## 🔧 **Comandos Útiles**

```bash
# Testing local
php artisan queue:work --queue=mailer --verbose
php artisan db:seed --class=TeamDemoSeeder

# Producción
php artisan queue:restart
php artisan config:cache
php artisan app:update-message-stats

# Debug
php artisan queue:failed
php artisan queue:retry all
tail -f storage/logs/laravel.log
```

## ✨ **¡El sistema está listo para producción!**

Solo necesitas:
1. ✅ Configurar el queue worker en servidor
2. ✅ Configurar SMTP/SendGrid en `.env`
3. ✅ Ejecutar las migraciones y seeders
4. ✅ ¡Empezar a enviar campañas!
