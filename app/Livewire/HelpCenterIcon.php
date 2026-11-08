<?php

namespace App\Livewire;

use App\Services\WhatsApp\WhatsAppInboundUnreadCount;
use Livewire\Component;

class HelpCenterIcon extends Component
{
    public function render()
    {
        $inboundCount = app(WhatsAppInboundUnreadCount::class)->forTeam(auth()->user()->currentTeam ?? null);

        return view('livewire.help-center-icon', ['inboundCount' => $inboundCount]);
    }
}
