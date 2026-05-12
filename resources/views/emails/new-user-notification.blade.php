<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Hola, {{ $displayName }}!</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.45; }
        .wrap { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); }
        .pad { padding: 28px 24px 24px; }
        .logo-row { text-align: center; margin-bottom: 16px; }
        .logo-row img { height: 40px; width: auto; }
        h1 { margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; }
        p { margin: 0 0 14px; font-size: 15px; color: #334155; text-align: center; }
        .muted { font-size: 13px; color: #64748b; }
        .btn-wrap { text-align: center; margin: 22px 0 8px; }
        a.btn { display: inline-block; padding: 14px 28px; background: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 999px; font-weight: 700; font-size: 15px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pad">
            <div class="logo-row">
                <a href="{{ config('app.url') }}" style="text-decoration: none;">
                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" height="40" style="height: 40px; width: auto;">
                </a>
            </div>
            <h1>¡Hola, {{ $displayName }}!</h1>
            @if($showBrandLine)
            <p><strong>{{ $brand }}</strong><br>Ya tienes cuenta. Solo falta <strong>tu contraseña</strong> (un clic).</p>
            @else
            <p>Ya tienes cuenta. Solo falta <strong>tu contraseña</strong> (un clic).</p>
            @endif
            <p class="muted">El enlace caduca en ~60&nbsp;min.</p>
            <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn">Activar acceso</a>
            </div>
            <p class="muted" style="margin-bottom: 0;">¿No pediste esto? Ignora este correo.</p>
        </div>
    </div>
</body>
</html>
