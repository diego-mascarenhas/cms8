<?php

namespace App\Mail;

use App\Models\MessageDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrackedTestMail extends Mailable
{
	use Queueable, SerializesModels;

	public $delivery;

	public function __construct(MessageDelivery $delivery)
	{
		$this->delivery = $delivery;
	}

	public function build()
	{
		$trackingLink = $this->delivery->getTrackingUrl();
		$html = <<<HTML
<html>
<body>
	<p>Hola, este es un mensaje de prueba con tracking de apertura.</p>
	<p>¡Gracias por participar!</p>
	<p><a href="$trackingLink" target="_blank">Probar tracking de apertura</a></p>
	<img src="$trackingLink" width="1" height="1" style="display:none;" alt="" />
</body>
</html>
HTML;

		return $this->subject('Prueba de Tracking de Apertura')
			->html($html);
	}
}
