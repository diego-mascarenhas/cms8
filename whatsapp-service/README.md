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

From the Chat UI, open "WhatsApp connection" for a team to show the QR; the app passes `team_id` so each team gets its own session. Sessions are stored under `auth/team_<id>`; multiple teams (numbers) can be connected at once.

## Laravel

In Laravel `.env`:

- `WHATSAPP_DRIVER=local`
- `WHATSAPP_LOCAL_BASE_URL=http://localhost:3000`
- `WHATSAPP_LOCAL_WEBHOOK_SECRET` – optional, same as `WEBHOOK_SECRET` above

Laravel sends `team_id` on every request so one instance serves all teams.

## Multiple teams (one number per team) — single instance

One Node process can serve multiple teams: each team has its own Baileys session (one WhatsApp number per team). Auth is stored in `auth/team_<team_id>`. No need to run multiple Node instances unless you prefer one process per team (then set team setting `whatsapp_service_url` to that instance's base URL).

## API (for Laravel gateway)

All endpoints require `team_id` (query or body) so the correct session is used.

- `GET /status?team_id=<id>` – `{ status, number? }`
- `GET /qr?team_id=<id>` – HTML page with QR for that team
- `GET /qr.png?team_id=<id>&link_token=...&link_current=1` – QR image; `link_token` and `link_current` used for linking the number to the team
- `GET /refresh?team_id=<id>` – disconnect and get a new QR for that team
- `POST /send-message` – body: `{ to, body, team_id }`
- `POST /send-media` – body: `{ to, mediaUrl, caption?, team_id }`

Incoming messages are forwarded to `LARAVEL_WEBHOOK_URL` as POST JSON (payload includes `team_id` when from Node).
