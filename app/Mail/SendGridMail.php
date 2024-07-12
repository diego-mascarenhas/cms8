<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use SendGrid\Mail\Mail as SendGridMail;

class MySendGridMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $email = new SendGridMail();
        $email->setFrom(config('mail.from.address'), config('mail.from.name'));
        $email->addTo($this->data['to']);
        $email->setTemplateId('tu_template_id');

        foreach ($this->data['dynamic_template_data'] as $key => $value) {
            $email->addDynamicTemplateData($key, $value);
        }

        return $this->view('emails.sendgrid'); // Required to comply with the Mailable interface, although not used
    }
}
