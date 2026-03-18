<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://use.typekit.net/fbh6wfi.css">
    <link rel="stylesheet" href="{{ asset('homes/wapify/css/style.css') }}">
    <style>
        .wapify-section { min-height: 100vh; display: flex; align-items: center; }
        .wapify-section .row { width: 100%; }
        .wapify-whatsapp-btn { display: inline-block !important; visibility: visible !important; margin-top: 1.5rem; }
    </style>
    <title>Wapify</title>
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
                <img src="{{ asset('homes/wapify/img/logo.png') }}" alt="Wapify">
            </a>
        </div>
    </nav>
    <header class="valign-wrapper">
        <div class="row">
            <div class="col l6 m6 s12 offset-l6">
                <h1 class="black-text">Lleva tu negocio a WhatsApp y conecta con más clientes</h1>
                <a href="#qr" class="waves-effect waves-light btn lime accent-3 black-text" aria-label="{{ __('Scroll to QR section') }}">
                    <i class="material-icons">arrow_drop_down</i>
                </a>
            </div>
        </div>
    </header>
    <section class="container wapify-section">
        <div class="row valign-wrapper">
            <div class="col l6 m6 s12">
                @php
                    $phone = preg_replace('/\D/', '', (string) config('app.wapify_whatsapp_phone', ''));
                    $whatsappText = (string) config('app.wapify_whatsapp_text', 'Hola!');
                    $storeUrl = $phone !== '' ? 'https://api.whatsapp.com/send/?phone=' . $phone . '&text=' . rawurlencode($whatsappText) : '';
                    $storeQrImageUrl = $storeUrl !== '' ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($storeUrl) : null;
                @endphp
                @if($storeQrImageUrl)
                <div class="center-align">
                    <div class="card white z-depth-2 center-align" style="border-radius: 4px; display: inline-block; padding: 0.75rem;">
                        <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('Open store in WhatsApp') }}">
                            <img src="{{ $storeQrImageUrl }}" alt="{{ __('Scan to open store in WhatsApp') }}" style="display: block; width: 220px; height: 220px;">
                        </a>
                    </div>
                    <p class="wapify-whatsapp-btn" style="margin-bottom: 0;">
                        <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="waves-effect waves-light btn white black-text z-depth-1 wapify-whatsapp-btn" style="text-transform: uppercase;">
                            {{ __('Open in WhatsApp') }}
                        </a>
                    </p>
                </div>
                @else
                <img src="{{ asset('homes/wapify/img/hero.png') }}" alt="" class="responsive-img">
                @endif
            </div>
            <article class="col l6 m6 s12">
                <h2 id="qr" class="black-text">{{ __('Scan the QR and try it now!') }}</h2>
                <a href="#logo" class="brand-logo">
                    <img src="{{ asset('homes/wapify/img/logo.png') }}" alt="Wapify">
                </a>
            </article>
        </div>
    </section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script src="{{ asset('homes/wapify/js/script.js') }}"></script>
</body>
</html>
