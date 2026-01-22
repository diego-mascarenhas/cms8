<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a tu cuenta - {{ $team->name }}</title>
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
        .header .access-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .welcome-message {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .cta-button {
            display: inline-block;
            padding: 15px 30px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }
        .cta-button:hover {
            background-color: #0056b3;
        }
        .info-box {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 14px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        .security-note {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 14px;
        }
        .team-info {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="access-icon">🔐</div>
            <h1>Acceso a tu cuenta</h1>
        </div>
        
        <div class="content">
            <div class="welcome-message">
                <h2>Hola {{ $user->name }},</h2>
                <p>Has recibido un link de acceso directo a tu cuenta en <strong>{{ $team->name }}</strong>.</p>
            </div>

            <div class="team-info">
                <strong>Cuenta:</strong> {{ $team->name }}<br>
                <strong>Tu email:</strong> {{ $user->email }}
            </div>

            <p>Haz clic en el siguiente botón para acceder directamente a tu cuenta sin necesidad de ingresar tu contraseña:</p>

            <div style="text-align: center;">
                <a href="{{ $autologinUrl }}" class="cta-button">
                    ✈️ Acceder a mi cuenta
                </a>
            </div>

            <div class="security-note">
                <strong>🔒 Nota de seguridad:</strong><br>
                Este link es válido por 30 días. Por razones de seguridad, si sospechas que este link ha sido comprometido, contacta inmediatamente con el administrador de tu cuenta.
            </div>

            <p>Si el botón no funciona, también puedes copiar y pegar este enlace en tu navegador:</p>
            <div class="info-box">
                <a href="{{ $autologinUrl }}" style="word-break: break-all;">{{ $autologinUrl }}</a>
            </div>

            <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.</p>
        </div>

        <div class="footer">
            <p>
                Este email fue enviado desde {{ $team->name }}<br>
                Si no esperabas este email, puedes ignorarlo de forma segura.<br>
                <small>Este es un email automático, por favor no respondas a este mensaje.</small>
            </p>
        </div>
    </div>
</body>
</html>
