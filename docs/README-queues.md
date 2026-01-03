# Colas (Queues) de Humano

Esta guía describe las colas que usa la aplicación, cómo configurarlas en .env, cómo ejecutar workers en desarrollo y producción, y cómo monitorear y recuperar errores.

## 1) Colas definidas y propósito

-   task-communications: Procesa envíos de comunicaciones de tareas (emails a cliente, notas internas), Job: `App\Jobs\SendTaskCommunication`. Prioritaria.
-   default: Cola por defecto de Laravel (reservada para futuros trabajos genéricos).

Nota: El Job `SendTaskCommunication` utiliza reintentos (tries=3) y backoff=60 segundos.

## 2) Requisitos previos

-   Migraciones de colas ejecutadas (jobs y failed_jobs):

```bash
php artisan migrate --path=database/migrations/2020_05_21_500000_create_jobs_table.php --force
php artisan migrate --path=database/migrations/2019_08_19_000000_create_failed_jobs_table.php --force
```

-   Migración de comunicaciones de tareas (asegúrate que corre después de `tasks`):

```bash
# ejemplo sugerido (ajusta al timestamp real en tu repo)
php artisan migrate --path=database/migrations/2024_07_04_400000_create_task_communications_table.php --force
```

## 3) Configuración .env

```env
# Driver recomendado
QUEUE_CONNECTION=database

# (Opcional) Configuración de correo para envíos a clientes
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@tudominio.com
MAIL_FROM_NAME="Humano"
```

Si usas Redis, cambia `QUEUE_CONNECTION=redis` y ajusta tu `config/queue.php`/Redis en `.env`.

## 4) Arranque de workers

### Desarrollo (local)

Procesar primero la cola prioritaria y luego la default:

```bash
php artisan queue:work --queue=task-communications,default --sleep=1 --tries=3 --backoff=60
```

Detener con Ctrl+C.

### Producción (Supervisor)

Archivo de ejemplo `/etc/supervisor/conf.d/humano-queues.conf`:

```ini
[program:humano-queues]
process_name=%(program_name)s_%(process_num)02d
directory=/var/www/humano
command=php artisan queue:work --queue=task-communications,default --sleep=1 --tries=3 --backoff=60 --timeout=120
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/humano/storage/logs/queue.log
stopwaitsecs=5
stopasgroup=true
killasgroup=true
```

Aplicar cambios:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start humano-queues:*
```

Ajusta `numprocs` según CPU/RAM del servidor y volumen de envíos.

## 5) Despliegue (checklist rápido)

1. Compilar assets si no hay CI: `npm run build`.
2. Migraciones necesarias ejecutadas (incluida la de `task_communications`).
3. `.env` con `QUEUE_CONNECTION=database` y mail configurado.
4. Workers levantados: `task-communications,default`.
5. Limpiar cachés: `php artisan optimize:clear`.

## 6) Monitoreo y recuperación

-   Ver trabajos en cola (driver database):
    -   Tabla `jobs` (pendientes), `failed_jobs` (fallidos).
-   Reintentar fallidos:

```bash
php artisan queue:failed
php artisan queue:retry all
# o por ID
php artisan queue:retry 12345
```

-   Borrar fallidos procesados:

```bash
php artisan queue:flush
```

-   Parar/arrancar workers (Supervisor):

```bash
sudo supervisorctl status humano-queues:*
sudo supervisorctl restart humano-queues:*
```

## 7) Prueba rápida de la cola de comunicaciones

1. Desde el Kanban, abre una tarea y envía una comunicación (cliente o responsable).
2. Verifica en logs que el Job se encola y procesa:

```bash
tail -f storage/logs/laravel.log | grep -i "Task communication"
```

3. Si usas base de datos como driver, verifica movimiento en `jobs`/`failed_jobs`.

---

Ante cualquier pico de carga en comunicaciones, aumenta `numprocs` y prioriza `task-communications` en el orden de la opción `--queue`.
