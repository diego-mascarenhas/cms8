/**
 * Local WhatsApp service (Baileys) for Humano.
 * Exposes REST API and forwards incoming messages to Laravel webhook.
 *
 * Env: LARAVEL_WEBHOOK_URL, WEBHOOK_SECRET, PORT, AUTH_DIR
 */
const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '.env') });

const express = require('express');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require('baileys');
const QRCode = require('qrcode');
const pino = require('pino');
const fs = require('fs');

const PORT = process.env.PORT || 3000;
const AUTH_DIR = process.env.AUTH_DIR || path.join(__dirname, 'auth');
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://localhost:80/webhook/whatsapp-local';
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET || '';

const app = express();
app.use(express.json());

let sock = null;
let currentQR = null;
let connectionStatus = 'disconnected';
let ourJid = null;

function makeSocket() {
  return (async () => {
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    const { version } = await fetchLatestBaileysVersion();
    const logger = pino({ level: 'silent' });

    const socket = makeWASocket({
      version,
      auth: state,
      logger,
      printQRInTerminal: false,
    });

    socket.ev.on('creds.update', saveCreds);

    socket.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        currentQR = qr;
        connectionStatus = 'waiting_qr';
      }

      if (connection === 'close') {
        currentQR = null;
        connectionStatus = 'disconnected';
        sock = null;
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        if (statusCode !== DisconnectReason.loggedOut) {
          console.log('Reconnecting in 3s...');
          setTimeout(() => {
            makeSocket().then((s) => { sock = s; }).catch((e) => console.error('Reconnect failed:', e.message));
          }, 3000);
        }
      } else if (connection === 'open') {
        currentQR = null;
        connectionStatus = 'connected';
        ourJid = socket.user?.id || null;
      }
    });

    socket.ev.on('messages.upsert', async ({ messages }) => {
      if (connectionStatus !== 'connected') return;
      for (const msg of messages) {
        if (msg.key.fromMe) continue;
        const body = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '';
        const from = msg.key.remoteJid;
        const id = msg.key.id;

        const payload = {
          from: from.replace('@s.whatsapp.net', ''),
          to: ourJid ? ourJid.replace('@s.whatsapp.net', '').replace(/:\d+$/, '') : '',
          body,
          id,
          messageId: id,
        };

        if (LARAVEL_WEBHOOK_URL) {
          try {
            const res = await fetch(LARAVEL_WEBHOOK_URL, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                ...(WEBHOOK_SECRET && { 'X-Webhook-Secret': WEBHOOK_SECRET }),
              },
              body: JSON.stringify(payload),
            });
            if (!res.ok) {
              console.error('Webhook error:', res.status, await res.text());
            }
          } catch (e) {
            console.error('Webhook request failed:', e.message);
          }
        }
      }
    });

    return socket;
  })();
}

// --- Routes ---

app.get('/status', (req, res) => {
  res.json({
    status: connectionStatus,
    number: ourJid ? ourJid.replace('@s.whatsapp.net', '').replace(/:\d+$/, '') : null,
  });
});

app.get('/qr', (req, res) => {
  if (connectionStatus !== 'waiting_qr' || !currentQR) {
    return res.type('html').send(`
      <!DOCTYPE html>
      <html><head><meta charset="utf-8"><title>WhatsApp</title></head>
      <body>
        <p>Status: ${connectionStatus}</p>
        <p>${connectionStatus === 'connected' ? 'Already connected.' : 'No QR code available. Restart the service if you need to link again.'}</p>
      </body></html>
    `);
  }
  QRCode.toDataURL(currentQR, (err, url) => {
    if (err) return res.status(500).json({ error: 'QR generation failed' });
    res.type('html').send(`
      <!DOCTYPE html>
      <html><head><meta charset="utf-8"><title>Scan WhatsApp QR</title></head>
      <body>
        <h1>Scan with WhatsApp</h1>
        <img src="${url}" alt="QR Code" />
        <p>Status: ${connectionStatus}</p>
      </body></html>
    `);
  });
});

app.get('/qr.png', (req, res) => {
  if (connectionStatus !== 'waiting_qr' || !currentQR) {
    return res.status(404).json({ error: 'No QR code available' });
  }
  QRCode.toBuffer(currentQR, (err, buffer) => {
    if (err) return res.status(500).json({ error: 'QR generation failed' });
    res.type('png').send(buffer);
  });
});

app.get('/refresh', async (req, res) => {
  if (connectionStatus === 'connected') {
    return res.json({ ok: true, status: 'connected' });
  }
  try {
    if (sock) {
      try {
        sock.end(undefined);
      } catch (e) {
        // ignore
      }
      sock = null;
    }
    ourJid = null;
    currentQR = null;
    connectionStatus = 'disconnected';
    sock = await makeSocket();
    res.json({ ok: true, message: 'Reconnecting to get a new QR code.' });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/send-message', async (req, res) => {
  if (connectionStatus !== 'connected' || !sock) {
    return res.status(503).json({ error: 'WhatsApp not connected. Scan QR first.' });
  }
  const { to, body } = req.body || {};
  if (!to || !body) {
    return res.status(422).json({ error: 'Missing "to" or "body"' });
  }
  const jid = to.includes('@') ? to : `${to.replace(/\D/g, '')}@s.whatsapp.net`;
  try {
    const sent = await sock.sendMessage(jid, { text: body });
    res.json({ id: sent?.key?.id, success: true });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/send-media', async (req, res) => {
  if (connectionStatus !== 'connected' || !sock) {
    return res.status(503).json({ error: 'WhatsApp not connected. Scan QR first.' });
  }
  const { to, mediaUrl, caption } = req.body || {};
  if (!to || !mediaUrl) {
    return res.status(422).json({ error: 'Missing "to" or "mediaUrl"' });
  }
  const jid = to.includes('@') ? to : `${to.replace(/\D/g, '')}@s.whatsapp.net`;
  try {
    const resp = await fetch(mediaUrl);
    const buffer = Buffer.from(await resp.arrayBuffer());
    const sent = await sock.sendMessage(jid, {
      image: buffer,
      caption: caption || undefined,
    });
    res.json({ id: sent?.key?.id, success: true });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/logout', async (req, res) => {
  try {
    if (sock) {
      try {
        await sock.logout();
      } catch (e) {
        // ignore
      }
      sock = null;
    }
    ourJid = null;
    currentQR = null;
    connectionStatus = 'disconnected';
    if (fs.existsSync(AUTH_DIR)) {
      fs.rmSync(AUTH_DIR, { recursive: true });
    }
    sock = await makeSocket();
    res.json({ success: true, message: 'WhatsApp session closed. Scan QR to link again.' });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// --- Fatal error handling (Baileys may throw "Connection Closed" etc.) ---
process.on('uncaughtException', (err) => {
  console.error('Fatal:', err.message || err);
  process.exit(1);
});
process.on('unhandledRejection', (reason, promise) => {
  console.error('Unhandled rejection:', reason);
  process.exit(1);
});

// --- Start ---

(async () => {
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
  }
  sock = await makeSocket();
  app.listen(PORT, () => {
    console.log(`WhatsApp service listening on http://localhost:${PORT}`);
    console.log(`QR: http://localhost:${PORT}/qr  Status: http://localhost:${PORT}/status`);
    console.log(`Laravel webhook: ${LARAVEL_WEBHOOK_URL}`);
  });
})();
