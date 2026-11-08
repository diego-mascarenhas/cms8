<?php

namespace App\Services\WhatsApp;

use App\Models\Conversation;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;

class WhatsAppInboundUnreadCount
{
    public function forTeam(?Team $team): int
    {
        $teamId = $team ? (int) $team->id : 0;
        $teamNumber = $team ? preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom()) : '';

        return (int) Cache::remember(
            'inbound_received_count_team_'.$teamId,
            15,
            function () use ($team, $teamId, $teamNumber): int
            {
                if ($teamNumber === '' || $team === null)
                {
                    return 0;
                }

                $archivedPhones = app(WhatsAppChatArchiveService::class)->archivedPhoneSet($teamId);

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
    }
}
