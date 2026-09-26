<?php

namespace App\Livewire;

use App\Models\Ticket;
use Livewire\Component;

class TicketNavbarIcon extends Component
{
    public int $openCount = 0;

    public function mount(): void
    {
        $this->refreshOpenCount();
    }

    public function refreshOpenCount(): void
    {
        $team = auth()->user()?->currentTeam;

        if ($team === null || ! $team->hasModule('tickets'))
        {
            $this->openCount = 0;

            return;
        }

        $this->openCount = Ticket::query()
            ->where('status', '!=', 'closed')
            ->count();
    }

    public function render()
    {
        return view('livewire.ticket-navbar-icon');
    }
}
