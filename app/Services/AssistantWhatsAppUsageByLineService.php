<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\AgentConversationMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Team;
use App\Services\Billing\AssistantSubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Laravel\Ai\AiManager;

class AssistantWhatsAppUsageByLineService
{
    private ?string $resolvedCheapest = null;

    public function __construct(
        private readonly AssistantSubscriptionService $subscriptions,
    ) {}

    /**
     * Token usage per WhatsApp inbox line for the current team.
     *
     * @return array{
     *     period_days: int,
     *     period_start: string,
     *     period_end: string,
     *     rate_per_million: float,
     *     currency: string,
     *     default_model: string,
     *     totals: array{lines: int, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, amount_cents: int, saved_cents: int},
     *     by_model: list<array{model: string, replies: int, total_tokens: int, tokens_saved: int, amount_cents: int, saved_cents: int}>,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function forTeam(Team $team, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from === null || $to === null)
        {
            [$from, $to] = $this->subscriptions->usagePeriod($team);
        }
        $rate = TeamApiUsageStatsService::sellRatePerMillion();
        $currency = strtoupper((string) config('humano_pricing.token_billing.currency', 'EUR'));
        $defaultModel = $this->displayModel(null);

        $buckets = $this->bucketsFromConversationMetadata($team, $from, $to);
        $this->fillGapsFromAgentConversations($team, $from, $to, $buckets, $defaultModel);

        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->get(['id', 'name', 'surname', 'phone']);

        $lines = $this->presentLines($buckets, $contacts, $rate, $defaultModel);
        $byModel = $this->presentByModel($lines, $rate);

        $replies = (int) collect($lines)->sum('replies');
        $prompt = (int) collect($lines)->sum('prompt_tokens');
        $completion = (int) collect($lines)->sum('completion_tokens');
        $tokens = (int) collect($lines)->sum('total_tokens');
        $tokensSaved = (int) collect($lines)->sum('tokens_saved');

        return [
            'period_days' => max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())),
            'period_start' => $from->toIso8601String(),
            'period_end' => $to->toIso8601String(),
            'rate_per_million' => $rate,
            'currency' => $currency,
            'default_model' => $defaultModel,
            'totals' => [
                'lines' => count($lines),
                'replies' => $replies,
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $tokens,
                'tokens_saved' => $tokensSaved,
                'amount_cents' => $this->cents($tokens, $rate),
                'saved_cents' => $this->cents($tokensSaved, $rate),
            ],
            'by_model' => $byModel,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, array{phone: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, models: array<string, int>, last_at: ?Carbon, from_metadata: bool}>
     */
    private function bucketsFromConversationMetadata(Team $team, Carbon $from, Carbon $to): array
    {
        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        if ($teamNumber === '')
        {
            return [];
        }

        $rows = Conversation::query()
            ->where('channel', 'whatsapp')
            ->where('direction', 'outbound')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where(function ($query) use ($teamNumber): void
            {
                $query->where('from', $teamNumber)
                    ->orWhere('from', 'like', $teamNumber.':%');
            })
            ->whereNotNull('metadata')
            ->get(['from', 'to', 'direction', 'metadata', 'created_at']);

        $buckets = [];

        foreach ($rows as $row)
        {
            $usage = $this->usageFromMetadata($row->metadata);
            if ($usage === null)
            {
                continue;
            }

            $phone = $this->peerDigits($row, $teamNumber);
            if ($phone === '' || $phone === $teamNumber)
            {
                continue;
            }

            $this->addToBucket($buckets, $phone, $usage, $row->created_at, fromMetadata: true);
        }

        return $buckets;
    }

    /**
     * @param  array<string, array{phone: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, models: array<string, int>, last_at: ?Carbon, from_metadata: bool}>  $buckets
     */
    private function fillGapsFromAgentConversations(Team $team, Carbon $from, Carbon $to, array &$buckets, string $defaultModel): void
    {
        $messages = AgentConversationMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->whereHas('conversation', function ($query) use ($team): void
            {
                $query->where('team_id', $team->id);
            })
            ->with(['conversation.user:id,name,phone'])
            ->get(['conversation_id', 'usage', 'meta', 'created_at']);

        foreach ($messages as $message)
        {
            $usage = $this->usageFromArray($message->usage);
            if ($usage === null)
            {
                continue;
            }

            $phone = preg_replace('/[^0-9]/', '', (string) ($message->conversation?->user?->phone ?? ''));
            if ($phone === '')
            {
                continue;
            }

            if (($buckets[$phone]['from_metadata'] ?? false) === true)
            {
                continue;
            }

            $model = $this->modelFromMeta($message->meta, $message->usage, $defaultModel);
            $usage['model'] = $model;
            $this->addToBucket($buckets, $phone, $usage, $message->created_at, fromMetadata: false);
        }
    }

    /**
     * @param  array<string, array{phone: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, models: array<string, int>, last_at: ?Carbon, from_metadata: bool}>  $buckets
     * @param  array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, model: string}  $usage
     */
    private function addToBucket(array &$buckets, string $phone, array $usage, mixed $at, bool $fromMetadata): void
    {
        if (! isset($buckets[$phone]))
        {
            $buckets[$phone] = [
                'phone' => $phone,
                'replies' => 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'tokens_saved' => 0,
                'models' => [],
                'last_at' => null,
                'from_metadata' => $fromMetadata,
            ];
        }

        $buckets[$phone]['replies']++;
        $buckets[$phone]['prompt_tokens'] += $usage['prompt_tokens'];
        $buckets[$phone]['completion_tokens'] += $usage['completion_tokens'];
        $buckets[$phone]['total_tokens'] += $usage['total_tokens'];
        $buckets[$phone]['tokens_saved'] += $usage['tokens_saved'];
        $buckets[$phone]['from_metadata'] = $buckets[$phone]['from_metadata'] || $fromMetadata;

        $model = $usage['model'];
        $buckets[$phone]['models'][$model] = ($buckets[$phone]['models'][$model] ?? 0) + $usage['total_tokens'];

        $carbon = $at instanceof Carbon ? $at : Carbon::parse((string) $at);
        if ($buckets[$phone]['last_at'] === null || $carbon->gt($buckets[$phone]['last_at']))
        {
            $buckets[$phone]['last_at'] = $carbon;
        }
    }

    /**
     * @param  array<string, array{phone: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, models: array<string, int>, last_at: ?Carbon, from_metadata: bool}>  $buckets
     * @param  Collection<int, Contact>  $contacts
     * @return list<array<string, mixed>>
     */
    private function presentLines(array $buckets, Collection $contacts, float $rate, string $defaultModel): array
    {
        $lines = [];

        foreach ($buckets as $bucket)
        {
            if ($bucket['total_tokens'] <= 0)
            {
                continue;
            }

            $contact = $this->matchContact($contacts, $bucket['phone']);
            $models = $this->sortedModels($bucket['models'], $defaultModel);
            $primary = $models[0] ?? $defaultModel;

            $lines[] = [
                'phone' => $bucket['phone'],
                'name' => $contact
                    ? trim($contact->name.' '.($contact->surname ?? ''))
                    : $bucket['phone'],
                'contact_id' => $contact?->id,
                'replies' => $bucket['replies'],
                'prompt_tokens' => $bucket['prompt_tokens'],
                'completion_tokens' => $bucket['completion_tokens'],
                'total_tokens' => $bucket['total_tokens'],
                'tokens_saved' => $bucket['tokens_saved'],
                'amount_cents' => $this->cents($bucket['total_tokens'], $rate),
                'saved_cents' => $this->cents($bucket['tokens_saved'], $rate),
                'model' => $primary,
                'models' => $models,
                'last_at' => $bucket['last_at']?->toIso8601String(),
                'inbox_href' => '/inbox?phone='.rawurlencode($bucket['phone']),
            ];
        }

        usort($lines, fn (array $left, array $right): int => $right['total_tokens'] <=> $left['total_tokens']);

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{model: string, replies: int, total_tokens: int, tokens_saved: int, amount_cents: int, saved_cents: int}>
     */
    private function presentByModel(array $lines, float $rate): array
    {
        $grouped = [];

        foreach ($lines as $line)
        {
            $model = (string) ($line['model'] ?? '');
            if ($model === '')
            {
                continue;
            }

            if (! isset($grouped[$model]))
            {
                $grouped[$model] = [
                    'model' => $model,
                    'replies' => 0,
                    'total_tokens' => 0,
                    'tokens_saved' => 0,
                ];
            }

            $grouped[$model]['replies'] += (int) $line['replies'];
            $grouped[$model]['total_tokens'] += (int) $line['total_tokens'];
            $grouped[$model]['tokens_saved'] += (int) $line['tokens_saved'];
        }

        $rows = array_values(array_map(function (array $row) use ($rate): array
        {
            $row['amount_cents'] = $this->cents((int) $row['total_tokens'], $rate);
            $row['saved_cents'] = $this->cents((int) $row['tokens_saved'], $rate);

            return $row;
        }, $grouped));

        usort($rows, fn (array $left, array $right): int => $right['total_tokens'] <=> $left['total_tokens']);

        return $rows;
    }

    /**
     * @param  array<string, int>  $models
     * @return list<string>
     */
    private function sortedModels(array $models, string $defaultModel): array
    {
        if ($models === [])
        {
            return [$defaultModel];
        }

        arsort($models);

        return array_values(array_keys($models));
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    private function matchContact(Collection $contacts, string $peer): ?Contact
    {
        foreach ($contacts as $contact)
        {
            if (PhoneHelper::digitsBelongToSameLine((string) $contact->phone, $peer))
            {
                return $contact;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, model: string}|null
     */
    private function usageFromMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata))
        {
            return null;
        }

        $usage = $this->usageFromArray($metadata['token_usage'] ?? null);
        if ($usage === null)
        {
            return null;
        }

        $usage['model'] = $this->displayModel(
            is_string($metadata['token_usage']['model'] ?? null)
                ? $metadata['token_usage']['model']
                : (is_string($metadata['model'] ?? null) ? $metadata['model'] : null),
        );

        return $usage;
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, model: string}|null
     */
    private function usageFromArray(mixed $usage): ?array
    {
        if (! is_array($usage))
        {
            return null;
        }

        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? 0);
        if ($total <= 0)
        {
            $total = $prompt + $completion;
        }
        if ($total <= 0)
        {
            return null;
        }

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'tokens_saved' => max(0, (int) ($usage['tokens_saved'] ?? 0)),
            'model' => $this->displayModel(is_string($usage['model'] ?? null) ? $usage['model'] : null),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>|null  $usage
     */
    private function modelFromMeta(mixed $meta, mixed $usage, string $defaultModel): string
    {
        if (is_array($meta) && is_string($meta['model'] ?? null) && $meta['model'] !== '')
        {
            return $this->displayModel($meta['model']);
        }

        if (is_array($usage) && is_string($usage['model'] ?? null) && $usage['model'] !== '')
        {
            return $this->displayModel($usage['model']);
        }

        return $defaultModel;
    }

    private function displayModel(?string $model): string
    {
        $value = is_string($model) ? trim($model) : '';
        if ($value !== '' && strtolower($value) !== 'cheapest')
        {
            return $value;
        }

        $configured = trim((string) config('ai.assistant_model', 'cheapest'));
        if ($configured !== '' && strtolower($configured) !== 'cheapest')
        {
            return $configured;
        }

        if ($this->resolvedCheapest === null)
        {
            $this->resolvedCheapest = $this->lookupCheapestModel() ?? '';
        }

        return $this->resolvedCheapest !== ''
            ? $this->resolvedCheapest
            : ($configured !== '' ? $configured : 'cheapest');
    }

    private function lookupCheapestModel(): ?string
    {
        try
        {
            $provider = (string) config('ai.assistant_provider', 'anthropic');
            $cheapest = app(AiManager::class)->textProvider($provider)->cheapestTextModel();

            return is_string($cheapest) && trim($cheapest) !== '' ? trim($cheapest) : null;
        } catch (\Throwable)
        {
            return null;
        }
    }

    private function peerDigits(Conversation $row, string $teamNumber): string
    {
        $from = preg_replace('/[^0-9]/', '', explode(':', (string) $row->from)[0] ?? '');
        $to = preg_replace('/[^0-9]/', '', explode(':', (string) $row->to)[0] ?? '');

        if ($from === $teamNumber)
        {
            return $to;
        }

        if ($to === $teamNumber)
        {
            return $from;
        }

        return $row->direction === 'inbound' ? $from : $to;
    }

    private function cents(int $tokens, float $rate): int
    {
        return (int) round(($tokens / 1_000_000) * $rate * 100);
    }
}
