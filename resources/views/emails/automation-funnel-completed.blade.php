<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Resumen del embudo') }}</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px;">
    <div style="max-width: 640px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #696cff; color: #ffffff; padding: 24px 28px;">
            <h1 style="margin: 0; font-size: 22px; font-weight: 500;">{{ __('Embudo completado') }}</h1>
            <p style="margin: 8px 0 0; opacity: 0.9;">{{ $automation->name }}</p>
        </div>

        <div style="padding: 28px;">
            <p style="margin-top: 0;">{{ __('Hola :name,', ['name' => $recipientName]) }}</p>
            <p>{{ __('Completaste el embudo. Acá va el resumen:') }}</p>

            @if($teamName)
                <p style="color: #6c757d; font-size: 14px;"><strong>{{ __('Equipo') }}:</strong> {{ $teamName }}</p>
            @endif

            @if(count($summaryLines) > 0)
                <div style="background-color: #f8f9fa; border-left: 4px solid #696cff; padding: 16px 18px; margin: 20px 0; border-radius: 0 4px 4px 0;">
                    <h2 style="margin: 0 0 12px; font-size: 16px;">{{ __('Resumen') }}</h2>
                    <ul style="margin: 0; padding-left: 1.25rem;">
                        @foreach($summaryLines as $line)
                            <li style="margin-bottom: 0.4rem;">{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(count($conversationExcerpts) > 0)
                <h2 style="margin: 24px 0 12px; font-size: 16px;">{{ __('Conversación') }}</h2>
                @foreach($conversationExcerpts as $excerpt)
                    <div style="margin-bottom: 12px; padding: 12px 14px; background-color: {{ ($excerpt['role'] ?? '') === 'user' ? '#eef2ff' : '#f8f9fa' }}; border-radius: 6px;">
                        <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">
                            {{ ($excerpt['role'] ?? '') === 'user' ? __('Vos') : __('Asistente') }}
                        </div>
                        <div style="white-space: pre-wrap;">{{ $excerpt['content'] ?? '' }}</div>
                    </div>
                @endforeach
            @endif

            <p style="margin-bottom: 0; color: #6c757d; font-size: 14px;">
                {{ __('Podés volver a abrir el embudo desde Humano cuando quieras revisarlo.') }}
            </p>
        </div>
    </div>
</body>
</html>
