<?php

namespace App\Mail;

use App\Models\SLA;
use App\Models\SubscriptionProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SLAAcceptanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sla;
    public $product;
    public $acceptanceUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(SLA $sla, SubscriptionProduct $product, string $acceptanceUrl)
    {
        $this->sla = $sla;
        $this->product = $product;
        $this->acceptanceUrl = $acceptanceUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Aceptación de SLA - '.$this->product->name)
            ->view('emails.sla-acceptance')
            ->with([
                'sla' => $this->sla,
                'product' => $this->product,
                'acceptanceUrl' => $this->acceptanceUrl,
            ]);
    }
}
