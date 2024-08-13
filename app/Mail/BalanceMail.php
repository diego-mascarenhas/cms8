<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BalanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $balances;

    public function __construct($balances)
    {
        $this->balances = $balances;
    }

    public function build()
    {
        return $this->view('emails.balance')
                    ->with(['balances' => $this->balances]);
    }
}
