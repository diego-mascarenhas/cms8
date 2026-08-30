<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\SiteAssistantMessage;
use App\Models\Team;

class InboxReplyTargetService
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MOBILE = 'mobile';

    /**
     * @return array{channel: string, session_key: string|null, phone: string|null}
     */
    public function forWebSession(string $sessionKey): array
    {
        return $this->present(self::CHANNEL_WEB, trim($sessionKey) !== '' ? trim($sessionKey) : null, null);
    }

    /**
     * Reply on the channel the contact last wrote on. Add inbound sources here as channels grow.
     *
     * @return array{channel: string, session_key: string|null, phone: string|null}
     */
    public function forWhatsAppThread(Team $team, string $phone, ?Contact $contact): array
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?: '';
        $fallback = $this->present(self::CHANNEL_WHATSAPP, $this->latestWebSession($team, $contact), $digits !== '' ? $digits : null);
        $latest = $this->latestInbound($this->inboundCandidates($team, $contact, $digits));

        return $latest ?? $fallback;
    }

    /**
     * @return list<array{channel: string, session_key: string|null, phone: string|null, at: int, id: int}>
     */
    private function inboundCandidates(Team $team, ?Contact $contact, string $phone): array
    {
        return array_values(array_filter([
            $this->whatsAppInbound($team, $phone),
            $this->webInbound($team, $contact),
        ]));
    }

    /**
     * @param  list<array{channel: string, session_key: string|null, phone: string|null, at: int, id: int}>  $candidates
     * @return array{channel: string, session_key: string|null, phone: string|null}|null
     */
    private function latestInbound(array $candidates): ?array
    {
        if ($candidates === [])
        {
            return null;
        }

        usort($candidates, function (array $left, array $right): int
        {
            return $right['at'] <=> $left['at'];
        });

        $winner = $candidates[0];

        return $this->present($winner['channel'], $winner['session_key'], $winner['phone']);
    }

    /**
     * @return array{channel: string, session_key: string|null, phone: string|null, at: int, id: int}|null
     */
    private function whatsAppInbound(Team $team, string $phone): ?array
    {
        if ($phone === '')
        {
            return null;
        }

        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom()) ?: '';
        $message = Conversation::query()
            ->where('channel', self::CHANNEL_WHATSAPP)
            ->where('direction', 'inbound')
            ->where(function ($query) use ($phone)
            {
                $query->where('from', $phone)->orWhere('from', 'like', $phone.':%');
            })
            ->when($teamNumber !== '', function ($query) use ($teamNumber)
            {
                $query->where(function ($scoped) use ($teamNumber)
                {
                    $scoped->where('from', $teamNumber)
                        ->orWhere('to', $teamNumber)
                        ->orWhere('from', 'like', $teamNumber.':%')
                        ->orWhere('to', 'like', $teamNumber.':%');
                });
            })
            ->orderByDesc('id')
            ->first();

        if (! $message)
        {
            return null;
        }

        return [
            'channel' => self::CHANNEL_WHATSAPP,
            'session_key' => null,
            'phone' => $phone,
            'at' => $message->created_at?->getTimestamp() ?? 0,
            'id' => (int) $message->id,
        ];
    }

    /**
     * @return array{channel: string, session_key: string|null, phone: string|null, at: int, id: int}|null
     */
    private function webInbound(Team $team, ?Contact $contact): ?array
    {
        if (! $contact)
        {
            return null;
        }

        $message = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('contact_id', $contact->id)
            ->where('role', SiteAssistantMessage::ROLE_VISITOR)
            ->orderByDesc('id')
            ->first();

        if (! $message)
        {
            return null;
        }

        return [
            'channel' => $message->originChannel(),
            'session_key' => trim((string) $message->session_key) !== '' ? (string) $message->session_key : null,
            'phone' => null,
            'at' => $message->created_at?->getTimestamp() ?? 0,
            'id' => (int) $message->id,
        ];
    }

    private function latestWebSession(Team $team, ?Contact $contact): ?string
    {
        if (! $contact)
        {
            return null;
        }

        $sessionKey = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('contact_id', $contact->id)
            ->where('role', SiteAssistantMessage::ROLE_VISITOR)
            ->orderByDesc('id')
            ->value('session_key');

        $sessionKey = trim((string) $sessionKey);

        return $sessionKey !== '' ? $sessionKey : null;
    }

    /**
     * @return array{channel: string, session_key: string|null, phone: string|null}
     */
    private function present(string $channel, ?string $sessionKey, ?string $phone): array
    {
        return [
            'channel' => $channel,
            'session_key' => $sessionKey,
            'phone' => $phone,
        ];
    }
}
