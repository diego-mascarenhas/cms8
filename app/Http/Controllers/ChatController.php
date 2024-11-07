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
		try {
			// Log para debug
			Log::info('Correo recibido:', [
				'asunto' => $email->subject(),
				'de' => $email->from(),
				'contenido' => $email->text()
			]);

			// Guardar el email en la tabla mailbox_inbound_emails
			$inboundEmail = InboundEmail::fromMessage($email->message);
			$inboundEmail->save();

			Log::info('Email guardado correctamente en la base de datos con ID: ' . $inboundEmail->id);

			return true;
		} catch (\Exception $e) {
			Log::error('Error al procesar el email: ' . $e->getMessage());
			return false;
		}
	}
}
