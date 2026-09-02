<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Quote: :project', ['project' => $projectName]) }}</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827; line-height: 1.5; }
        .wrap { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .pad { padding: 28px 24px; }
        h1 { margin: 0 0 16px; font-size: 20px; font-weight: 600; }
        h2 { margin: 20px 0 8px; font-size: 15px; font-weight: 700; color: #111827; }
        p { margin: 0 0 14px; font-size: 15px; color: #374151; }
        .section p { margin: 0 0 8px; }
        .section p:last-child { margin-bottom: 0; }
        .btn-wrap { text-align: center; margin: 24px 0 4px; }
        a.btn { display: inline-block; padding: 12px 24px; background: #0d9488; color: #fff !important; text-decoration: none; border-radius: 16px; font-weight: 700; font-size: 15px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pad">
            <h1>{{ __('Hello :name,', ['name' => $recipientName]) }}</h1>
            <p>{{ __('This quote covers the following project:') }}</p>
            @if ($requestSummary !== '')
                <p>{{ $requestSummary }}</p>
            @endif
            @foreach ($sections as $section)
                <div class="section">
                    <h2>{{ $section['title'] }}</h2>
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            @endforeach
            <div class="btn-wrap">
                <a class="btn" href="{{ $previewUrl }}" style="background:#0d9488;color:#ffffff;">{{ __('View quote') }}</a>
            </div>
        </div>
    </div>
    @if (! empty($trackingPixelUrl))
        <img src="{{ $trackingPixelUrl }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">
    @endif
</body>
</html>
