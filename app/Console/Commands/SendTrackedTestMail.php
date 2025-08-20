<?php

namespace App\Console\Commands;

use App\Mail\TrackedTestMail;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTrackedTestMail extends Command
{
	protected $signature = 'mail:send-tracked-test {delivery_id}';

	protected $description = 'Enviar un correo de prueba con tracking de apertura usando MessageDelivery.';

	public function handle()
	{
		$deliveryId = $this->argument('delivery_id');
		$delivery = MessageDelivery::find($deliveryId);
		if (! $delivery)
		{
			$this->error('No se encontró el MessageDelivery con ID: '.$deliveryId);

			return 1;
		}
		if (! $delivery->contact || ! $delivery->contact->email)
		{
			$this->error('El delivery no tiene contacto o email asociado.');

			return 1;
		}
		Mail::to($delivery->contact->email)->send(new TrackedTestMail($delivery));
		$delivery->markAsSent();
		$this->info('Correo de prueba enviado a: '.$delivery->contact->email);

		return 0;
	}
}
