<!DOCTYPE html>
<html>
<head>
    <title>Test Email</title>
</head>
<body>
    <h1>Test de Email</h1>
    <p>Este es un email de prueba desde el entorno local.</p>
    <p>Configuración actual:</p>
    <ul>
        <li>MAIL_HOST: {{ config('mail.mailers.smtp.host') }}</li>
        <li>MAIL_PORT: {{ config('mail.mailers.smtp.port') }}</li>
        <li>MAIL_FROM_ADDRESS: {{ config('mail.from.address') }}</li>
        <li>Fecha y hora: {{ now() }}</li>
    </ul>
</body>
</html> 