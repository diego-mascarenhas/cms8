/**
 * Local WhatsApp service (Baileys) for Humano.
 * Exposes REST API and forwards incoming messages to Laravel webhook.
 *
 * Env: LARAVEL_WEBHOOK_URL, WEBHOOK_SECRET, PORT, AUTH_DIR
 */
const path = require('path');
const https = require('https');
const http = require('http');
const { URL } = require('url');
require('dotenv').config({ path: path.join(__dirname, '.env') });

const express = require('express');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, downloadMediaMessage, getContentType } = require('baileys');
const QRCode = require('qrcode');
const pino = require('pino');
const fs = require('fs');

const PORT = process.env.PORT || 3000;
const AUTH_DIR = process.env.AUTH_DIR || path.join(__dirname, 'auth');
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://localhost:80/webhook/whatsapp-local';
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET || '';

/** POST JSON to URL; for HTTPS, allow self-signed certs (e.g. local Herd). */
function postJsonToWebhook(urlString, body, headers = {}) {
  return new Promise((resolve, reject) => {
    const u = new URL(urlString);
    const isHttps = u.protocol === 'https:';
    const bodyStr = typeof body === 'string' ? body : JSON.stringify(body);
    const opts = {
      hostname: u.hostname,
      port: u.port || (isHttps ? 443 : 80),
      path: u.pathname + u.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(bodyStr),
        ...headers,
      },
    };
    if (isHttps) {
      opts.rejectUnauthorized = false;
    }
    const mod = isHttps ? https : http;
    const req = mod.request(opts, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => resolve({ ok: res.statusCode >= 200 && res.statusCode < 300, status: res.statusCode, text: Buffer.concat(chunks).toString() }));
    });
    req.on('error', reject);
    req.write(bodyStr);
    req.end();
  });
}

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
        const contentType = getContentType(msg.message);
        let body = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '';
        const from = msg.key.remoteJid;
        const id = msg.key.id;

        const payload = {
          from: from.replace('@s.whatsapp.net', '').replace(/:\d+$/, ''),
          to: ourJid ? ourJid.replace('@s.whatsapp.net', '').replace(/:\d+$/, '') : '',
          body,
          id,
          messageId: id,
        };

        if (contentType === 'audioMessage' && body === '') {
          try {
            const buffer = await downloadMediaMessage(msg, 'buffer', {}, { reuploadRequest: socket.updateMediaMessage });
            const mimetype = msg.message?.audioMessage?.mimetype || 'audio/ogg; codecs=opus';
            payload.audio_base64 = buffer.toString('base64');
            payload.audio_content_type = mimetype.split(';')[0].trim();
          } catch (e) {
            console.error('Download incoming audio failed:', e.message);
          }
        }

        if (LARAVEL_WEBHOOK_URL) {
          try {
            const webhookHeaders = {};
            if (WEBHOOK_SECRET) webhookHeaders['X-Webhook-Secret'] = WEBHOOK_SECRET;
            const res = await postJsonToWebhook(LARAVEL_WEBHOOK_URL, payload, webhookHeaders);
            if (!res.ok) {
              console.error('Webhook error:', res.status, res.text);
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
    // Clear auth so the new socket has no credentials and Baileys will emit a QR
    if (fs.existsSync(AUTH_DIR)) {
      fs.rmSync(AUTH_DIR, { recursive: true });
      fs.mkdirSync(AUTH_DIR, { recursive: true });
    }
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
    const contentType = (resp.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
    const urlPath = new URL(mediaUrl).pathname || '';
    let messageContent;
    if (contentType.startsWith('audio/') || /\.(webm|ogg|mp3|m4a|wav|aac|opus)$/i.test(urlPath)) {
      messageContent = {
        audio: buffer,
        mimetype: contentType || 'audio/webm',
        ptt: true,
      };
      if (caption) messageContent.caption = caption;
    } else if (contentType.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp)$/i.test(urlPath)) {
      messageContent = { image: buffer, caption: caption || undefined };
    } else if (contentType.startsWith('video/') || /\.(mp4|webm|mov)$/i.test(urlPath)) {
      messageContent = { video: buffer, caption: caption || undefined };
    } else {
      messageContent = { document: buffer, caption: caption || undefined };
    }
    const sent = await sock.sendMessage(jid, messageContent);
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
