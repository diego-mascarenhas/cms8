# Supervisor / Laravel Forge — workers

Cinco daemons en Forge: **Communications** (prioridad), **correo**, **reuniones**, **prospectos** y el **resto**.

## 1. Worker general (editar el daemon existente)

Sustituye el `command` del daemon actual por el de `forge-queue-general.conf.example`.

No incluyas `mailer`, `campaign`, `notifications`, `task-communications`, `communications` ni `meetings`.

## 2. Worker de email (daemon existente)

`mailer` y `campaign`. Pega `forge-queue-email.conf.example` (o copia solo `command` + `directory`).

No incluyas `communications`.

## 3. Worker de reuniones (daemon nuevo)

Crea un **Queue Worker** nuevo y pega `forge-queue-meetings.conf.example`.

Cola sola: `meetings`, conexión `redis-meetings`. El worker general no debe escuchar esa cola. Timeout 930 segundos y memoria 1024 MB, porque una transcripción carga el audio entero y puede tardar varios minutos.

## 4. Worker de Communications (daemon nuevo)

Crea un **Queue Worker** nuevo y pega `forge-queue-communications.conf.example`.

Cola sola: `communications`. Así un envío masivo no retrasa email/WhatsApp/SMS transaccional.

## 5. Worker de prospectos (daemon nuevo)

Crea un **Queue Worker** nuevo y pega `forge-queue-prospects.conf.example`.

Cola sola: `prospects`. Ahí entra la búsqueda del filtro y, por cada persona, el trabajo que verifica si ya está importada y la enriquece.

## 6. `.env` (staging / producción)

```env
QUEUE_CONNECTION=redis
MESSAGE_DELIVERY_QUEUE_CONNECTION=redis
```

## 7. Tras cambiar daemons o desplegar

```bash
php artisan queue:restart
```

En el servidor (si usas supervisorctl directamente):

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

## 8. Comprobar

```bash
# Encolar un envío de prueba y ver el log del worker de email
tail -f storage/logs/queue-email.log
tail -f storage/logs/laravel.log | grep -i "notification\|MessageCampaign\|Task communication"
```
