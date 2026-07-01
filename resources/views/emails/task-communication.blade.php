@extends('emails.layouts.humano')

@section('title', 'Consulta sobre tarea - ' . ($task->title ?? ''))

@section('content')
	<h1>Consulta sobre tarea</h1>

	<p>Estimado/a <strong>{{ $enterprise->name }}</strong>,</p>

	<p>Hemos recibido una consulta relacionada con una tarea de su proyecto:</p>

	<div style="background:#f8fafc;border-radius:8px;padding:14px 16px;margin:16px 0;">
		<p style="margin:4px 0;"><strong style="color:#2563eb;">Proyecto:</strong> {{ $task->project->name ?? 'N/A' }}</p>
		<p style="margin:4px 0;"><strong style="color:#2563eb;">Tarea:</strong> {{ $task->title }}</p>
		@if($task->description)
			<p style="margin:4px 0;"><strong style="color:#2563eb;">Descripción:</strong> {{ $task->description }}</p>
		@endif
	</div>

	<div style="border-left:4px solid #2563eb;background:#ffffff;padding:12px 16px;margin:16px 0;">
		<strong>Mensaje:</strong>
		<p style="margin:8px 0 0;">{{ $body }}</p>
	</div>

	<p>Puede acceder a la landing del proyecto (ver el mensaje, las tareas y enviar su respuesta) mediante el siguiente enlace seguro con token. Solo tiene que hacer clic:</p>

	<div class="btn-wrap">
		<a href="{{ $responseUrl }}" class="btn">Acceder a la landing del proyecto</a>
	</div>

	<p class="muted" style="margin-top:20px;">
		Si el botón no funciona, copie y pegue este enlace en su navegador (acceso con token):<br>
		<a href="{{ $responseUrl }}" style="color:#2563eb;">{{ $responseUrl }}</a>
	</p>

	<p class="muted">Este es un mensaje automático, por favor no responda a este email.</p>
@endsection
