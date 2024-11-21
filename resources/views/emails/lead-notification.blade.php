<!DOCTYPE html>
<html>
<head>
    <title>Nuevo Lead</title>
</head>
<body>
    <h2>Nuevo Lead Recibido</h2>
    <p><strong>Nombre:</strong> {{ $leadData['name'] }}</p>
    <p><strong>Email:</strong> {{ $leadData['email'] }}</p>
    <p><strong>Teléfono:</strong> {{ $leadData['phone'] }}</p>
</body>
</html> 