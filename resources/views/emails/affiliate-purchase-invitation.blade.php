<!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('affiliate_invitation.page_title', ['inviter' => $inviterLabel, 'app' => $appName]) }}</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.45; }
        .wrap { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); }
        .pad { padding: 28px 24px 24px; }
        .logo-row { text-align: center; margin-bottom: 20px; }
        .logo-row img { height: 40px; width: auto; }
        h1 { margin: 0 0 10px; font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; }
        .intro { margin: 0 0 20px; font-size: 15px; color: #334155; text-align: center; }
        .plan-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 20px 16px; margin: 0 0 24px; background: #fff; }
        .plan-image { text-align: center; margin-bottom: 12px; }
        .plan-image img { height: 140px; width: auto; max-width: 100%; }
        .plan-card h2 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #0f172a; text-align: center; }
        .plan-card .desc { font-size: 14px; color: #64748b; text-align: center; margin: 0 0 16px; }
        .plan-card ul { margin: 0; padding: 0; list-style: none; }
        .plan-card li { font-size: 14px; color: #334155; margin-bottom: 8px; padding-left: 22px; position: relative; }
        .plan-card li::before { content: '✓'; position: absolute; left: 0; color: #696cff; font-weight: 700; }
        .btn-wrap { text-align: center; margin: 0 0 10px; }
        a.btn { display: inline-block; padding: 14px 28px; text-decoration: none; border-radius: 999px; font-weight: 700; font-size: 15px; margin: 6px 0; }
        a.btn-primary { background: #696cff; color: #ffffff !important; }
        a.btn-secondary { background: #ffffff; color: #696cff !important; border: 2px solid #696cff; }
        .muted { font-size: 13px; color: #64748b; text-align: center; margin: 16px 0 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pad">
            <div class="logo-row">
                <a href="{{ $pricingUrl }}" style="text-decoration: none;">
                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" height="40" style="height: 40px; width: auto;">
                </a>
            </div>

            <h1>{{ __('affiliate_invitation.greeting', ['name' => $inviteeName]) }}</h1>
            <p class="intro">
                @php
                    $introHtml = __('affiliate_invitation.intro', [
                        'inviter' => '<strong>'.e($inviterLabel).'</strong>',
                        'app' => '<strong>'.e($appName).'</strong>',
                        'plan' => '<strong>'.e($planName).'</strong>',
                    ]);
                @endphp
                {!! $introHtml !!}
            </p>

            <div class="plan-card">
                <div class="plan-image">
                    <img src="{{ $planImageUrl }}" alt="{{ $planName }}" height="140" style="height: 140px; width: auto; max-width: 100%;">
                </div>
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

            <div class="btn-wrap">
                <a href="{{ $checkoutUrl }}" class="btn btn-primary">{{ __('affiliate_invitation.cta_subscribe', ['plan' => $planName]) }}</a>
            </div>
            <div class="btn-wrap">
                <a href="{{ $pricingUrl }}" class="btn btn-secondary">{{ __('affiliate_invitation.cta_pricing') }}</a>
            </div>

            <p class="muted" style="margin-bottom: 0;">{{ __('affiliate_invitation.footer') }}</p>
        </div>
    </div>
    @if(!empty($trackingPixelUrl))
        <img src="{{ $trackingPixelUrl }}" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;">
    @endif
</body>
</html>
