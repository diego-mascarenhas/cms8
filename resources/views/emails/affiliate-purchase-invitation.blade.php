<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $inviterLabel }} te invita a conocer {{ $appName }}</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.5; }
        .wrap { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); }
        .header { background: linear-gradient(135deg, #6f42c1 0%, #2563eb 100%); color: #ffffff; padding: 28px 24px; text-align: center; }
        .header img { height: 36px; width: auto; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; line-height: 1.35; }
        .header p { margin: 10px 0 0; font-size: 14px; opacity: 0.95; }
        .pad { padding: 28px 24px; }
        p { margin: 0 0 14px; font-size: 15px; color: #334155; }
        .plan-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin: 20px 0; background: #f8fafc; }
        .plan-card h2 { margin: 0 0 8px; font-size: 18px; color: #0f172a; }
        .plan-card .desc { font-size: 14px; color: #475569; margin-bottom: 12px; }
        .plan-card ul { margin: 0; padding-left: 18px; color: #334155; font-size: 14px; }
        .plan-card li { margin-bottom: 6px; }
        .btn-row { text-align: center; margin: 24px 0 8px; }
        a.btn { display: inline-block; padding: 13px 22px; margin: 6px 4px; text-decoration: none; border-radius: 999px; font-weight: 700; font-size: 14px; }
        a.btn-primary { background: #2563eb; color: #ffffff !important; }
        a.btn-secondary { background: #ffffff; color: #2563eb !important; border: 2px solid #2563eb; }
        .muted { font-size: 13px; color: #64748b; word-break: break-all; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <img src="{{ $logoUrl }}" alt="{{ $appName }}" height="36">
            <h1>{{ $inviterLabel }} te invita a conocer {{ $appName }}</h1>
            <p>Hola {{ $inviteeName }}, descubrí el plan <strong>{{ $planName }}</strong></p>
        </div>
        <div class="pad">
            <p>
                <strong>{{ $inviterLabel }}</strong> ({{ $referrerTeam->name }}) cree que {{ $appName }} puede ayudarte a automatizar y hacer crecer tu negocio con inteligencia artificial, sin perder el toque humano.
            </p>

            <div class="plan-card">
                <h2>{{ $planName }}</h2>
                <p class="desc">{{ $planDescription }}</p>
                @if(count($planFeatures) > 0)
                    <ul>
                        @foreach($planFeatures as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="btn-row">
                <a href="{{ $pricingUrl }}" class="btn btn-secondary">Ver todos los planes</a>
                <a href="{{ $checkoutUrl }}" class="btn btn-primary">Suscribirme a {{ $planName }}</a>
            </div>

            <p class="muted">Si los botones no funcionan, copiá este enlace para suscribirte:<br>{{ $checkoutUrl }}</p>
        </div>
    </div>
</body>
</html>
