<?php

namespace App\Livewire;

use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class HelpCenterIcon extends Component
{
    public function render()
    {
        $team = auth()->user()->currentTeam ?? null;
        $teamNumber = $team ? preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom()) : '';
        $cacheKey = 'inbound_received_count_team_'.($team ? $team->id : 0);

        $inboundCount = Cache::remember(
            $cacheKey,
            15,
            function () use ($teamNumber)
            {
                if ($teamNumber === '')
                {
                    return 0;
                }

                return Conversation::where('channel', 'whatsapp')
                    ->where('direction', 'inbound')
                    ->where('status', 'received')
                    ->where(function ($q) use ($teamNumber)
                    {
                        $q->where('from', $teamNumber)
                            ->orWhere('to', $teamNumber)
                            ->orWhere('from', 'like', $teamNumber.':%')
                            ->orWhere('to', 'like', $teamNumber.':%');
                    })
                    ->count();
            },
        );

        return view('livewire.help-center-icon', ['inboundCount' => $inboundCount]);
    }
}
