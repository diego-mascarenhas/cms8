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
                <h1 class="black-text">Your audio transcriber for WhatsApp</h1>
                <a href="#qr" class="waves-effect waves-light btn lime accent-3 black-text" aria-label="Scroll to QR section">
                    <i class="material-icons">arrow_drop_down</i>
                </a>
            </div>
        </div>
    </header>
    <section class="container valign-wrapper">
        <div class="row">
            <div class="col l6 m6 s12">
                <img src="{{ asset('homes/wapify/img/hero.png') }}" alt="">
            </div>
            <article class="col l6 m6 s12">
                <h2 id="qr" class="black-text">Scan the QR code and you're done!</h2>
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
