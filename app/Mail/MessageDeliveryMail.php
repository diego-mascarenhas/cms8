<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\MessageDelivery;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class MessageDeliveryMail extends Mailable implements ShouldQueue
{
	use Queueable, SerializesModels;

	public $delivery;

	public function __construct(MessageDelivery $delivery)
	{
		$this->delivery = $delivery;
	}

	public function build()
	{
		$subject = $this->delivery->message ? $this->delivery->message->name : 'Newsletter';
		$html = $this->delivery->getHtmlForContact();

		// Add advertising footer if team is using system SMTP
		$advertisingFooter = config('app.mail_advertising_footer', '');
		if ($advertisingFooter) {
			$html .= $advertisingFooter;
		}

		$css = '';
		$inliner = new CssToInlineStyles();
		$htmlInlined = $inliner->convert($html, $css);
		return $this->subject($subject)
			->html($htmlInlined);
	}
}
