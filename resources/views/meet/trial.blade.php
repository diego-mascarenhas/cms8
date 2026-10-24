<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Sala de prueba</title>
  <style>
    :root {
      color-scheme: dark;
      --bg: #0f1419;
      --bg-deep: #0b1014;
      --text: #e8eef4;
      --muted: #8b9aab;
      --accent: #3d9cf0;
      --line: rgba(232, 238, 244, 0.12);
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      height: 100%;
      background: var(--bg);
      color: var(--text);
      font-family: "Segoe UI", system-ui, sans-serif;
    }

    body {
      display: flex;
      flex-direction: column;
      min-height: 100dvh;
    }

    header {
      display: flex;
      flex-wrap: wrap;
      gap: 16px 24px;
      align-items: flex-end;
      justify-content: space-between;
      padding: 16px 20px 12px;
      background: var(--bg-deep);
      border-bottom: 1px solid var(--line);
    }

    .eyebrow {
      margin: 0 0 4px;
      color: var(--accent);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    h1 {
      margin: 0;
      font-size: 22px;
      line-height: 1.2;
    }

    .lead, .note {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.4;
    }

    form, .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    label {
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      clip: rect(0 0 0 0);
    }

    input, button, a.button {
      height: 40px;
      border-radius: 8px;
      font: inherit;
      font-size: 14px;
    }

    input {
      width: min(240px, 100%);
      padding: 0 12px;
      border: 1px solid var(--line);
      background: var(--bg);
      color: var(--text);
    }

    button, a.button {
      display: inline-flex;
      align-items: center;
      padding: 0 14px;
      border: 0;
      background: var(--accent);
      color: #0f1419;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
    }

    a.button.secondary, button.secondary {
      background: transparent;
      color: var(--text);
      border: 1px solid var(--line);
    }

    .note {
      margin: 0;
      padding: 8px 20px;
      background: var(--bg-deep);
      border-bottom: 1px solid var(--line);
    }

    #meet {
      flex: 1 1 auto;
      height: calc(100dvh - 148px);
      min-height: 480px;
      background: #000;
    }

    #meet iframe {
      display: block;
      width: 100%;
      height: 100%;
      border: 0;
    }

    .fallback {
      max-width: 36rem;
      margin: 48px auto;
      padding: 0 20px;
      text-align: center;
    }
  </style>
</head>
<body>
  <header>
    <div>
      <p class="eyebrow">meet.jit.si</p>
      <h1>Sala de prueba</h1>
      <p class="lead">Pública y sin contraseña. Quien tenga el enlace puede entrar.</p>
    </div>
    <form id="name-form">
      <label for="display-name">Nombre</label>
      <input id="display-name" name="nombre" maxlength="60" value="{{ $displayName }}" placeholder="Tu nombre en la sala" autocomplete="nickname">
      <button type="submit">Aplicar nombre</button>
    </form>
    <div class="actions">
      <button type="button" class="secondary" id="copy-link">Copiar enlace</button>
      <a class="button secondary" href="{{ $directUrl }}" target="_blank" rel="noopener">Abrir en pestaña</a>
    </div>
  </header>
  <p class="note">La primera persona inicia sesión en meet.jit.si con Google, GitHub o Facebook. El resto entra sin cuenta. La sala no pide contraseña.</p>
  <div id="meet"></div>
  <script src="https://{{ $domain }}/external_api.js"></script>
  <script>
    const roomName = @json($roomName);
    const domain = @json($domain);
    const shareUrl = @json($shareUrl);
    const directUrl = @json($directUrl);
    const parentNode = document.querySelector('#meet');
    const nameInput = document.querySelector('#display-name');

    if (typeof JitsiMeetExternalAPI === 'undefined') {
      parentNode.innerHTML = '<div class="fallback"><p>No se pudo cargar la sala embebida.</p><p><a href="' + directUrl + '">Abrir ' + roomName + ' en meet.jit.si</a></p></div>';
    } else {
      const api = new JitsiMeetExternalAPI(domain, {
        roomName: roomName,
        parentNode: parentNode,
        width: '100%',
        height: '100%',
        lang: 'es',
        userInfo: {
          displayName: nameInput.value.trim()
        },
        configOverwrite: {
          prejoinPageEnabled: true,
          disableDeepLinking: true,
          enableWelcomePage: false
        }
      });

      document.querySelector('#name-form').addEventListener('submit', function (event) {
        event.preventDefault();
        api.executeCommand('displayName', nameInput.value.trim());
      });
    }

    document.querySelector('#copy-link').addEventListener('click', function () {
      const button = this;

      navigator.clipboard.writeText(shareUrl).then(function () {
        button.textContent = 'Enlace copiado';
      }).catch(function () {
        window.prompt('Copia este enlace', shareUrl);
      });
    });
  </script>
</body>
</html>
