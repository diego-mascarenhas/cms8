<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\WhatsApp\WhatsAppChatArchiveService;
use App\Services\WhatsApp\WhatsAppInboxContactStarter;
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
            function () use ($team, $teamNumber)
            {
                if ($teamNumber === '' || $team === null)
                {
                    return 0;
                }

                $archivedPhones = app(WhatsAppChatArchiveService::class)->archivedPhoneSet((int) $team->id);

                $query = Conversation::query()
                    ->where('channel', 'whatsapp')
                    ->where('direction', 'inbound')
                    ->where('status', 'received')
                    ->where(function ($q) use ($teamNumber)
                    {
                        $q->where('from', $teamNumber)
                            ->orWhere('to', $teamNumber)
                            ->orWhere('from', 'like', $teamNumber.':%')
                            ->orWhere('to', 'like', $teamNumber.':%');
                    });

                if ($archivedPhones === [])
                {
                    return (int) $query->count();
                }

                return $query->get(['from'])
                    ->filter(function ($row) use ($archivedPhones): bool
                    {
                        $digits = WhatsAppInboxContactStarter::normalizeInboxPhone((string) $row->from);

                        return $digits !== '' && ! isset($archivedPhones[$digits]);
                    })
                    ->count();
            },
        );

        return view('livewire.help-center-icon', ['inboundCount' => $inboundCount]);
    }
}
