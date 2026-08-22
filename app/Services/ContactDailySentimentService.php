<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\Email;
use App\Models\Team;
use Carbon\CarbonInterface;

class ContactDailySentimentService
{
    /** @var array<int, string> */
    private const SENTIMENT_LABELS = [
        1 => 'Muy Negativo',
        2 => 'Negativo',
        3 => 'Neutral',
        4 => 'Positivo',
        5 => 'Muy Positivo',
    ];

    public function __construct(
        private readonly ContactSentimentAnalysisService $sentimentAnalysisService,
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
        private readonly UserResolverService $userResolver,
    ) {}

    /**
     * Analyze inbound WhatsApp/email from the last 24 hours for contacts who wrote in.
     */
    public function processTeam(Team $team, ?CarbonInterface $since = null): int
    {
        if (! $team->hasModule('insights'))
        {
            return 0;
        }

        $since ??= now()->subDay();
        $contextByContact = $this->collectRecentInboundContextByContact($team, $since);
        $processed = 0;

        foreach ($contextByContact as $contactId => $contextText)
        {
            $contact = Contact::withoutGlobalScopes()->find($contactId);

            if (! $contact || (int) $contact->team_id !== (int) $team->id)
            {
                continue;
            }

            $this->sentimentAnalysisService->recordForContact($contact, $contextText, 'daily');
            $processed++;
        }

        return $processed;
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    public function chartDataForTeam(Team $team): array
    {
        $latestSentimentIds = ContactSentimentHistory::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('contact_id', Contact::withoutGlobalScopes()->where('team_id', $team->id)->select('id'))
            ->groupBy('contact_id');

        $sentimentCounts = ContactSentimentHistory::query()
            ->whereIn('id', $latestSentimentIds)
            ->selectRaw('sentiment_id, COUNT(*) as aggregate')
            ->groupBy('sentiment_id')
            ->pluck('aggregate', 'sentiment_id');

        $sentimentData = [];

        foreach (self::SENTIMENT_LABELS as $id => $label)
        {
            $sentimentData[] = [
                'label' => $label,
                'count' => (int) ($sentimentCounts[$id] ?? 0),
            ];
        }

        return $sentimentData;
    }

    /**
     * @return array<int, string>
     */
    private function collectRecentInboundContextByContact(Team $team, CarbonInterface $since): array
    {
        /** @var array<int, list<array{at: string, channel: string, text: string}>> $entriesByContact */
        $entriesByContact = [];

        if ($this->teamUsesWhatsApp($team))
        {
            $whatsappMessages = $this->digestCollector
                ->whatsappConversationQueryForTeam($team)
                ->where('direction', 'inbound')
                ->where('created_at', '>=', $since)
                ->whereNotNull('body')
                ->where('body', '!=', '')
                ->orderBy('created_at')
                ->get(['from', 'body', 'created_at']);

            foreach ($whatsappMessages as $message)
            {
                $phoneDigits = preg_replace('/[^0-9]/', '', (string) $message->from);
                if ($phoneDigits === '')
                {
                    continue;
                }

                $contact = $this->userResolver->findContactInTeamByPhone($team->id, $phoneDigits);
                if (! $contact)
                {
                    continue;
                }

                $entriesByContact[$contact->id][] = [
                    'at' => $message->created_at?->toDateTimeString() ?? '',
                    'channel' => 'WhatsApp',
                    'text' => trim((string) $message->body),
                ];
            }
        }

        if ($team->hasModule('mailbox'))
        {
            $emails = Email::query()
                ->where('team_id', $team->id)
                ->where('created_at', '>=', $since)
                ->whereNotNull('from_address')
                ->orderBy('created_at')
                ->get(['from_address', 'subject', 'body_text', 'body_html', 'created_at']);

            foreach ($emails as $email)
            {
                $contact = $this->findContactByEmailAddress($team->id, (string) $email->from_address);
                if (! $contact)
                {
                    continue;
                }

                $body = trim((string) ($email->body_text ?: strip_tags((string) $email->body_html)));
                if ($body === '')
                {
                    continue;
                }

                $subject = trim((string) ($email->subject ?? ''));
                $text = $subject !== ''
                    ? "Asunto: {$subject}\n{$body}"
                    : $body;

                $entriesByContact[$contact->id][] = [
                    'at' => $email->created_at?->toDateTimeString() ?? '',
                    'channel' => 'Email',
                    'text' => $text,
                ];
            }
        }

        $contextByContact = [];

        foreach ($entriesByContact as $contactId => $entries)
        {
            usort($entries, fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

            $parts = [];

            foreach ($entries as $entry)
            {
                $parts[] = sprintf('[%s %s]'."\n".'%s', $entry['channel'], $entry['at'], $entry['text']);
            }

            $contextByContact[$contactId] = implode("\n\n", $parts);
        }

        return $contextByContact;
    }

    private function findContactByEmailAddress(int $teamId, string $fromAddress): ?Contact
    {
        $address = $this->normalizeEmailAddress($fromAddress);
        if ($address === null)
        {
            return null;
        }

        return Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($address))])
            ->first();
    }

    private function normalizeEmailAddress(string $from): ?string
    {
        $from = trim($from);
        if ($from === '')
        {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $from, $matches))
        {
            return trim($matches[1]);
        }

        return $from;
    }

    /**
     * WhatsApp inbound is stored even when the Chat sidebar module is off
     * (connected number + contact chat actions). Gate on the number, not the module.
     */
    private function teamUsesWhatsApp(Team $team): bool
    {
        if ($team->hasModule('chat'))
        {
            return true;
        }

        $number = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());

        return $number !== '';
    }
}
