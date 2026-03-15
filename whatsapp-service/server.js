/**
 * Local WhatsApp service (Baileys) for Humano — multi-tenant.
 * One Baileys socket per team_id; one instance can serve multiple teams (multiple numbers).
 *
 * Env: LARAVEL_WEBHOOK_URL, WEBHOOK_SECRET, PORT, AUTH_DIR (base dir for auth/team_<id>)
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
const AUTH_BASE = process.env.AUTH_DIR || path.join(__dirname, 'auth');
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://localhost:80/webhook/whatsapp-local';
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET || '';
const LARAVEL_APP_URL = process.env.LARAVEL_APP_URL || '';

function getLaravelBaseUrl() {
  const base = (LARAVEL_APP_URL && LARAVEL_APP_URL.trim()) ? LARAVEL_APP_URL.trim().replace(/\/$/, '') : null;
  if (base) {
    try {
      new URL(base);
      return base;
    } catch (_) {}
  }
  try {
    const u = new URL(LARAVEL_WEBHOOK_URL);
    return `${u.protocol}//${u.host}`;
  } catch {
    return 'http://localhost';
  }
}

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
    if (isHttps) opts.rejectUnauthorized = false;
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

/** @type {Record<string, { socket: any, currentQR: string|null, connectionStatus: string, ourJid: string|null, pendingLinkToken: string|null }>} */
const sessions = {};

function getTeamId(req) {
  const id = req.query?.team_id || req.body?.team_id;
  return id != null && String(id).trim() !== '' ? String(id).trim() : null;
}

function getOrCreateSession(teamId) {
  if (!teamId) return null;
  if (!sessions[teamId]) {
    sessions[teamId] = {
      socket: null,
      currentQR: null,
      connectionStatus: 'disconnected',
      ourJid: null,
      pendingLinkToken: null,
    };
  }
  return sessions[teamId];
}

function maybeSendLinkedCallback(session) {
  if (!session || !session.ourJid || !session.pendingLinkToken) return;
  const number = session.ourJid.replace('@s.whatsapp.net', '').replace(/:\d+$/, '');
  const linkedUrl = `${getLaravelBaseUrl()}/chat/whatsapp-linked`;
  const webhookHeaders = {};
  if (WEBHOOK_SECRET) webhookHeaders['X-Webhook-Secret'] = WEBHOOK_SECRET;
  postJsonToWebhook(linkedUrl, { link_token: session.pendingLinkToken, number }, webhookHeaders)
    .then((res) => {
      if (res.ok) console.log('Linked WhatsApp number to team.');
      else console.error('Linked callback error:', res.status, res.text);
    })
    .catch((e) => console.error('Linked callback failed:', e.message));
  session.pendingLinkToken = null;
}

async function makeSocket(teamId) {
  const session = getOrCreateSession(teamId);
  if (!session) throw new Error('team_id required');

  const authDir = path.join(AUTH_BASE, 'team_' + teamId);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  const { state, saveCreds } = await useMultiFileAuthState(authDir);
  const { version } = await fetchLatestBaileysVersion();
  const logger = pino({ level: 'silent' });

  const socket = makeWASocket({
    version,
    auth: state,
    logger,
    printQRInTerminal: false,
  });

  socket.teamId = teamId;
  session.socket = socket;

  socket.ev.on('creds.update', saveCreds);

  socket.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      session.currentQR = qr;
      session.connectionStatus = 'waiting_qr';
    }

    if (connection === 'close') {
      session.currentQR = null;
      session.connectionStatus = 'disconnected';
      session.socket = null;
      session.ourJid = null;
      session.pendingLinkToken = null;
      const statusCode = lastDisconnect?.error?.output?.statusCode;
      if (statusCode !== DisconnectReason.loggedOut) {
        console.log(`Team ${teamId}: reconnecting in 3s...`);
        setTimeout(() => {
          makeSocket(teamId).catch((e) => console.error('Reconnect failed:', e.message));
        }, 3000);
      }
    } else if (connection === 'open') {
      session.currentQR = null;
      session.connectionStatus = 'connected';
      session.ourJid = socket.user?.id || null;
      maybeSendLinkedCallback(session);
    }
  });

  socket.ev.on('messages.upsert', async ({ messages }) => {
    if (session.connectionStatus !== 'connected') return;
    for (const msg of messages) {
      if (msg.key.fromMe) continue;
      const contentType = getContentType(msg.message);
      let body = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '';
      const from = msg.key.remoteJid;
      const id = msg.key.id;

      const fromNormalized = (from || '').replace('@s.whatsapp.net', '').replace(/:\d+$/, '').replace(/\D/g, '');
      const toNormalized = session.ourJid ? session.ourJid.replace('@s.whatsapp.net', '').replace(/:\d+$/, '').replace(/\D/g, '') : '';
      if (fromNormalized.length < 8) {
        continue;
      }

      const payload = {
        from: fromNormalized,
        to: toNormalized,
        body,
        id,
        messageId: id,
        team_id: teamId,
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
          if (!res.ok) console.error('Webhook error:', res.status, res.text);
        } catch (e) {
          console.error('Webhook request failed:', e.message);
        }
      }
    }
  });

  return socket;
}

// --- Routes (all require team_id for multi-tenant) ---

/** If session has no socket but auth dir exists, restore socket from disk (e.g. after Node restart). */
function ensureSocketIfAuthExists(teamId) {
  const session = getOrCreateSession(teamId);
  if (session.socket) return;
  const authDir = path.join(AUTH_BASE, 'team_' + teamId);
  if (!fs.existsSync(authDir)) return;
  const hasCreds = fs.readdirSync(authDir).length > 0;
  if (!hasCreds) return;
  makeSocket(teamId).catch((e) => console.error('Restore session failed:', e.message));
}

app.get('/status', (req, res) => {
  const teamId = getTeamId(req);
  if (!teamId) {
    return res.status(400).json({ error: 'team_id required' });
  }
  const session = getOrCreateSession(teamId);
  if (!session.socket) {
    setImmediate(() => ensureSocketIfAuthExists(teamId));
  }
  const number = session.ourJid ? session.ourJid.replace('@s.whatsapp.net', '').replace(/:\d+$/, '') : null;
  res.json({
    status: session.connectionStatus,
    number,
  });
});

app.get('/qr', (req, res) => {
  const teamId = getTeamId(req);
  if (!teamId) return res.status(400).send('team_id required');
  const session = getOrCreateSession(teamId);
  if (session.connectionStatus !== 'waiting_qr' || !session.currentQR) {
    return res.type('html').send(`
      <!DOCTYPE html><html><head><meta charset="utf-8"><title>WhatsApp</title></head><body>
      <p>Status: ${session.connectionStatus}</p>
      <p>${session.connectionStatus === 'connected' ? 'Already connected.' : 'No QR. Use /refresh?team_id=' + teamId + ' to get a new QR.'}</p>
      </body></html>
    `);
  }
  QRCode.toDataURL(session.currentQR, (err, url) => {
    if (err) return res.status(500).json({ error: 'QR generation failed' });
    res.type('html').send(`
      <!DOCTYPE html><html><head><meta charset="utf-8"><title>Scan WhatsApp QR (team ${teamId})</title></head><body>
      <h1>Scan with WhatsApp</h1>
      <img src="${url}" alt="QR Code" />
      <p>Team: ${teamId}</p>
      </body></html>
    `);
  });
});

app.get('/qr.png', async (req, res) => {
  const teamId = getTeamId(req);
  if (!teamId) {
    return res.status(400).json({ error: 'team_id required' });
  }

  let session = getOrCreateSession(teamId);
  if (!session.socket) {
    try {
      await makeSocket(teamId);
      session = getOrCreateSession(teamId);
    } catch (e) {
      return res.status(500).json({ error: e.message });
    }
  }

  const linkToken = req.query.link_token || null;
  if (linkToken) {
    session.pendingLinkToken = linkToken;
    const linkCurrent = req.query.link_current === '1' || req.query.link_current === 'true';
    if (linkCurrent && session.connectionStatus === 'connected' && session.ourJid) {
      setImmediate(() => maybeSendLinkedCallback(session));
    }
  }

  if (session.connectionStatus !== 'waiting_qr' || !session.currentQR) {
    return res.status(404).json({ error: 'No QR code available' });
  }

  QRCode.toBuffer(session.currentQR, (err, buffer) => {
    if (err) return res.status(500).json({ error: 'QR generation failed' });
    res.type('png').send(buffer);
  });
});

app.get('/refresh', async (req, res) => {
  const teamId = getTeamId(req);
  if (!teamId) {
    return res.status(400).json({ error: 'team_id required' });
  }

  const session = getOrCreateSession(teamId);
  if (session.connectionStatus === 'connected') {
    return res.json({ ok: true, status: 'connected' });
  }

  try {
    if (session.socket) {
      try {
        session.socket.end(undefined);
      } catch (e) {}
      session.socket = null;
    }
    session.ourJid = null;
    session.currentQR = null;
    session.connectionStatus = 'disconnected';
    session.pendingLinkToken = null;

    const authDir = path.join(AUTH_BASE, 'team_' + teamId);
    if (fs.existsSync(authDir)) {
      fs.rmSync(authDir, { recursive: true });
      fs.mkdirSync(authDir, { recursive: true });
    }

    await makeSocket(teamId);
    res.json({ ok: true, message: 'Reconnecting to get a new QR code.' });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/send-message', async (req, res) => {
  const teamId = getTeamId(req);
  if (!teamId) {
    return res.status(400).json({ error: 'team_id required' });
  }

  const session = getOrCreateSession(teamId);
  if (session.connectionStatus !== 'connected' || !session.socket) {
    return res.status(503).json({ error: 'WhatsApp not connected. Scan QR first.' });
  }

  const { to, body } = req.body || {};
  if (!to || !body) {
    return res.status(422).json({ error: 'Missing "to" or "body"' });
  }

  const jid = to.includes('@') ? to : `${String(to).replace(/\D/g, '')}@s.whatsapp.net`;
  try {
    const sent = await session.socket.sendMessage(jid, { text: body });
    res.json({ id: sent?.key?.id, success: true });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/send-media', async (req, res) => {
  const teamId = getTeamId(req);
  if (!teamId) {
    return res.status(400).json({ error: 'team_id required' });
  }

  const session = getOrCreateSession(teamId);
  if (session.connectionStatus !== 'connected' || !session.socket) {
    return res.status(503).json({ error: 'WhatsApp not connected. Scan QR first.' });
  }

  const { to, mediaUrl, caption } = req.body || {};
  if (!to || !mediaUrl) {
    return res.status(422).json({ error: 'Missing "to" or "mediaUrl"' });
  }

  const jid = to.includes('@') ? to : `${String(to).replace(/\D/g, '')}@s.whatsapp.net`;
  try {
    const resp = await fetch(mediaUrl);
    const buffer = Buffer.from(await resp.arrayBuffer());
    const contentType = (resp.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
    const urlPath = new URL(mediaUrl).pathname || '';
    let messageContent;
    if (contentType.startsWith('audio/') || /\.(webm|ogg|mp3|m4a|wav|aac|opus)$/i.test(urlPath)) {
      messageContent = { audio: buffer, mimetype: contentType || 'audio/webm', ptt: true };
      if (caption) messageContent.caption = caption;
    } else if (contentType.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp)$/i.test(urlPath)) {
      messageContent = { image: buffer, caption: caption || undefined };
    } else if (contentType.startsWith('video/') || /\.(mp4|webm|mov)$/i.test(urlPath)) {
      messageContent = { video: buffer, caption: caption || undefined };
    } else {
      messageContent = { document: buffer, caption: caption || undefined };
    }
    const sent = await session.socket.sendMessage(jid, messageContent);
    res.json({ id: sent?.key?.id, success: true });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/logout', async (req, res) => {
  const teamId = getTeamId(req);
  if (!teamId) return res.status(400).json({ error: 'team_id required' });

  const session = getOrCreateSession(teamId);
  try {
    if (session.socket) {
      try {
        await session.socket.logout();
      } catch (e) {}
      session.socket = null;
    }
    session.ourJid = null;
    session.currentQR = null;
    session.connectionStatus = 'disconnected';
    const authDir = path.join(AUTH_BASE, 'team_' + teamId);
    if (fs.existsSync(authDir)) {
      fs.rmSync(authDir, { recursive: true });
    }
    await makeSocket(teamId);
    res.json({ success: true, message: 'WhatsApp session closed. Scan QR to link again.' });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

process.on('uncaughtException', (err) => {
  console.error('Fatal:', err.message || err);
  process.exit(1);
});
process.on('unhandledRejection', (reason, promise) => {
  console.error('Unhandled rejection:', reason);
  process.exit(1);
});

if (!fs.existsSync(AUTH_BASE)) {
  fs.mkdirSync(AUTH_BASE, { recursive: true });
}

app.listen(PORT, () => {
  console.log(`WhatsApp service (multi-tenant) listening on http://localhost:${PORT}`);
  console.log(`Use ?team_id=<id> for /status, /qr, /qr.png, /refresh and body team_id for /send-message`);
  console.log(`Laravel webhook: ${LARAVEL_WEBHOOK_URL}`);
});
