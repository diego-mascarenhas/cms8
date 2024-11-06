<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Source;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
	public function index()
	{
		$sources = Source::all();
		
		return view('chat.index', compact('sources'));
	}

	public function handleIncomingEmail(InboundEmail $email)
	{
		// Opción 1: Usar Log para registrar en storage/logs/laravel.log
		Log::info('Correo recibido:', [
			'asunto' => $email->subject(),
			'de' => $email->from(),
			'contenido' => $email->text()
		]);

		// Opción 2: Escribir en un archivo específico
		// $log = "Fecha: " . now() . "\n";
		// $log .= "Asunto: " . $email->subject() . "\n";
		// $log .= "De: " . $email->from() . "\n";
		// $log .= "Contenido: " . $email->text() . "\n";
		// $log .= "------------------------\n";
		
		// file_put_contents(
		// 	storage_path('logs/emails.log'),
		// 	$log,
		// 	FILE_APPEND
		// );

		// // Opción 3: Guardar en base de datos
		// \App\Models\EmailLog::create([
		// 	'subject' => $email->subject(),
		// 	'from' => $email->from(),
		// 	'content' => $email->text(),
		// 	'received_at' => now()
		// ]);

		// // Opción 4: Enviar una notificación
		// \Illuminate\Support\Facades\Notification::route('mail', 'tu@email.com')
		// 	->notify(new \App\Notifications\NewEmailReceived($email));

		return true; // Indica que el email fue procesado correctamente
	}
}
