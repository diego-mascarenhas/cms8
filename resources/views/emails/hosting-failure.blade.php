<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error de hosting</title>
</head>
<body>
    <h2>Error al crear un hosting</h2>
    <p><strong>Dominio:</strong> {{ $context['domain'] ?? '—' }}</p>
    <p><strong>Usuario:</strong> {{ $context['username'] ?? '—' }}</p>
    <p><strong>Quién lo pidió:</strong> {{ $context['user'] ?? '—' }}</p>
    <p><strong>Detalle técnico:</strong></p>
    <p>{{ $technical }}</p>
</body>
</html>
