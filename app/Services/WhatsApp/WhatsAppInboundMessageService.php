<?php

namespace App\Services\WhatsApp;

use App\Models\Team;

class WhatsAppInboundMessageService extends WhatsAppMessageService
{
    public function __construct(?Team $team = null)
    {
        parent::__construct($team);
    }
}
