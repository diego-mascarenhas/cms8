<?php

namespace App\Exceptions;

class WhatsAppSessionWindowClosedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('WhatsApp customer service window is closed.');
    }
}
