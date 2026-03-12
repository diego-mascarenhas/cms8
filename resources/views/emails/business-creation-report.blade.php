<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Tu informe de negocio') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 1.5rem; color: #1e40af; }
        .section { margin-bottom: 24px; }
        .section h2 { font-size: 1.1rem; color: #1e40af; margin: 0 0 10px 0; }
        .section p { margin: 0 0 8px 0; }
        .summary-box { background: #eff6ff; border-left: 4px solid #2563eb; padding: 12px 16px; margin: 12px 0; }
        .insight-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; margin: 12px 0; font-size: 0.95rem; }
        .footer { font-size: 12px; color: #64748b; margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
        .label { font-weight: 600; color: #475569; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
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
                {!! nl2br(e($summary)) !!}
            </div>
        </div>
        @endif

        @if(!empty($insights['potential_clients_summary']))
        <div class="section">
            <h2>{{ __('Informe de mercado') }}</h2>
            <div class="insight-box">
                {!! \Illuminate\Support\Str::markdown($insights['potential_clients_summary']) !!}
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
