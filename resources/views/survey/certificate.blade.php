<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Certificado') }} — {{ $survey->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,500;7..72,650&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #102033;
            --ink-soft: #3d4f63;
            --panel: rgba(255, 255, 255, 0.86);
            --line: rgba(16, 32, 51, 0.12);
            --accent: #c4a35a;
            --accent-deep: #8f7335;
            --danger: #9b2c2c;
            --danger-bg: #f8e8e8;
            --bg-1: #d9e2ec;
            --bg-2: #eef3f7;
            --bg-3: #b7c7d8;
            --shadow: 0 28px 60px rgba(16, 32, 51, 0.14);
            --radius: 22px;
        }

        * { box-sizing: border-box; }

        html, body {
            min-height: 100%;
        }

        body {
            margin: 0;
            font-family: Manrope, system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 700px at 12% -10%, rgba(196, 163, 90, 0.22), transparent 55%),
                radial-gradient(900px 600px at 90% 10%, rgba(61, 79, 99, 0.18), transparent 50%),
                linear-gradient(165deg, var(--bg-2), var(--bg-1) 48%, var(--bg-3));
            display: grid;
            place-items: center;
            padding: 32px 18px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.35;
            background-image:
                linear-gradient(rgba(16, 32, 51, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 32, 51, 0.035) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(circle at center, black 35%, transparent 85%);
        }

        .shell {
            width: min(560px, 100%);
            position: relative;
            z-index: 1;
            animation: rise 700ms cubic-bezier(.2, .8, .2, 1) both;
        }

        .mark {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
            animation: rise 700ms cubic-bezier(.2, .8, .2, 1) 80ms both;
        }

        .mark-seal {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1.5px solid var(--accent);
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.55);
            box-shadow: inset 0 0 0 4px rgba(196, 163, 90, 0.18);
            color: var(--accent-deep);
            font-family: Literata, Georgia, serif;
            font-weight: 650;
            font-size: 18px;
            letter-spacing: 0.02em;
        }

        .mark span {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .panel {
            background: var(--panel);
            backdrop-filter: blur(14px);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: clamp(28px, 5vw, 40px);
            position: relative;
            overflow: hidden;
        }

        .panel::before {
            content: "";
            position: absolute;
            inset: 12px;
            border: 1px solid rgba(196, 163, 90, 0.35);
            border-radius: calc(var(--radius) - 8px);
            pointer-events: none;
        }

        .panel > * {
            position: relative;
            z-index: 1;
        }

        h1 {
            margin: 0 0 8px;
            font-family: Literata, Georgia, serif;
            font-weight: 650;
            font-size: clamp(1.85rem, 4vw, 2.35rem);
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .event {
            margin: 0 0 28px;
            color: var(--ink-soft);
            font-size: 1rem;
            line-height: 1.5;
        }

        .event strong {
            color: var(--ink);
            font-weight: 600;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        input[type="email"] {
            width: 100%;
            appearance: none;
            border: 1.5px solid rgba(16, 32, 51, 0.16);
            background: rgba(255, 255, 255, 0.92);
            border-radius: 14px;
            padding: 15px 16px;
            font: inherit;
            font-size: 1.02rem;
            color: var(--ink);
            transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: var(--accent-deep);
            box-shadow: 0 0 0 4px rgba(196, 163, 90, 0.22);
        }

        input[type="email"].is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 4px rgba(155, 44, 44, 0.12);
        }

        .error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--danger-bg);
            color: var(--danger);
            font-size: 0.95rem;
            line-height: 1.4;
            animation: shake 420ms ease both;
        }

        .error svg {
            flex: 0 0 auto;
            margin-top: 1px;
        }

        button {
            margin-top: 22px;
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 15px 18px;
            font: inherit;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #fff;
            background: linear-gradient(135deg, #173049, #102033 55%, #1c3a55);
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(16, 32, 51, 0.22);
            transition: transform 160ms ease, box-shadow 160ms ease, filter 160ms ease;
        }

        button:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
            box-shadow: 0 16px 28px rgba(16, 32, 51, 0.26);
        }

        button:active {
            transform: translateY(0);
        }

        .hint {
            margin: 18px 0 0;
            text-align: center;
            font-size: 0.86rem;
            color: var(--ink-soft);
            line-height: 1.45;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            50% { transform: translateX(4px); }
            75% { transform: translateX(-2px); }
        }

        @media (prefers-reduced-motion: reduce) {
            .shell, .mark, .error {
                animation: none;
            }

            button {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <div class="mark" aria-hidden="true">
            <div class="mark-seal">C</div>
            <span>{{ __('Certificado') }}</span>
        </div>

        <section class="panel">
            <h1>{{ __('Descargar certificado') }}</h1>
            <p class="event">{{ __('Evento') }}: <strong>{{ $survey->title }}</strong></p>

            <form method="POST" action="{{ route('survey.certificate.download', $survey) }}" novalidate>
                @csrf
                <div class="field">
                    <label for="email">{{ __('Email con el que te inscribiste') }}</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        placeholder="nombre@empresa.com"
                        class="@error('email') is-invalid @enderror"
                    >
                    @error('email')
                        <div class="error" role="alert">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 7.5v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <circle cx="12" cy="16.2" r="1" fill="currentColor"/>
                            </svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>
                <button type="submit">{{ __('Descargar') }}</button>
            </form>

            <p class="hint">{{ __('Usá el mismo correo de la inscripción. El archivo se descarga con tu nombre.') }}</p>
        </section>
    </main>
</body>
</html>
