<?php

namespace App\Observers;

use App\Models\TicketResponse;

class TicketResponseObserver
{
    public function created(TicketResponse $ticketResponse): void
    {
        $ticketResponse->ticket->updateLastResponse();
    }
}
