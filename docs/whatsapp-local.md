# WhatsApp local (QR sin Twilio)

Guía para usar WhatsApp vinculado por QR con el servicio Node (Baileys), sin depender de Twilio.

## Resumen

- **Driver** en Laravel: `WHATSAPP_DRIVER=local` usa el servicio Node; `twilio` sigue usando Twilio.
- **Servicio Node** (`whatsapp-service/`): mantiene la sesión de WhatsApp (QR una vez, luego persistida), expone API y envía mensajes entrantes al webhook de Laravel.
- **Tabla** donde se guardan los mensajes: `conversations` (igual que con Twilio).

---

## 1. Variables de entorno

### Laravel (raíz del proyecto, `.env`)

```env
WHATSAPP_DRIVER=local
WHATSAPP_LOCAL_BASE_URL=http://localhost:3000
WHATSAPP_LOCAL_WEBHOOK_SECRET=
```

- `LARAVEL_WEBHOOK_URL` **no** va aquí; es solo del servicio Node.

### Servicio Node (`whatsapp-service/.env`)

```env
LARAVEL_WEBHOOK_URL=https://humano.test/webhook/whatsapp-local
WEBHOOK_SECRET=
PORT=3000
```

- Las variables `WHATSAPP_*` **no** van aquí; son solo de Laravel.
- Si usas `WEBHOOK_SECRET`, en Laravel pon el mismo valor en `WHATSAPP_LOCAL_WEBHOOK_SECRET`.

---

**Requisitos:** Node 18+ (probado con Node 22).

## 2. Instalación y primera ejecución

```bash
cd whatsapp-service
npm install
cp .env.example .env
# Editar .env y poner LARAVEL_WEBHOOK_URL (y opcional WEBHOOK_SECRET)
npm start
```

Abrir **http://localhost:3000/qr** (o en Laravel: Chat → “WhatsApp connection” → “Open QR in new tab”), escanear el QR con WhatsApp en el móvil. La sesión se guarda en `whatsapp-service/auth/`.

---

## 3. Ejecutar con PM2 (recomendado)

Para que el proceso se mantenga en marcha y se reinicie si falla:

```bash
cd whatsapp-service
npx pm2 start server.js --name whatsapp-service
pm2 save
```

Comandos útiles:

| Comando | Descripción |
|---------|-------------|
| `pm2 list` | Ver procesos (estado, CPU, memoria) |
| `pm2 logs whatsapp-service` | Ver logs en vivo |
| `pm2 restart whatsapp-service` | Reiniciar el servicio |
| `pm2 stop whatsapp-service` | Parar el servicio |
| `pm2 delete whatsapp-service` | Quitar el proceso de PM2 |

Para que arranque al encender el Mac, ejecutar el comando que PM2 muestra al hacer `pm2 startup` (suele incluir `sudo`).

---

## 4. Cómo acceder al QR

- **Desde Laravel:** con `WHATSAPP_DRIVER=local`, en Chat aparece el enlace “WhatsApp connection” al final de la lista. Ahí se muestra el QR (o el botón “Open QR in new tab” si el iframe no carga por HTTPS).
- **Directo:** **http://localhost:3000/qr** (HTML) o **http://localhost:3000/qr.png** (imagen). Estado: **http://localhost:3000/status**.

---

## 5. Enviar y recibir mensajes

- **Enviar:** en Laravel, Chat → elegir o escribir número (ej. `34722372858`) → escribir y enviar. El mensaje sale por el WhatsApp vinculado.
- **Recibir:** cuando alguien escribe a ese número, el Node hace POST a `LARAVEL_WEBHOOK_URL` y Laravel guarda en `conversations` y aplica la misma lógica que con Twilio (notificaciones, IA, carrito, etc.).
- **Nombre en el chat:** el servicio Node envía el **nombre de perfil de WhatsApp** (Baileys `pushName`) como `push_name` en el JSON del webhook. Laravel lo usa para el `User` y el `Contact` cuando aún tenían el nombre automático (`Usuario …` / `Contacto …`); no sobrescribe nombres que ya pusiste a mano.
- **Lista de chats:** se muestran tanto conversaciones en las que te escribieron (inbound) como a las que tú escribiste (outbound). “WhatsApp connection” está al final de la lista.

---

## 6. Tabla de conversaciones

Los mensajes se guardan en la tabla **`conversations`**:

- `channel` = `'whatsapp'`
- `from` / `to` = números (sin `whatsapp:`)
- `body` = texto
- `direction` = `'inbound'` o `'outbound'`

Ejemplo para buscar en la base de datos:

```sql
SELECT id, `from`, `to`, body, direction, created_at
FROM conversations
WHERE channel = 'whatsapp'
ORDER BY created_at DESC
LIMIT 20;
```

---

## 7. Problemas frecuentes

### “Connection Closed” y el proceso Node se cae

- La conexión con WhatsApp se cerró y Baileys lanza un error no capturado. Con PM2 el proceso se reinicia solo.
- Si no usas PM2: `npm start` de nuevo en `whatsapp-service`. Opción en bucle: `while true; do npm start; sleep 2; done`.

### Tabla `conversations` vacía aunque he enviado mensajes

- Comprobar que `WHATSAPP_DRIVER=local` y que el gateway local está guardando (p. ej. `message_sid` único). Si hubo errores al enviar, revisar `storage/logs/` (o el log que uses).

### QR no se ve en la página de Laravel (recuadro vacío)

- Suele ser contenido mixto (HTTPS en humano.test e iframe a `http://localhost`). Usar el botón **“Open QR in new tab”** o abrir **http://localhost:3000/qr** en otra pestaña.

### “Status: disconnected” / “No QR code available”

- Esperar unos segundos y recargar **http://localhost:3000/qr**.
- Si sigue igual: parar el servicio, borrar la sesión y volver a vincular:
  ```bash
  cd whatsapp-service
  rm -rf auth
  npm start
  ```
  Luego escanear de nuevo el QR.

### Logs de Laravel

- Por defecto: `storage/logs/laravel.log` (puede no existir hasta el primer mensaje). Si usas logs diarios: `storage/logs/laravel-YYYY-MM-DD.log`.
- Crear el archivo si quieres hacer `tail -f`: `touch storage/logs/laravel.log` y luego `tail -f storage/logs/laravel.log` (desde la raíz del proyecto).

---

## 8. API del servicio Node (para el gateway Laravel)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/status` | `{ status, number? }` (connected, waiting_qr, disconnected) |
| GET | `/qr` | Página HTML con el QR (cuando no está vinculado) |
| GET | `/qr.png` | Imagen PNG del QR |
| POST | `/send-message` | Body: `{ to, body }` |
| POST | `/send-media` | Body: `{ to, mediaUrl, caption? }` |

Los mensajes entrantes se reenvían en POST a `LARAVEL_WEBHOOK_URL` con `from`, `to`, `body`, `id`, `team_id`, y si WhatsApp lo envía, **`push_name`** (nombre visible del remitente). Opcional: cabecera `X-Webhook-Secret` si está configurada.
