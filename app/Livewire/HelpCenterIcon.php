<?php

namespace App\Livewire;

use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class HelpCenterIcon extends Component
{
    public function render()
    {
        $inboundCount = Cache::remember(
            Conversation::CACHE_KEY_INBOUND_UNREAD,
            30,
            fn () => Conversation::where('channel', 'whatsapp')
                ->where('direction', 'inbound')
                ->where('status', 'received')
                ->count(),
        );

        return view('livewire.help-center-icon', ['inboundCount' => $inboundCount]);
    }
}
