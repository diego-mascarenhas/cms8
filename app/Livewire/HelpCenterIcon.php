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
                $query = Conversation::where('channel', 'whatsapp')
                    ->where('direction', 'inbound')
                    ->where('status', 'received');
                if ($teamNumber !== '')
                {
                    $query->where(function ($q) use ($teamNumber)
                    {
                        $q->where('from', $teamNumber)
                            ->orWhere('to', $teamNumber)
                            ->orWhere('from', 'like', $teamNumber.':%')
                            ->orWhere('to', 'like', $teamNumber.':%');
                    });
                }

                return $query->count();
            },
        );

        return view('livewire.help-center-icon', ['inboundCount' => $inboundCount]);
    }
}
