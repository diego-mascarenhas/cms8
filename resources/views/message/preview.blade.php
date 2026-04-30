<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Vista previa') }}@if ($message) — {{ $message->name }}@endif</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f9;
        }
        .preview-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem 1rem;
            padding: 0.75rem 1rem;
            background: #fff;
            border-bottom: 1px solid rgba(67, 89, 113, 0.12);
        }
        .preview-toolbar strong {
            font-size: 0.9375rem;
        }
        .preview-meta {
            font-size: 0.8125rem;
            color: #697a8d;
            padding: 0.5rem 1rem;
            background: #fff;
            border-bottom: 1px solid rgba(67, 89, 113, 0.12);
            line-height: 1.5;
        }
        .preview-frame-wrap {
            padding: 0;
            background: #f5f5f9;
        }
        iframe.preview-email-frame {
            display: block;
            width: 100%;
            border: 0;
            min-height: calc(100vh - 120px);
            background: #fff;
        }
        .btn-close-preview {
            border: 1px solid rgba(67, 89, 113, 0.2);
            background: #fff;
            color: #566a7f;
            border-radius: 0.375rem;
            padding: 0.35rem 0.85rem;
            font-size: 0.8125rem;
            cursor: pointer;
        }
        .btn-close-preview:hover {
            background: #f5f5f9;
        }
    </style>
</head>
<body>
    <div class="preview-toolbar">
        <div>
            <strong>{{ __('Vista previa del correo') }}</strong>
            @if ($message)
                <span class="text-muted"> — {{ $message->name }}</span>
            @endif
        </div>
        <button type="button" class="btn-close-preview" onclick="window.close()">{{ __('Cerrar') }}</button>
    </div>

    @if ($message && $sampleContact)
        @php
            $emailConfig = auth()->user()?->currentTeam?->getOutgoingEmailConfig() ?? [];
        @endphp
        <div class="preview-meta">
            <strong>{{ __('Para') }}:</strong> {{ $sampleContact->email ?? 'sample@example.com' }}
            <span class="text-muted"> · </span>
            <strong>{{ __('De') }}:</strong>
            {{ $emailConfig['from_name'] ?? __('Remitente') }}
            &lt;{{ $emailConfig['from_address'] ?? 'sender@example.com' }}&gt;
        </div>
    @endif

    <div class="preview-frame-wrap">
        <iframe
            class="preview-email-frame"
            title="{{ __('Vista previa del contenido del correo') }}"
            srcdoc="{{ htmlspecialchars($htmlContent ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) }}"
        ></iframe>
    </div>
</body>
</html>
