@php
    $favicon = Helper::logoAsset('iso');
    $faviconPath = parse_url($favicon, PHP_URL_PATH) ?: $favicon;
    $faviconType = str_ends_with($faviconPath, '.svg') ? 'image/svg+xml' : 'image/png';
@endphp
<link rel="icon" href="{{ $favicon }}" type="{{ $faviconType }}">
<link rel="apple-touch-icon" href="{{ $favicon }}">
