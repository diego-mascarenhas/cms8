# Supervisor / Laravel Forge — workers

Tres daemons en Forge: **Communications** (prioridad), **correo** y el **resto**.

## 1. Worker general (editar el daemon existente)

Sustituye el `command` del daemon actual por el de `forge-queue-general.conf.example`.

No incluyas `mailer`, `campaign`, `notifications`, `task-communications` ni `communications`.

## 2. Worker de email (daemon existente)

`mailer` y `campaign`. Pega `forge-queue-email.conf.example` (o copia solo `command` + `directory`).

No incluyas `communications`.

## 3. Worker de Communications (daemon nuevo)

Crea un **Queue Worker** nuevo y pega `forge-queue-communications.conf.example`.

Cola sola: `communications`. Así un envío masivo no retrasa email/WhatsApp/SMS transaccional.

## 4. `.env` (staging / producción)

```env
QUEUE_CONNECTION=redis
MESSAGE_DELIVERY_QUEUE_CONNECTION=redis
```

## 5. Tras cambiar daemons o desplegar

```bash
php artisan queue:restart
```

En el servidor (si usas supervisorctl directamente):

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

## 6. Comprobar

```bash
# Encolar un envío de prueba y ver el log del worker de email
tail -f storage/logs/queue-email.log
tail -f storage/logs/laravel.log | grep -i "notification\|MessageCampaign\|Task communication"
```
