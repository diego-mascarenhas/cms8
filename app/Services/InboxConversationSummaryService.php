<?php

namespace App\Services;

use App\Enums\ContactInteractionType;
use App\Models\Contact;
use App\Models\ContactIntent;
use App\Models\ContactInteraction;
use App\Models\Conversation;
use App\Models\MessageDelivery;
use App\Models\Team;
use App\Support\AiTasks;
use InvalidArgumentException;

use function Laravel\Ai\agent;

class InboxConversationSummaryService
{
    private const MAX_MESSAGES = 30;

    private const MAX_BODY_CHARS = 500;

    private const INTENT_KEYS = ['buy', 'update', 'work', 'cancel', 'other', 'unclear'];

    private const INSTRUCTIONS = <<<'PROMPT'
You write a CRM follow-up digest from the contact's conversations across channels (WhatsApp, email, calls, meetings and notes).

The thread is a compact TOON table under messages (columns: who, at, text). Read it in chronological order.

Write summary in the message language, in short lines separated by \n. Cover the whole thread: what they asked, where it stands, and what to do next. No greeting. Do not stop mid-sentence.

Commercial intent (intent_key), exactly one:
buy = wants to purchase a product or service
update = wants to change existing data, an order, a plan, or details
work = wants a job, repair, project, or service performed
cancel = wants to unsubscribe, cancel, or leave
other = a clear request that is none of the above
unclear = greeting, noise, or no actionable intent

Use unclear only when there is no actionable request. Every other key is a clear intention.

Also draft the next team reply:
channel = the best medium, exactly one of call, email, meeting, note, whatsapp, other. Prefer the channel the contact already uses.
subject = a short subject in the message language, max 80 characters. Use an empty string for WhatsApp or a call.
reply = the message or talking points to send, in the message language, max 500 characters, ready to send.

Respond with ONLY a valid JSON object, no markdown. Example:
{"summary":"Pidió precio del plan.\\nPreguntó cuántos usuarios caben.\\nResponder hoy con cifras.","intent_key":"buy","channel":"whatsapp","subject":"","reply":"El plan incluye hasta 5 usuarios. Te paso el precio hoy."}
PROMPT;

    public function __construct(
        private readonly ContactSentimentAnalysisService $sentimentAnalysisService,
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
    ) {}

    /**
     * @return array{summary: string, intent: array{id: int|null, key: string, name: string, emoji: string}|null, suggestion: array{channel: string, subject: string, body: string, clear: bool}}
     */
    public function summarize(Team $team, Contact $contact): array
    {
        $rows = $this->threadRows($team, $contact);
        if ($rows === [])
        {
            throw new InvalidArgumentException(__('No hay mensajes para resumir.'));
        }

        $encoded = ToonPayloadService::encode(['messages' => $rows]);
        $parsed = $this->summarizeWithAi($encoded['text'], (int) $team->id, $encoded);
        $summary = $parsed['summary'] !== ''
            ? $parsed['summary']
            : $this->fallbackSummary($rows);

        if ($summary === '')
        {
            throw new InvalidArgumentException(__('No se pudo resumir la conversación.'));
        }

        $intentKey = $parsed['intent_key'];
        $channel = $this->normalizeChannel($parsed['channel'], $rows);
        $this->sentimentAnalysisService->storeInboxDigest($contact, $summary, $intentKey, false, [
            'channel' => $channel,
        ]);

        $reply = $parsed['reply'] !== '' ? $parsed['reply'] : $this->clampLine($this->fallbackSummary($rows), 500);

        return [
            'summary' => $summary,
            'intent' => $this->presentIntent($intentKey),
            'suggestion' => [
                'channel' => $channel,
                'subject' => $parsed['subject'],
                'body' => $reply,
                'clear' => $intentKey !== null && $intentKey !== 'unclear',
            ],
        ];
    }

    /**
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, savings_percentage?: float|int, tokens_saved?: int}  $toon
     * @return array{summary: string, intent_key: string|null, channel: string|null, subject: string, reply: string}
     */
    private function summarizeWithAi(string $thread, int $teamId, array $toon): array
    {
        try
        {
            $agent = agent(
                instructions: self::INSTRUCTIONS,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($thread, [], AiTasks::provider('summary'), AiTasks::model('summary'));

            TokenUsageLogService::logFromAiResponse(
                teamId: $teamId,
                service: 'InboxConversationSummaryService',
                usage: $response->usage ?? null,
                moduleKey: 'assistant',
                inputSize: (int) ($toon['json_size'] ?? strlen($thread)),
                toon: $toon,
            );

            $raw = trim((string) ($response->text ?? ''));
            $raw = preg_replace('/^```\w*\s*|\s*```$/u', '', $raw) ?? $raw;
            $data = json_decode($raw, true);

            if (is_array($data))
            {
                $summary = isset($data['summary']) && is_string($data['summary'])
                    ? trim($this->unescapeLines($data['summary']))
                    : '';
                $intentKey = $this->normalizeIntentKey($data['intent_key'] ?? null);

                return [
                    'summary' => $summary,
                    'intent_key' => $intentKey,
                    'channel' => is_string($data['channel'] ?? null) ? $data['channel'] : null,
                    'subject' => $this->clampLine(is_string($data['subject'] ?? null) ? $data['subject'] : '', 80),
                    'reply' => $this->clampLine($this->unescapeLines(is_string($data['reply'] ?? null) ? $data['reply'] : ''), 500),
                ];
            }

            return [
                'summary' => trim($this->unescapeLines($raw)),
                'intent_key' => null,
                'channel' => null,
                'subject' => '',
                'reply' => '',
            ];
        } catch (\Throwable)
        {
            return [
                'summary' => '',
                'intent_key' => null,
                'channel' => null,
                'subject' => '',
                'reply' => '',
            ];
        }
    }

    /**
     * @return list<array{who: string, at: string, text: string}>
     */
    private function threadRows(Team $team, Contact $contact): array
    {
        $rows = array_merge(
            $this->whatsappRows($team, $contact),
            $this->interactionRows($contact),
            $this->emailRows($team, $contact),
        );

        usort($rows, function (array $left, array $right): int
        {
            return strcmp((string) $left['at'], (string) $right['at']);
        });

        if (count($rows) > self::MAX_MESSAGES)
        {
            $rows = array_slice($rows, -self::MAX_MESSAGES);
        }

        return array_values($rows);
    }

    /**
     * @return list<array{who: string, at: string, text: string}>
     */
    private function whatsappRows(Team $team, Contact $contact): array
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($contact->phone ?? ''));
        if ($phone === '')
        {
            return [];
        }

        $messages = $this->digestCollector
            ->whatsappConversationQueryForTeam($team)
            ->where(function ($query) use ($phone): void
            {
                $query->where('from', $phone)
                    ->orWhere('to', $phone)
                    ->orWhere('from', 'like', $phone.':%')
                    ->orWhere('to', 'like', $phone.':%');
            })
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderByDesc('created_at')
            ->limit(self::MAX_MESSAGES)
            ->get(['direction', 'body', 'created_at']);

        if ($messages->isEmpty())
        {
            return [];
        }

        $rows = [];
        foreach ($messages->reverse() as $message)
        {
            if (! $message instanceof Conversation)
            {
                continue;
            }

            $body = trim(preg_replace('/\s+/u', ' ', (string) $message->body) ?? '');
            if ($body === '')
            {
                continue;
            }

            if (mb_strlen($body) > self::MAX_BODY_CHARS)
            {
                $body = rtrim(mb_substr($body, 0, self::MAX_BODY_CHARS - 1)).'…';
            }

            $rows[] = [
                'who' => $message->direction === 'outbound' ? 'Equipo' : 'Cliente',
                'at' => $message->created_at?->toDateTimeString() ?? '',
                'text' => $body,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{who: string, at: string, text: string}>
     */
    private function interactionRows(Contact $contact): array
    {
        $rows = [];
        $interactions = ContactInteraction::query()
            ->where('contact_id', $contact->id)
            ->orderByDesc('occurred_at')
            ->limit(self::MAX_MESSAGES)
            ->get();

        foreach ($interactions as $interaction)
        {
            if (! $interaction instanceof ContactInteraction)
            {
                continue;
            }

            $type = $interaction->type instanceof ContactInteractionType
                ? $interaction->type->label()
                : '';
            $text = trim($type.': '.trim((string) $interaction->subject.' '.(string) $interaction->body));
            if ($text === '' || $text === ':')
            {
                continue;
            }

            if (mb_strlen($text) > self::MAX_BODY_CHARS)
            {
                $text = rtrim(mb_substr($text, 0, self::MAX_BODY_CHARS - 1)).'…';
            }

            $rows[] = [
                'who' => 'Equipo',
                'at' => $interaction->occurred_at?->toDateTimeString() ?? '',
                'text' => $text,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{who: string, at: string, text: string}>
     */
    private function emailRows(Team $team, Contact $contact): array
    {
        $rows = [];
        $deliveries = MessageDelivery::query()
            ->where('team_id', $team->id)
            ->where('contact_id', $contact->id)
            ->whereNotNull('sent_at')
            ->with('message:id,name')
            ->orderByDesc('sent_at')
            ->limit(10)
            ->get();

        foreach ($deliveries as $delivery)
        {
            if (! $delivery instanceof MessageDelivery)
            {
                continue;
            }

            $name = trim((string) ($delivery->message?->name ?? ''));
            $state = $delivery->clicked_at ? 'hizo clic' : ($delivery->opened_at ? 'lo abrió' : 'no lo abrió');
            $text = $name !== '' ? 'Correo «'.$name.'». '.$state.'.' : 'Correo enviado. '.$state.'.';

            $rows[] = [
                'who' => 'Equipo',
                'at' => $delivery->sent_at?->toDateTimeString() ?? '',
                'text' => $text,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{who: string, at: string, text: string}>  $rows
     * @return list<string>
     */
    private function latestLines(array $rows, bool $clientOnly): array
    {
        $lines = [];
        foreach (array_reverse($rows) as $row)
        {
            if ($clientOnly && ($row['who'] ?? '') === 'Equipo')
            {
                continue;
            }

            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '')
            {
                continue;
            }
            array_unshift($lines, $text);
            if (count($lines) >= 3)
            {
                break;
            }
        }

        return $lines;
    }

    /**
     * @param  list<array{who: string, at: string, text: string}>  $rows
     */
    private function fallbackSummary(array $rows): string
    {
        $clientLines = $this->latestLines($rows, true);

        return implode("\n", $clientLines !== [] ? $clientLines : $this->latestLines($rows, false));
    }

    /**
     * @param  list<array{who: string, at: string, text: string}>  $rows
     */
    private function normalizeChannel(mixed $value, array $rows): string
    {
        $channel = strtolower(trim((string) $value));
        $allowed = ['call', 'email', 'meeting', 'note', 'whatsapp', 'other'];
        if (in_array($channel, $allowed, true))
        {
            return $channel;
        }

        foreach (array_reverse($rows) as $row)
        {
            $text = (string) ($row['text'] ?? '');
            if (str_starts_with($text, 'Correo'))
            {
                return 'email';
            }
        }

        foreach ($rows as $row)
        {
            if (($row['who'] ?? '') === 'Cliente')
            {
                return 'whatsapp';
            }
        }

        return 'note';
    }

    private function unescapeLines(string $text): string
    {
        return str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
    }

    private function clampLine(string $text, int $max): string
    {
        $line = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($line === '' || mb_strlen($line) <= $max)
        {
            return $line;
        }

        return rtrim(mb_substr($line, 0, $max - 1)).'…';
    }

    private function normalizeIntentKey(mixed $value): ?string
    {
        $key = strtolower(trim((string) $value));
        if (! in_array($key, self::INTENT_KEYS, true))
        {
            return null;
        }

        return $key;
    }

    /**
     * @return array{id: int|null, key: string, name: string, emoji: string}|null
     */
    private function presentIntent(?string $key): ?array
    {
        if ($key === null || $key === '')
        {
            return null;
        }

        $intent = ContactIntent::query()->where('key', $key)->first();

        return [
            'id' => $intent?->id !== null ? (int) $intent->id : null,
            'key' => $key,
            'name' => match ($key)
            {
                'buy' => 'Comprar',
                'update' => 'Actualizar',
                'work' => 'Resolver',
                'cancel' => 'Cancelar',
                'other' => 'Otro',
                'unclear' => 'Poco clara',
                default => $intent?->name ?: $key,
            },
            'emoji' => (string) ($intent?->emoji ?: match ($key)
            {
                'buy' => '🛒',
                'update' => '🔄',
                'work' => '🔧',
                'cancel' => '🚪',
                'other' => '💬',
                default => '❔',
            }),
        ];
    }
}
