<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Acceso a tus resultados') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f8fafc; }
        .container { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .content { padding: 28px; }
        .code-block { font-size: 28px; font-weight: 700; letter-spacing: 0.2em; padding: 16px 24px; background: #f1f5f9; border-radius: 8px; text-align: center; margin: 20px 0; font-family: ui-monospace, monospace; }
        .muted { color: #64748b; font-size: 14px; }
        .link-fallback { word-break: break-all; font-size: 13px; color: #0f766e; margin-top: 12px; }
        .cta-button { display: inline-block; padding: 14px 28px; background: #0f766e; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h2 style="margin-top: 0;">{{ __('Accede a tus resultados') }}</h2>
            <p>{{ __('Te enviamos el código que solicitaste para ver los resultados de tu búsqueda de prospectos.') }}</p>
            <p class="muted" style="margin-bottom: 8px;">{{ __('Tu código de acceso:') }}</p>
            <div class="code-block" data-code="{{ $code }}">{{ $code }}</div>
            <p class="muted">{{ __('Introduce este código en la página de búsqueda para ver los resultados. El código es de un solo uso y caduca en 24 horas.') }}</p>
            @if($accessUrl)
            <p class="muted" style="margin-top: 20px;">{{ __('Si prefieres, también puedes usar este enlace:') }}</p>
            <p><a href="{{ $accessUrl }}" class="cta-button">{{ __('Ver resultados') }}</a></p>
            <p class="link-fallback"><a href="{{ $accessUrl }}">{{ $accessUrl }}</a></p>
            @endif
            @if(!empty($downloadUrl))
            <p class="muted" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">{{ __('Guarda este enlace para volver a descargar tu archivo CSV cuando quieras (después de pagar):') }}</p>
            <p><a href="{{ $downloadUrl }}" class="cta-button" style="background: #0d9488;">{{ __('Enlace de descarga') }}</a></p>
            <p class="link-fallback"><a href="{{ $downloadUrl }}">{{ $downloadUrl }}</a></p>
            @endif
        </div>
    </div>
</body>
</html>
