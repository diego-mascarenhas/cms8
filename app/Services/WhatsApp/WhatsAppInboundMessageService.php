<?php

namespace App\Services\WhatsApp;

use App\Models\Team;
use App\Services\TwilioService;

class WhatsAppInboundMessageService extends TwilioService
{
    public function __construct(?Team $team = null)
    {
        parent::__construct($team);
    }
}
