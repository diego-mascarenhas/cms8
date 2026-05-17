<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $appName ?? config('app.name'))</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.5; }
        .wrap { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); }
        .pad { padding: 28px 24px 20px; }
        h1 { margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; }
        p { margin: 0 0 14px; font-size: 15px; color: #334155; }
        p.center { text-align: center; }
        .muted { font-size: 13px; color: #64748b; text-align: center; }
        .btn-wrap { text-align: center; margin: 20px 0 8px; }
        a.btn { display: inline-block; padding: 14px 28px; background: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 999px; font-weight: 700; font-size: 15px; }
        a.btn-secondary { background: #0f172a; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
        .footer { padding: 16px 24px 24px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #64748b; }
        .footer p { margin: 0; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pad">
            @include('emails.partials.header', ['logoUrl' => $logoUrl, 'appName' => $appName ?? config('app.name')])

            @yield('content')
        </div>

        @include('emails.partials.footer', ['appName' => $appName ?? config('app.name')])
    </div>
</body>
</html>
