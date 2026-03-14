# WhatsApp Local Service (Baileys)

Node.js service that connects to WhatsApp via QR code (no Twilio or external API). Used when `WHATSAPP_DRIVER=local`.

**Requisitos:** Node 18+ (probado con Node 22).

## Setup

```bash
cd whatsapp-service
npm install
```

Copy `.env.example` to `.env` and set:

- `LARAVEL_WEBHOOK_URL` – full URL to your Laravel webhook (e.g. `https://humano.test/webhook/whatsapp-local`)
- `WEBHOOK_SECRET` – optional; must match `WHATSAPP_LOCAL_WEBHOOK_SECRET` in Laravel `.env`
- `PORT` – default `3000`

## Run

```bash
npm start
```

Open `http://localhost:3000/qr` in a browser and scan the QR code with WhatsApp on your phone. After linking, the session is stored in the `auth` folder and you won’t need to scan again unless you log out.

## Laravel

In Laravel `.env`:

- `WHATSAPP_DRIVER=local`
- `WHATSAPP_LOCAL_BASE_URL=http://localhost:3000`
- `WHATSAPP_LOCAL_WEBHOOK_SECRET` – optional, same as `WEBHOOK_SECRET` above

In the Chat UI, use “WhatsApp connection” to open the QR page (or open the Node `/qr` URL directly).

## API (for Laravel gateway)

- `GET /status` – `{ status, number? }`
- `GET /qr` – HTML page with QR (when not connected)
- `POST /send-message` – `{ to, body }`
- `POST /send-media` – `{ to, mediaUrl, caption? }`

Incoming messages are forwarded to `LARAVEL_WEBHOOK_URL` as POST JSON.
