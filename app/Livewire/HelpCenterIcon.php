<?php

namespace App\Livewire;

use App\Models\Conversation;
use Livewire\Component;

class HelpCenterIcon extends Component
{
    public $inboundCount = 0;

    public function mount()
    {
        $this->inboundCount = Conversation::where('direction', 'inbound')
            ->where('status', '!=', 'received')
            ->count();
    }

    public function render()
    {
        $this->inboundCount = Conversation::where('direction', 'inbound')
            ->where('status', '!=', 'received')
            ->count();

        return view('livewire.help-center-icon');
    }
}
