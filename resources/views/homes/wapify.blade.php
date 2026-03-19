<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('wapify.meta_description') }}">

    @php
        $wapifyOgImageUrl = url('/homes/wapify/img/og-wapify.png');
        $wapifyPageUrl = route('wapify');
    @endphp
    <link rel="canonical" href="{{ $wapifyPageUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $wapifyPageUrl }}">
    <meta property="og:title" content="{{ __('wapify.og_title') }}">
    <meta property="og:description" content="{{ __('wapify.meta_description') }}">
    <meta property="og:image" content="{{ $wapifyOgImageUrl }}">
    <meta property="og:image:secure_url" content="{{ $wapifyOgImageUrl }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ __('wapify.brand_alt') }}">
    <meta property="og:site_name" content="{{ __('wapify.og_site_name') }}">
    <meta property="og:locale" content="es_AR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ __('wapify.og_title') }}">
    <meta name="twitter:description" content="{{ __('wapify.meta_description') }}">
    <meta name="twitter:image" content="{{ $wapifyOgImageUrl }}">
    <meta name="twitter:image:alt" content="{{ __('wapify.brand_alt') }}">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://use.typekit.net/fbh6wfi.css">
    <link rel="stylesheet" href="{{ asset('homes/wapify/css/style.css') }}">
    <title>{{ __('wapify.page_title') }}</title>
    @if(config('app.google_analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.google_analytics_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('app.google_analytics_id') }}');
    </script>
    @endif
</head>
<body class="lime accent-3">
    <nav class="lime accent-3">
        <div class="nav-wrapper">
            <a href="#qr" id="logo" class="brand-logo">
                <img src="{{ asset('homes/wapify/img/logo.png') }}" alt="{{ __('wapify.brand_alt') }}">
            </a>
        </div>
    </nav>
    <header class="valign-wrapper">
        <div class="row">
            <div class="col l6 m6 s12 offset-l6">
                <h1 class="black-text">{{ __('wapify.hero_heading') }}</h1>
                <a href="#qr" class="waves-effect waves-light btn lime accent-3 black-text" aria-label="{{ __('wapify.scroll_to_qr_aria') }}">
                    <i class="material-icons">arrow_drop_down</i>
                </a>
            </div>
        </div>
    </header>
    @php
        $wa = \App\Helpers\WapifyWhatsAppHelper::resolve();
        $wapifyQrSrc = \App\Helpers\WapifyWhatsAppHelper::qrDataUri($wa['qr_data']);
    @endphp
    <section class="container valign-wrapper wapify-section">
        <div class="row">
            <div class="col l6 m6 s12">
                <div class="center-align">
                    <div class="card white z-depth-2 wapify-qr-card">
                        <a href="{{ $wa['api_url'] }}" target="_blank" rel="noopener noreferrer" title="{{ __('wapify.qr_link_title') }}" class="wapify-qr-link">
                            <img src="{{ $wapifyQrSrc }}" alt="{{ __('wapify.qr_image_alt') }}" class="wapify-qr-img">
                        </a>
                        <p class="black-text wapify-qr-cta">
                            {{ __('wapify.cta_or') }} <a href="{{ route('landing.business-creation') }}" class="black-text wapify-link-muted">{{ __('wapify.cta_create_business') }}</a>
                        </p>
                    </div>
                </div>
            </div>
            <article class="col l6 m6 s12">
                <h2 id="qr" class="black-text">{{ __('wapify.qr_section_heading') }}</h2>
                <a href="#logo" class="brand-logo">
                    <img src="{{ asset('homes/wapify/img/logo.png') }}" alt="{{ __('wapify.brand_alt') }}">
                </a>
            </article>
        </div>
    </section>

    <footer class="lime accent-3 wapify-footer">
        <div class="wapify-footer-inner">
            <a
                href="https://www.idoneo.dev"
                target="_blank"
                rel="noopener noreferrer"
                class="wapify-footer-brand"
                title="{{ __('wapify.partner_link_title') }}"
            >
                <img src="{{ asset('homes/wapify/img/idoneo.svg') }}" alt="{{ __('wapify.partner_logo_alt') }}">
            </a>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script src="{{ asset('homes/wapify/js/script.js') }}"></script>
</body>
</html>
