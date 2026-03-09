<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProspectResultsAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;

    public string $accessUrl;

    public function __construct(string $code, string $accessUrl = '')
    {
        $this->code = $code;
        $this->accessUrl = $accessUrl;
    }

    public function build()
    {
        return $this->subject(__('Tu código para ver los resultados de prospectos'))
            ->view('emails.prospect-results-access');
    }
}
