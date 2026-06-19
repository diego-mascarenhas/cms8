<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $mailSubject ?? 'Mensaje' }}</title>
</head>
<body style="font-family: sans-serif; line-height: 1.5; color: #333;">
{!! nl2br(e($plainBody)) !!}
</body>
</html>
