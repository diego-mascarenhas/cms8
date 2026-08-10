<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Your project quote is ready: :project', ['project' => $projectName]) }}</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827; line-height: 1.5; }
        .wrap { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .pad { padding: 28px 24px; }
        .logo { text-align: center; margin-bottom: 20px; }
        .logo img { height: 36px; width: auto; }
        h1 { margin: 0 0 12px; font-size: 20px; text-align: center; }
        p { margin: 0 0 14px; font-size: 15px; color: #374151; text-align: center; }
        .btn-wrap { text-align: center; margin: 24px 0 12px; }
        a.btn { display: inline-block; padding: 12px 24px; background: #4361f7; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 15px; }
        .muted { font-size: 13px; color: #6b7280; text-align: center; margin-top: 18px; }
        .footer { font-size: 12px; color: #9ca3af; text-align: center; padding: 0 24px 24px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pad">
            <div class="logo">
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" height="36">
            </div>
            <h1>{{ __('Hello :name', ['name' => $recipientName]) }}</h1>
            <p>
                {{ __('We have prepared the quote for :project.', ['project' => $projectName]) }}
                @if ($enterpriseName !== '')
                    ({{ $enterpriseName }})
                @endif
            </p>
            <p>{{ __('Please review it and accept it or request a reformulation from the link below.') }}</p>
            <div class="btn-wrap">
                <a class="btn" href="{{ $previewUrl }}">{{ __('View quote') }}</a>
            </div>
            <p class="muted">{{ __('The project will not start until 30% of the payment is received.') }}</p>
        </div>
        <div class="footer">{{ $appName }}</div>
    </div>
    @if (! empty($trackingPixelUrl))
        <img src="{{ $trackingPixelUrl }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">
    @endif
</body>
</html>
