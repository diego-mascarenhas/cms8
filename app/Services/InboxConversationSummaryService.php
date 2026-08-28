<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactIntent;
use App\Models\Conversation;
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
You write a CRM follow-up digest from a WhatsApp thread (client and team).

The thread is a compact TOON table under messages (columns: who, at, text). Read it in chronological order.

Write summary: at most 3 short lines separated by \n, in the message language. Cover what they asked, where the thread stands, and what to do next. No greeting. Max 280 characters.

Commercial intent (intent_key), exactly one:
buy = wants to purchase a product or service
update = wants to change existing data, an order, a plan, or details
work = wants a job, repair, project, or service performed
cancel = wants to unsubscribe, cancel, or leave
other = a clear request that is none of the above
unclear = greeting, noise, or no actionable intent

Respond with ONLY a valid JSON object, no markdown. Example:
{"summary":"Pidió precio del plan.\\nPreguntó cuántos usuarios caben.\\nResponder hoy con cifras.","intent_key":"buy"}
PROMPT;

    public function __construct(
        private readonly ContactSentimentAnalysisService $sentimentAnalysisService,
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
    ) {}

    /**
     * @return array{summary: string, intent: array{id: int|null, key: string, name: string, emoji: string}|null}
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
            : $this->sentimentAnalysisService->clampSummary($this->fallbackSummary($rows));

        if ($summary === '')
        {
            throw new InvalidArgumentException(__('No se pudo resumir la conversación.'));
        }

        $this->sentimentAnalysisService->storeInboxDigest($contact, $summary, $parsed['intent_key']);

        return [
            'summary' => $summary,
            'intent' => $this->presentIntent($parsed['intent_key']),
        ];
    }

    /**
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, savings_percentage?: float|int, tokens_saved?: int}  $toon
     * @return array{summary: string, intent_key: string|null}
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
            $response = $agent->prompt($thread, [], AiTasks::provider('summary'));

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
                    ? $this->sentimentAnalysisService->clampSummary($data['summary'])
                    : '';
                $intentKey = $this->normalizeIntentKey($data['intent_key'] ?? null);

                return ['summary' => $summary, 'intent_key' => $intentKey];
            }

            return [
                'summary' => $this->sentimentAnalysisService->clampSummary($raw),
                'intent_key' => null,
            ];
        } catch (\Throwable)
        {
            return ['summary' => '', 'intent_key' => null];
        }
    }

    /**
     * @return list<array{who: string, at: string, text: string}>
     */
    private function threadRows(Team $team, Contact $contact): array
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
     * @param  list<array{who: string, at: string, text: string}>  $rows
     */
    private function fallbackSummary(array $rows): string
    {
        $lines = [];
        foreach (array_reverse($rows) as $row)
        {
            if (($row['who'] ?? '') === 'Equipo')
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

        return implode("\n", $lines);
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
