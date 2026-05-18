# Supervisor / Laravel Forge — workers

Dos daemons en Forge: uno para **correo** y otro para el **resto**.

## 1. Worker general (editar el daemon existente)

Sustituye el `command` del daemon actual por el de `forge-queue-general.conf.example`.

No incluyas `mailer`, `campaign`, `notifications` ni `task-communications`.

## 2. Worker de email (daemon nuevo en Forge)

Crea un **Queue Worker** nuevo y pega `forge-queue-email.conf.example` (o copia solo `command` + `directory`).

## 3. `.env` (staging / producción)

```env
QUEUE_CONNECTION=redis
MESSAGE_DELIVERY_QUEUE_CONNECTION=redis
```

## 4. Tras cambiar daemons o desplegar

```bash
php artisan queue:restart
```

En el servidor (si usas supervisorctl directamente):

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

## 5. Comprobar

```bash
# Encolar un envío de prueba y ver el log del worker de email
tail -f storage/logs/queue-email.log
tail -f storage/logs/laravel.log | grep -i "notification\|MessageCampaign\|Task communication"
```
