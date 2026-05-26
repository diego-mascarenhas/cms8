<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style" dir="ltr" data-theme="theme-default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('WhatsApp connection') }} · Humano</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-chat.css') }}" />
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bs-body-bg, #f5f5f9);
        }
        .presentation-chat-embed {
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 1rem;
        }
        #chat-qr-container,
        #chat-history-qr-container {
            position: relative;
            min-width: 200px;
            min-height: 200px;
        }
        #chat-qr-container.chat-qr-loading,
        #chat-history-qr-container.chat-qr-loading {
            background-color: transparent;
            border-radius: 0;
        }
        #chat-qr-container.chat-qr-loading .chat-qr-fallback-frame,
        #chat-history-qr-container.chat-qr-loading .chat-qr-fallback-frame {
            opacity: 0.65;
        }
        .chat-qr-fallback-frame {
            width: 200px;
            height: 200px;
            background-color: var(--bs-gray-75, #eceef2);
            box-shadow: none;
        }
        .chat-qr-fallback-pattern {
            position: absolute;
            inset: -10px;
            z-index: 0;
            pointer-events: none;
            background-color: #dfe3ea;
            background-image:
                linear-gradient(90deg, rgba(67, 89, 113, 0.22) 50%, transparent 50%),
                linear-gradient(rgba(67, 89, 113, 0.22) 50%, transparent 50%);
            background-size: 7px 7px;
            filter: blur(3px);
            opacity: 0.55;
        }
        .chat-qr-fallback-vignette {
            z-index: 1;
            background: radial-gradient(
                ellipse 70% 70% at 50% 50%,
                rgba(255, 255, 255, 0.88) 0%,
                rgba(255, 255, 255, 0.35) 55%,
                rgba(255, 255, 255, 0.12) 100%
            );
            pointer-events: none;
        }
        .chat-qr-fallback-frame .chat-qr-loading-overlay {
            z-index: 3;
            background: rgba(255, 255, 255, 0.82);
            display: flex !important;
        }
    </style>
</head>
<body>
    <div class="presentation-chat-embed">
        @include('homes.humano.presentations.partials.chat-whatsapp-sidebar-demo')
    </div>
</body>
</html>
