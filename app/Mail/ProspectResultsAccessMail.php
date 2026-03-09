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

    public ?string $fromAddress = null;

    public ?string $fromName = null;

    public function __construct(string $code, string $accessUrl = '', ?string $fromAddress = null, ?string $fromName = null)
    {
        $this->code = $code;
        $this->accessUrl = $accessUrl;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    public function build()
    {
        $mailable = $this->subject(__('Tu código para ver los resultados de prospectos'))
            ->view('emails.prospect-results-access');

        if ($this->fromAddress !== null && $this->fromAddress !== '')
        {
            $mailable->from($this->fromAddress, $this->fromName ?? '');
        }

        return $mailable;
    }
}
