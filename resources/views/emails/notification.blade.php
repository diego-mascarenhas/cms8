<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->subject }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #6f42c1, #007bff);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 300;
        }
        .content {
            padding: 30px;
        }
        .message-content {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        .metadata {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 14px;
        }
        .metadata strong {
            color: #495057;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $team->name ?? 'Humano' }}</h1>
        </div>
        
        <div class="content">
            <h2>{{ $notification->subject }}</h2>
            
            <div class="message-content">
                {!! nl2br(e($notification->message)) !!}
            </div>
            
            @if($notification->type && $notification->reference)
                <div class="metadata">
                    <strong>Tipo:</strong> {{ $notification->type->name }}<br>
                    @if($notification->reference)
                        <strong>Referencia:</strong> {{ $notification->reference }}<br>
                    @endif
                    <strong>Fecha:</strong> {{ $notification->created_at->format('d/m/Y H:i') }}
                </div>
            @endif
            
            @if($notification->metadata && isset($notification->metadata['action_url']))
                <p>
                    <a href="{{ $notification->metadata['action_url'] }}" class="btn">
                        {{ $notification->metadata['action_text'] ?? 'Ver detalles' }}
                    </a>
                </p>
            @endif
        </div>
        
        <div class="footer">
            <p>
                Enviado por <strong>{{ $sender->name }}</strong> desde {{ $team->name ?? 'Humano' }}<br>
                {{ $notification->created_at->format('d/m/Y \a \l\a\s H:i') }}
            </p>
            <p>
                <small>Este es un mensaje automático, por favor no respondas a este email.</small>
            </p>
        </div>
    </div>
</body>
</html> 