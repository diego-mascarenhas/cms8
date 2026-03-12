<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Tu informe de negocio') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; margin: 0; word-wrap: break-word; overflow-wrap: break-word; }
        .container { max-width: 600px; width: 100%; margin: 0 auto; box-sizing: border-box; overflow-wrap: break-word; word-wrap: break-word; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 24px; }
        .header-logo { display: block; margin-bottom: 16px; }
        .header-logo img { height: 40px; width: auto; vertical-align: middle; }
        .header h1 { margin: 0; font-size: 1.5rem; color: #1e40af; }
        .section { margin-bottom: 24px; }
        .section h2 { font-size: 1.1rem; color: #1e40af; margin: 0 0 12px 0; }
        .section p { margin: 0 0 8px 0; }
        .summary-box { background: #eff6ff; border-left: 4px solid #2563eb; padding: 16px 20px; margin: 12px 0; overflow-wrap: break-word; word-wrap: break-word; max-width: 100%; box-sizing: border-box; }
        .insight-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; margin: 12px 0; font-size: 0.95rem; overflow-wrap: break-word; word-wrap: break-word; max-width: 100%; box-sizing: border-box; }
        .report-content { font-size: 0.9375rem; line-height: 1.6; overflow-wrap: break-word; word-wrap: break-word; max-width: 100%; }
        .report-content h1, .report-content h2, .report-content h3 { font-weight: 600; color: #1e293b; margin: 0 0 8px 0; }
        .report-content h1 { font-size: 1.25rem; margin-top: 16px; }
        .report-content h1:first-child { margin-top: 0; }
        .report-content h2 { font-size: 1.1rem; margin-top: 16px; }
        .report-content h3 { font-size: 1rem; margin-top: 12px; }
        .report-content p { margin: 0 0 10px 0; }
        .report-content p:last-child { margin-bottom: 0; }
        .report-content ul, .report-content ol { margin: 0 0 10px 0; padding-left: 20px; overflow-wrap: break-word; word-wrap: break-word; }
        .report-content li { margin-bottom: 4px; overflow-wrap: break-word; word-wrap: break-word; }
        .report-content strong { font-weight: 600; }
        .report-content em { font-style: italic; }
        .report-content a { color: #2563eb; text-decoration: underline; }
        .report-content hr { border: none; border-top: 1px solid #e2e8f0; margin: 16px 0; }
        .footer { font-size: 12px; color: #64748b; margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
        .label { font-weight: 600; color: #475569; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="{{ config('app.url') }}" class="header-logo" style="display: block; margin-bottom: 16px;">
                <img src="{{ url(Helper::logoAsset('dark')) }}" alt="{{ config('app.name') }}" height="40" style="height: 40px; width: auto; vertical-align: middle;" />
            </a>
            <h1>{{ __('Tu informe de negocio') }}</h1>
            <p>{{ __('Resumen de la configuración y recomendaciones generadas.') }}</p>
        </div>

        @if(!empty($config['business_name']) || !empty($config['business_industry']) || !empty($config['business_location']))
        <div class="section">
            <h2>{{ __('Datos del negocio') }}</h2>
            @if(!empty($config['business_name']))
                <p><span class="label">{{ __('Nombre') }}:</span> {{ $config['business_name'] }}</p>
            @endif
            @if(!empty($config['business_industry']))
                <p><span class="label">{{ __('Rubro / Sector') }}:</span> {{ $config['business_industry'] }}</p>
            @endif
            @if(!empty($config['business_location']))
                <p><span class="label">{{ __('Ubicación') }}:</span> {{ $config['business_location'] }}</p>
            @endif
            @if(!empty($config['city']))
                <p><span class="label">{{ __('Ciudad') }}:</span> {{ $config['city'] }}</p>
            @endif
            @if(!empty($config['business_email']))
                <p><span class="label">{{ __('Email') }}:</span> {{ $config['business_email'] }}</p>
            @endif
            @if(!empty($config['business_whatsapp']))
                <p><span class="label">{{ __('WhatsApp') }}:</span> {{ $config['business_whatsapp'] }}</p>
            @endif
        </div>
        @endif

        @if($summary)
        <div class="section">
            <h2>{{ __('Resumen para mejorar tu empresa') }}</h2>
            <div class="summary-box">
                <div class="report-content">{!! \Illuminate\Support\Str::markdown($summary) !!}</div>
            </div>
        </div>
        @endif

        @if(!empty($insights['potential_clients_summary']))
        <div class="section">
            <h2>{{ __('Informe de mercado') }}</h2>
            <div class="insight-box">
                <div class="report-content">{!! \Illuminate\Support\Str::markdown($insights['potential_clients_summary']) !!}</div>
            </div>
        </div>
        @endif

        <div class="footer">
            <p>{{ __('Este correo se ha generado a partir del formulario de creación de negocio.') }}</p>
            <p>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
