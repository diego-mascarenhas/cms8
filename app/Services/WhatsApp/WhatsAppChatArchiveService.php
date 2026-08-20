<?php

namespace App\Services\WhatsApp;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\WhatsAppChatArchive;

class WhatsAppChatArchiveService
{
    public function archive(int $teamId, string $phone): void
    {
        $digits = WhatsAppInboxContactStarter::normalizeInboxPhone($phone);
        if ($digits === '')
        {
            return;
        }

        WhatsAppChatArchive::query()->updateOrCreate(
            ['team_id' => $teamId, 'phone' => $digits],
            ['archived_at' => now()],
        );
    }

    public function unarchive(int $teamId, string $phone): void
    {
        $digits = WhatsAppInboxContactStarter::normalizeInboxPhone($phone);
        if ($digits === '')
        {
            return;
        }

        WhatsAppChatArchive::query()
            ->where('team_id', $teamId)
            ->where('phone', $digits)
            ->delete();
    }

    public function isArchived(int $teamId, string $phone): bool
    {
        $digits = WhatsAppInboxContactStarter::normalizeInboxPhone($phone);
        if ($digits === '')
        {
            return false;
        }

        return WhatsAppChatArchive::query()
            ->where('team_id', $teamId)
            ->where('phone', $digits)
            ->exists();
    }

    /**
     * @return array<string, true>
     */
    public function archivedPhoneSet(int $teamId): array
    {
        $phones = WhatsAppChatArchive::query()
            ->where('team_id', $teamId)
            ->pluck('phone');

        $set = [];
        foreach ($phones as $phone)
        {
            $digits = WhatsAppInboxContactStarter::normalizeInboxPhone((string) $phone);
            if ($digits !== '')
            {
                $set[$digits] = true;
            }
        }

        return $set;
    }

    public function unarchiveIncoming(Conversation $conversation): void
    {
        if ($conversation->channel !== 'whatsapp' || $conversation->direction !== 'inbound')
        {
            return;
        }

        $to = preg_replace('/[^0-9]/', '', (string) $conversation->to);
        $from = WhatsAppInboxContactStarter::normalizeInboxPhone((string) $conversation->from);
        if ($to === '' || $from === '')
        {
            return;
        }

        $team = Team::findByWhatsAppNumber($to);
        if ($team === null)
        {
            return;
        }

        $this->unarchive((int) $team->id, $from);
    }
}
