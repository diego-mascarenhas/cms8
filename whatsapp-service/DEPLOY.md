# WhatsApp service (Baileys) – Production deployment

## 1. One-time setup on the server

- **Node.js**: Ensure Node 18+ is installed (`node -v`). On Laravel Forge you may need to enable Node in the server details or install it.
- **PM2**: Install globally so the process restarts on failure and on reboot:
  ```bash
  npm install -g pm2
  pm2 startup
  ```
  (Run the command that `pm2 startup` prints so it runs on boot.)

- **Environment**: Create `whatsapp-service/.env` on the server (or copy from `.env.example`) with production values:
  ```env
  LARAVEL_WEBHOOK_URL=https://humano.revisionalpha.net/webhook/whatsapp-local
  WEBHOOK_SECRET=<same as WHATSAPP_LOCAL_WEBHOOK_SECRET in Laravel .env>
  PORT=3000
  AUTH_DIR=./auth
  ```
  The Laravel app must have `WHATSAPP_DRIVER=local` and `WHATSAPP_LOCAL_BASE_URL=https://humano.revisionalpha.net:3000` (or the URL where this service is exposed) so the chat can load the QR and send messages.

- **Firewall / URL**: If the Laravel app and this service are on the same machine, you can use `http://127.0.0.1/webhook/whatsapp-local` for `LARAVEL_WEBHOOK_URL`. If the service is on another server, the Laravel app must be reachable at the URL you set. Expose port 3000 only if the Laravel app needs to call this service from the browser (e.g. QR image); otherwise keep it bound to 127.0.0.1.

## 2. Add to Forge deploy script

For **zero-downtime** deploys (using `$CREATE_RELEASE()` / `$ACTIVATE_RELEASE()`), add this block **after** `$ACTIVATE_RELEASE()` and `$RESTART_QUEUES()` so the WhatsApp service runs the new release:

```bash
# --- WhatsApp Node service (Baileys) ---
WHATSAPP_DIR="$FORGE_RELEASE_DIRECTORY/whatsapp-service"
if [ -d "$WHATSAPP_DIR" ] && [ -f "$WHATSAPP_DIR/package.json" ]; then
  cd "$WHATSAPP_DIR"
  npm ci --production 2>/dev/null || npm install --production
  if command -v pm2 >/dev/null 2>&1; then
    pm2 reload whatsapp-service --update-env 2>/dev/null || pm2 start server.js --name whatsapp-service
    pm2 save 2>/dev/null || true
  else
    echo "PM2 not found. Install with: npm install -g pm2; pm2 startup"
  fi
  cd - >/dev/null
fi
```

- **Zero-downtime**: Use `WHATSAPP_DIR="$FORGE_RELEASE_DIRECTORY/whatsapp-service"` so PM2 runs the code from the release you just activated.
- **Classic deploy** (no release dirs): Use a fixed path like `WHATSAPP_DIR="/home/forge/humano.revisionalpha.net/whatsapp-service"` and run this block after `$FORGE_PHP artisan up`.

**Staging + production on the same server:** Use a different **PM2 name** per site (e.g. `WHATSAPP_PM2_NAME=whatsapp-service-staging` in Forge env for staging) and a different **PORT** in each site’s `whatsapp-service/.env` (e.g. staging `3001`, production `3000`). Otherwise both sites would share one process and one port and conflict.

See `docs/FORGE-DEPLOYMENT.md` for the full zero-downtime script and same-server setup.

## 3. Manual commands (optional)

- Start: `cd /home/forge/humano.revisionalpha.net/whatsapp-service && pm2 start server.js --name whatsapp-service`
- Restart after code change: `pm2 reload whatsapp-service`
- Logs: `pm2 logs whatsapp-service`
- Status: `pm2 status`
