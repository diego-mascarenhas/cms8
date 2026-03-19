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
    @php
        $wa = \App\Helpers\WapifyWhatsAppHelper::resolve();
        $wapifyQrSrc = \App\Helpers\WapifyWhatsAppHelper::qrDataUri($wa['qr_data']);
    @endphp
    <section class="container valign-wrapper wapify-section">
        <div class="row">
            <div class="col l6 m6 s12">
                <div class="center-align">
                    <div class="card white z-depth-2 wapify-qr-card">
                        <a href="{{ $wa['api_url'] }}" target="_blank" rel="noopener noreferrer" title="Abrir en WhatsApp" class="wapify-qr-link">
                            <img src="{{ $wapifyQrSrc }}" alt="QR para WhatsApp" class="wapify-qr-img">
                        </a>
                        <p class="black-text wapify-qr-cta">
                            O <a href="{{ route('landing.business-creation') }}" class="black-text wapify-link-muted">Crea tu negocio</a>
                        </p>
                    </div>
                </div>
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
