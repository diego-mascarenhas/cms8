<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compartir contraseña segura</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7fb; margin: 0; padding: 24px; }
        .card { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb; }
        .muted { color: #6b7280; }
        .alert { padding: 12px; border-radius: 6px; }
        .warning { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
        .danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="card">
        @if($status === 'reveal_prompt')
            <h2>Contraseña compartida de forma segura</h2>
            <p class="muted">Este enlace es de un solo uso. Las apps de mensajería y el correo a veces abren el enlace en segundo plano para generar vista previa; por eso debes confirmar aquí para ver el secreto.</p>
            <form method="post" action="{{ route('passwords.share.reveal', ['token' => $token]) }}" class="mt-3">
                @csrf
                <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:16px;">
                    Mostrar contraseña
                </button>
            </form>
        @elseif($status === 'ok')
            <h2>Contraseña compartida de forma segura</h2>
            <p class="muted">Este enlace es de un solo uso. El secreto ya quedó mostrado; no compartas esta página.</p>
            <p><strong>Nombre:</strong> {{ $name }}</p>
            <p><strong>Usuario:</strong> {{ $username ?: '—' }}</p>
            <p><strong>Contraseña:</strong> <code>{{ $password }}</code></p>
            <p><strong>URL:</strong> {{ $url ?: '—' }}</p>
            <p><strong>Notas:</strong> {{ $notes ?: '—' }}</p>
        @elseif($status === 'expired')
            <div class="alert warning">Este enlace seguro ha expirado.</div>
        @elseif($status === 'consumed')
            <div class="alert warning">Este enlace seguro ya fue usado y no está disponible.</div>
        @else
            <div class="alert danger">Este enlace seguro es inválido.</div>
        @endif
    </div>
</body>
</html>
