<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Responder Consulta - {{ config('app.name') }}</title>
	<link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}">
	<style>
		body {
			background-color: #f8f9fa;
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.response-container {
			max-width: 800px;
			margin: 50px auto;
			padding: 20px;
		}
		.response-card {
			background: white;
			border-radius: 12px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.08);
			padding: 40px;
		}
		.header-section {
			text-align: center;
			margin-bottom: 40px;
			padding-bottom: 30px;
			border-bottom: 2px solid #7367f0;
		}
		.header-section h1 {
			color: #7367f0;
			font-size: 28px;
			margin-bottom: 10px;
		}
		.info-box {
			background-color: #f8f9fa;
			border-left: 4px solid #7367f0;
			padding: 20px;
			margin-bottom: 30px;
			border-radius: 6px;
		}
		.info-box p {
			margin: 8px 0;
			color: #333;
		}
		.info-box strong {
			color: #7367f0;
		}
		.message-section {
			background-color: #fff;
			border: 1px solid #e0e0e0;
			padding: 20px;
			border-radius: 8px;
			margin-bottom: 30px;
		}
		.message-section h3 {
			color: #7367f0;
			font-size: 18px;
			margin-bottom: 15px;
		}
		.response-section {
			margin-bottom: 30px;
		}
		.response-section h3 {
			color: #333;
			font-size: 18px;
			margin-bottom: 15px;
		}
		.form-control {
			width: 100%;
			padding: 12px;
			border: 1px solid #ddd;
			border-radius: 6px;
			font-size: 15px;
			transition: border-color 0.3s;
		}
		.form-control:focus {
			outline: none;
			border-color: #7367f0;
			box-shadow: 0 0 0 3px rgba(115, 103, 240, 0.1);
		}
		.btn-primary {
			background-color: #7367f0;
			color: white;
			padding: 12px 30px;
			border: none;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: background-color 0.3s;
		}
		.btn-primary:hover {
			background-color: #5e50ee;
		}
		.btn-primary:disabled {
			background-color: #ccc;
			cursor: not-allowed;
		}
		.alert {
			padding: 15px 20px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.alert-success {
			background-color: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
		}
		.alert-error {
			background-color: #f8d7da;
			border: 1px solid #f5c6cb;
			color: #721c24;
		}
		.footer-section {
			text-align: center;
			margin-top: 40px;
			padding-top: 20px;
			border-top: 1px solid #e0e0e0;
			color: #666;
			font-size: 14px;
		}
		.response-box {
			background-color: #e8f5e9;
			border: 1px solid #c8e6c9;
			padding: 20px;
			border-radius: 8px;
			margin-top: 20px;
		}
		.response-box h4 {
			color: #2e7d32;
			margin-bottom: 10px;
		}
	</style>
</head>
<body>
	<div class="response-container">
		<div class="response-card">
			<div class="header-section">
				<h1><i class="ti ti-message-circle"></i> Consulta sobre Tarea</h1>
				<p>{{ config('app.name') }}</p>
			</div>

			@if(session('success'))
				<div class="alert alert-success">
					<i class="ti ti-check"></i> {{ session('success') }}
				</div>
			@endif

			@if(session('error'))
				<div class="alert alert-error">
					<i class="ti ti-alert-circle"></i> {{ session('error') }}
				</div>
			@endif

			<div class="info-box">
				<p><strong>Empresa:</strong> {{ $communication->task->project->enterprise->name ?? 'N/A' }}</p>
				<p><strong>Proyecto:</strong> {{ $communication->task->project->name ?? 'N/A' }}</p>
				<p><strong>Tarea:</strong> {{ $communication->task->title }}</p>
				<p><strong>Fecha de consulta:</strong> {{ $communication->created_at->format('d/m/Y H:i') }}</p>
			</div>

			<div class="message-section">
				<h3><i class="ti ti-message"></i> Mensaje de {{ $communication->user->name ?? 'Sistema' }}</h3>
				<p>{{ $communication->message }}</p>
			</div>

			@if($communication->response)
				<!-- Already responded -->
				<div class="response-box">
					<h4><i class="ti ti-check-circle"></i> Tu Respuesta</h4>
					<p>{{ $communication->response }}</p>
					<small class="text-muted">Respondido el {{ $communication->response_at->format('d/m/Y H:i') }}</small>
				</div>
			@else
				<!-- Response form -->
				<div class="response-section">
					<h3><i class="ti ti-pencil"></i> Tu Respuesta</h3>
					<form action="{{ route('task.communication.respond.store', $communication->response_token) }}" method="POST">
						@csrf
						<div class="mb-3">
							<label for="response" class="form-label">Escribe tu respuesta:</label>
							<textarea
								name="response"
								id="response"
								class="form-control"
								rows="6"
								placeholder="Escribe tu respuesta aquí..."
								required
							></textarea>
							@error('response')
								<small class="text-danger">{{ $message }}</small>
							@enderror
						</div>
						<div class="text-center">
							<button type="submit" class="btn btn-primary">
								<i class="ti ti-send"></i> Enviar Respuesta
							</button>
						</div>
					</form>
				</div>
			@endif

			<div class="footer-section">
				<p>© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
			</div>
		</div>
	</div>
</body>
</html>

