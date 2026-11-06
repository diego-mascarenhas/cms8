@extends('emails.layouts.humano')

@section('title', 'Solicitud de cambio - ' . $task->title)

@section('content')
	<h1>El cliente solicitó un cambio</h1>

	<p>{{ $senderName }} pidió un cambio en una tarea del proyecto.</p>

	<div style="background:#f8fafc;border-radius:8px;padding:14px 16px;margin:16px 0;">
		<p style="margin:4px 0;"><strong style="color:#2563eb;">Tarea:</strong> {{ $task->title }}</p>
		@if($task->project)
			<p style="margin:4px 0;"><strong style="color:#2563eb;">Proyecto:</strong> {{ $task->project->name }}</p>
		@endif
	</div>

	<div style="border-left:4px solid #2563eb;background:#ffffff;padding:12px 16px;margin:16px 0;">
		<strong>Comentario:</strong>
		<p style="margin:8px 0 0;">{{ $body }}</p>
	</div>

	<p>La conversación también está en Mis tareas.</p>

	<div class="btn-wrap">
		<a href="{{ $taskUrl }}" class="btn">Ver tarea</a>
	</div>

	<p class="muted" style="margin-top:20px;">
		Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
		<a href="{{ $taskUrl }}" style="color:#2563eb;">{{ $taskUrl }}</a>
	</p>
@endsection
