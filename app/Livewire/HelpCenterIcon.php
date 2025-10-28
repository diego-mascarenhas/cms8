<?php

namespace App\Livewire;

use App\Models\Conversation;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;

class HelpCenterIcon extends Component
{
    public $inboundCount = 0;

    public function mount()
    {
        $teamKey = auth()->user()->currentTeam->id ?? 'global';
        $this->inboundCount = Cache::remember("inbound_received_count_{$teamKey}", 60, function ()
        {
            return Conversation::where('direction', 'inbound')
                ->where('status', 'received')
                ->count();
        });
    }

    public function render()
    {
        return view('livewire.help-center-icon');
    }
}
