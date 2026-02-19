<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Consulta sobre Tarea</title>
	<style>
		body {
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			line-height: 1.6;
			color: #333;
			max-width: 600px;
			margin: 0 auto;
			padding: 20px;
			background-color: #f4f4f4;
		}
		.container {
			background-color: #ffffff;
			border-radius: 8px;
			padding: 30px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		.header {
			text-align: center;
			padding-bottom: 20px;
			border-bottom: 2px solid #7367f0;
			margin-bottom: 30px;
		}
		.header h1 {
			color: #7367f0;
			margin: 0;
			font-size: 24px;
		}
		.content {
			margin-bottom: 30px;
		}
		.task-info {
			background-color: #f8f9fa;
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.task-info p {
			margin: 5px 0;
		}
		.task-info strong {
			color: #7367f0;
		}
		.message-box {
			background-color: #fff;
			border-left: 4px solid #7367f0;
			padding: 15px;
			margin: 20px 0;
		}
		.button {
			display: inline-block;
			padding: 12px 30px;
			background-color: #7367f0;
			color: #ffffff !important;
			text-decoration: none;
			border-radius: 6px;
			font-weight: bold;
			text-align: center;
			margin: 20px 0;
		}
		.button:hover {
			background-color: #5e50ee;
		}
		.footer {
			text-align: center;
			margin-top: 30px;
			padding-top: 20px;
			border-top: 1px solid #e0e0e0;
			font-size: 12px;
			color: #666;
		}
		.btn-container {
			text-align: center;
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1>Consulta sobre Tarea</h1>
		</div>

		<div class="content">
			<p>Estimado/a <strong>{{ $enterprise->name }}</strong>,</p>

			<p>Hemos recibido una consulta relacionada con una tarea de su proyecto:</p>

			<div class="task-info">
				<p><strong>Proyecto:</strong> {{ $task->project->name ?? 'N/A' }}</p>
				<p><strong>Tarea:</strong> {{ $task->title }}</p>
				@if($task->description)
					<p><strong>Descripción:</strong> {{ $task->description }}</p>
				@endif
			</div>

			<div class="message-box">
				<strong>Mensaje:</strong>
				<p>{{ $body }}</p>
			</div>

			<p>Puede acceder a la landing del proyecto (ver el mensaje, las tareas y enviar su respuesta) mediante el siguiente enlace seguro con token. Solo tiene que hacer clic:</p>

			<div class="btn-container">
				<a href="{{ $responseUrl }}" class="button">Acceder a la landing del proyecto</a>
			</div>

			<p style="font-size: 12px; color: #666; margin-top: 20px;">
				Si el botón no funciona, copie y pegue este enlace en su navegador (acceso con token):<br>
				<a href="{{ $responseUrl }}" style="color: #7367f0;">{{ $responseUrl }}</a>
			</p>
		</div>

		<div class="footer">
			<p>Este es un mensaje automático, por favor no responda a este email.</p>
			<p>© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
		</div>
	</div>
</body>
</html>

