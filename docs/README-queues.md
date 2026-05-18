# Colas (Queues) de Humano

Guía de colas, `.env`, workers en desarrollo/producción y recuperación de fallos.

## 1) Colas por tipo

### Correo (worker dedicado)

| Cola | Job | Uso |
|------|-----|-----|
| `task-communications` | `SendTaskCommunication` | Emails desde el Kanban |
| `notifications` | `SendNotificationJob` | Notificaciones a contactos |
| `mailer` | `SendMessageCampaignJob` | Mensajes, reenvíos, pruebas |
| `campaign` | `SendMessageCampaignJob` | Envíos masivos de campaña |

Configuración de colas de campaña: `config/message_delivery_dispatch.php`.

### General (otro worker)

| Cola | Job / uso |
|------|-----------|
| `default` | Jobs sin cola explícita |
| `domain-info` | `UpdateDomainInfo`, `UpdateServerDomainInfo` |
| `domain-updates` | `UpdateDomainSiteType` |
| `domain-version` | `UpdateDomainPhpVersion` |
| `whm-sync` | `WhmDomainSync` |
| `whm-tests` | `WhmServerTest` |
| `ovh-sync` | `OvhServiceSync` |

## 2) Requisitos previos

Migraciones `jobs` y `failed_jobs` ejecutadas.

## 3) Configuración `.env`

```env
QUEUE_CONNECTION=redis
MESSAGE_DELIVERY_QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Humano"
```

## 4) Workers

### Desarrollo (dos terminales)

```bash
# Correo
php artisan queue:work redis --queue=task-communications,notifications,mailer,campaign --sleep=3 --tries=3 --timeout=120

# Resto
php artisan queue:work redis --queue=default,domain-info,domain-updates,domain-version,whm-sync,whm-tests,ovh-sync --sleep=3 --tries=3 --timeout=120
```

### Producción / Forge (dos daemons)

Ejemplos listos para copiar en Forge:

- `deploy/supervisor/forge-queue-email.conf.example`
- `deploy/supervisor/forge-queue-general.conf.example`
- `deploy/supervisor/README.md`

**Daemon 1 — general** (el que ya tienes, actualizado):

```ini
command=php8.4 /home/forge/staging.humano.app/artisan queue:work redis --queue=default,domain-info,domain-updates,domain-version,whm-sync,whm-tests,ovh-sync --sleep=3 --tries=3 --timeout=120 --max-time=3600 --memory=256
directory=/home/forge/staging.humano.app
numprocs=2
```

**Daemon 2 — email** (nuevo en Forge → Queue → New Worker):

```ini
command=php8.4 /home/forge/staging.humano.app/artisan queue:work redis --queue=task-communications,notifications,mailer,campaign --sleep=3 --tries=3 --timeout=120 --max-time=3600 --memory=256
directory=/home/forge/staging.humano.app
numprocs=1
```

Quita `--daemon` y `--quiet` si los tenías; Supervisor ya mantiene el proceso vivo.

Tras cada deploy: `php artisan queue:restart`.

## 5) Despliegue (checklist)

1. `.env` con `QUEUE_CONNECTION=redis` y mail configurado.
2. Dos daemons activos (general + email).
3. `php artisan queue:restart` en el deploy script.

## 6) Monitoreo y recuperación

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush

tail -f storage/logs/queue-email.log
tail -f storage/logs/laravel.log
```

## 7) Prueba rápida

1. Envía una comunicación desde una tarea (cola `task-communications`).
2. O encola campaña: `php artisan messages:send-pending`.
3. Comprueba `queue-email.log` o logs de Laravel.

Para envíos masivos con rate limit, mantén `numprocs=1` en el worker de email; el jitter al encolar está en `MessageDeliveryDispatcher`.
