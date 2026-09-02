<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\AgentConversationMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Team;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\Billing\ClientTokenPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Laravel\Ai\AiManager;

class AssistantWhatsAppUsageByLineService
{
    private ?string $resolvedCheapest = null;

    public function __construct(
        private readonly AssistantSubscriptionService $subscriptions,
        private readonly ClientTokenPresenter $tokens,
    ) {}

    /**
     * Token usage per WhatsApp inbox line for the current team.
     *
     * @return array{
     *     period_days: int,
     *     period_start: string,
     *     period_end: string,
     *     token_multiplier: float,
     *     usd_to_display: float,
     *     client_presented: true,
     *     currency: string,
     *     default_model: string,
     *     totals: array{lines: int, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, amount_cents: int, saved_cents: int},
     *     all: array{calls: int, tokens: int, tokens_saved: int, amount_cents: int, saved_cents: int},
     *     sources: list<array{module_name: string, count: int, tokens_used: int, tokens_saved: int, amount_cents: int, saved_cents: int}>,
     *     by_model: list<array{model: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, amount_cents: int, saved_cents: int}>,
     *     lines: list<array<string, mixed>>,
     *     whatsapp: array{messages_sent: int, our_amount_cents: int, our_rate: float, currency: string}
     * }
     */
    public function forTeam(Team $team, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from === null || $to === null)
        {
            [$from, $to] = $this->subscriptions->usagePeriod($team);
        }
        $currency = TokenBillingRateService::displayCurrency();
        $defaultModel = $this->displayModel(null);

        $buckets = $this->bucketsFromConversationMetadata($team, $from, $to);
        $this->addInboundTranscriptions($team, $from, $to, $buckets);
        $this->addInboundImageOcr($team, $from, $to, $buckets);
        $this->fillGapsFromAgentConversations($team, $from, $to, $buckets, $defaultModel);

        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->get(['id', 'name', 'surname', 'phone']);

        $lines = $this->presentLines($buckets, $contacts, $from, $defaultModel);
        $byModel = $this->presentByModel($buckets, $from);

        $replies = (int) collect($lines)->sum('replies');
        $prompt = (int) collect($lines)->sum('prompt_tokens');
        $completion = (int) collect($lines)->sum('completion_tokens');
        $tokens = (int) collect($lines)->sum('total_tokens');
        $tokensSaved = (int) collect($lines)->sum('tokens_saved');
        $teamUsage = TeamApiUsageStatsService::forTeam((int) $team->id, $from, $to);
        $sources = $this->presentSources($teamUsage['byModule'], $from, $defaultModel);
        $headline = $this->headlineFromBreakdown($byModel, $sources, (int) $teamUsage['totalTokensUsed'], $from, $defaultModel);
        $allSaved = $this->tokens->scale((int) $teamUsage['totalTokensSaved']);
        $whatsapp = TeamWhatsAppUsageStatsService::forTeam($team, $from, $to);

        return [
            'period_days' => max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())),
            'period_start' => $from->toIso8601String(),
            'period_end' => $to->toIso8601String(),
            'token_multiplier' => TokenBillingRateService::clientTokenMultiplier(),
            'usd_to_display' => TokenBillingRateService::usdToDisplay($from),
            'client_presented' => true,
            'currency' => $currency,
            'default_model' => $defaultModel,
            'totals' => [
                'lines' => count($lines),
                'replies' => $replies,
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $tokens,
                'tokens_saved' => $tokensSaved,
                'amount_cents' => (int) collect($lines)->sum('amount_cents'),
                'saved_cents' => $this->tokens->costCents($tokensSaved, 0, $defaultModel, $from),
            ],
            'all' => [
                'calls' => (int) $teamUsage['totalCalls'],
                'tokens' => $headline['tokens'],
                'tokens_saved' => $allSaved,
                'amount_cents' => $headline['amount_cents'],
                'saved_cents' => $this->tokens->costCents($allSaved, 0, $defaultModel, $from),
            ],
            'sources' => $sources,
            'by_model' => $byModel,
            'lines' => $lines,
            'whatsapp' => [
                'messages_sent' => (int) $whatsapp['messages_sent'],
                'our_amount_cents' => (int) $whatsapp['our_amount_cents'],
                'our_rate' => (float) $whatsapp['our_rate'],
                'currency' => (string) $whatsapp['currency'],
            ],
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
     * @param  array<string, array{phone: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, models: array<string, int>, model_usage: array<string, array{prompt_tokens: int, completion_tokens: int, total_tokens: int}>, last_at: ?Carbon, from_metadata: bool}>  $buckets
     * @param  array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, model: string}  $usage
     */
    private function addToBucket(array &$buckets, string $phone, array $usage, mixed $at, bool $fromMetadata, bool $countReply = true): void
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
                'model_usage' => [],
                'last_at' => null,
                'from_metadata' => $fromMetadata,
            ];
        }

        if ($countReply)
        {
            $buckets[$phone]['replies']++;
        }
        $buckets[$phone]['prompt_tokens'] += $usage['prompt_tokens'];
        $buckets[$phone]['completion_tokens'] += $usage['completion_tokens'];
        $buckets[$phone]['total_tokens'] += $usage['total_tokens'];
        $buckets[$phone]['tokens_saved'] += $usage['tokens_saved'];
        $buckets[$phone]['from_metadata'] = $buckets[$phone]['from_metadata'] || $fromMetadata;

        $model = $usage['model'];
        $buckets[$phone]['models'][$model] = ($buckets[$phone]['models'][$model] ?? 0) + $usage['total_tokens'];
        if (! isset($buckets[$phone]['model_usage'][$model]))
        {
            $buckets[$phone]['model_usage'][$model] = [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ];
        }
        $buckets[$phone]['model_usage'][$model]['prompt_tokens'] += $usage['prompt_tokens'];
        $buckets[$phone]['model_usage'][$model]['completion_tokens'] += $usage['completion_tokens'];
        $buckets[$phone]['model_usage'][$model]['total_tokens'] += $usage['total_tokens'];

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
    private function presentLines(array $buckets, Collection $contacts, Carbon $from, string $defaultModel): array
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
            $presented = $this->presentModelUsage($bucket['model_usage'] ?? [], $from, $defaultModel);

            $lines[] = [
                'phone' => $bucket['phone'],
                'name' => $contact
                    ? trim($contact->name.' '.($contact->surname ?? ''))
                    : $bucket['phone'],
                'contact_id' => $contact?->id,
                'replies' => $bucket['replies'],
                'prompt_tokens' => $presented['prompt_tokens'],
                'completion_tokens' => $presented['completion_tokens'],
                'total_tokens' => $presented['total_tokens'],
                'tokens_saved' => $this->tokens->scale((int) $bucket['tokens_saved']),
                'amount_cents' => $presented['amount_cents'],
                'saved_cents' => $this->tokens->costCents($this->tokens->scale((int) $bucket['tokens_saved']), 0, $primary, $from),
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
     * @param  array<string, array{replies: int, tokens_saved: int, model_usage: array<string, array{prompt_tokens: int, completion_tokens: int, total_tokens: int}>}>  $buckets
     * @return list<array{model: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, amount_cents: int, saved_cents: int}>
     */
    private function presentByModel(array $buckets, Carbon $from): array
    {
        $grouped = [];

        foreach ($buckets as $bucket)
        {
            $primary = $this->sortedModels($bucket['models'] ?? [], '')[0] ?? null;
            foreach ($bucket['model_usage'] ?? [] as $model => $usage)
            {
                if (! isset($grouped[$model]))
                {
                    $grouped[$model] = [
                        'model' => $model,
                        'replies' => 0,
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0,
                        'tokens_saved' => 0,
                    ];
                }

                if ($model === $primary)
                {
                    $grouped[$model]['replies'] += (int) $bucket['replies'];
                    $grouped[$model]['tokens_saved'] += (int) $bucket['tokens_saved'];
                }
                $grouped[$model]['prompt_tokens'] += (int) $usage['prompt_tokens'];
                $grouped[$model]['completion_tokens'] += (int) $usage['completion_tokens'];
                $grouped[$model]['total_tokens'] += (int) $usage['total_tokens'];
            }
        }

        $rows = [];
        foreach ($grouped as $model => $row)
        {
            $presented = $this->tokens->present(
                (int) $row['prompt_tokens'],
                (int) $row['completion_tokens'],
                (string) $model,
                $from,
            );
            $rows[] = [
                'model' => $model,
                'replies' => (int) $row['replies'],
                'prompt_tokens' => $presented['prompt_tokens'],
                'completion_tokens' => $presented['completion_tokens'],
                'total_tokens' => $presented['total_tokens'],
                'tokens_saved' => $this->tokens->scale((int) $row['tokens_saved']),
                'amount_cents' => $presented['amount_cents'],
                'saved_cents' => $this->tokens->costCents($this->tokens->scale((int) $row['tokens_saved']), 0, (string) $model, $from),
            ];
        }

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
     * Headline tokens/value follow the catalog-priced model rows the customer can add up.
     * Log-only sources (Insights, OCR without a model row) still join that total.
     *
     * @param  list<array{model: string, total_tokens: int, amount_cents: int}>  $byModel
     * @param  list<array{module_name: string, tokens_used: int, amount_cents: int}>  $sources
     * @return array{tokens: int, amount_cents: int}
     */
    private function headlineFromBreakdown(array $byModel, array $sources, int $fallbackTokens, Carbon $from, string $defaultModel): array
    {
        $modelTokens = (int) collect($byModel)->sum('total_tokens');
        $modelAmount = (int) collect($byModel)->sum('amount_cents');
        $extra = collect($sources)->filter(
            fn (array $source): bool => ! $this->sourceCoveredByModels((string) ($source['module_name'] ?? ''), $byModel),
        );

        if ($modelTokens > 0 || $modelAmount > 0)
        {
            return [
                'tokens' => $modelTokens + (int) $extra->sum('tokens_used'),
                'amount_cents' => $modelAmount + (int) $extra->sum('amount_cents'),
            ];
        }

        $presented = $this->tokens->present($fallbackTokens, 0, $defaultModel, $from);

        return [
            'tokens' => $presented['total_tokens'],
            'amount_cents' => $presented['amount_cents'],
        ];
    }

    /**
     * @param  list<array{model: string}>  $byModel
     */
    private function sourceCoveredByModels(string $moduleName, array $byModel): bool
    {
        $name = strtolower(trim($moduleName));

        if ($name === 'chat')
        {
            return collect($byModel)->contains(fn (array $row): bool => ! $this->isAuxiliaryModel((string) ($row['model'] ?? '')));
        }

        if ($name === 'ocr')
        {
            return collect($byModel)->contains(fn (array $row): bool => $this->isOcrModel((string) ($row['model'] ?? '')));
        }

        return false;
    }

    private function isAuxiliaryModel(string $model): bool
    {
        $key = strtolower($model);

        return str_contains($key, 'whisper') || $this->isOcrModel($model);
    }

    private function isOcrModel(string $model): bool
    {
        $key = strtolower($model);

        return str_contains($key, 'gpt-4o-mini') || str_contains($key, 'ocr');
    }

    /**
     * @param  array<int|string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>  $byModule
     * @return list<array{module_name: string, count: int, tokens_used: int, tokens_saved: int, amount_cents: int, saved_cents: int}>
     */
    private function presentSources(array $byModule, Carbon $from, string $defaultModel): array
    {
        $sources = [];

        foreach ($byModule as $row)
        {
            $presented = $this->tokens->present((int) ($row['tokens_used'] ?? 0), 0, $defaultModel, $from);
            $saved = $this->tokens->scale((int) ($row['tokens_saved'] ?? 0));
            $sources[] = [
                'module_name' => (string) ($row['module_name'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
                'tokens_used' => $presented['total_tokens'],
                'tokens_saved' => $saved,
                'amount_cents' => $presented['amount_cents'],
                'saved_cents' => $this->tokens->costCents($saved, 0, $defaultModel, $from),
            ];
        }

        usort($sources, fn (array $left, array $right): int => $right['tokens_used'] <=> $left['tokens_used']);

        return $sources;
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
     * @param  array<string, array{prompt_tokens: int, completion_tokens: int, total_tokens: int}>  $modelUsage
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, amount_cents: int}
     */
    private function presentModelUsage(array $modelUsage, Carbon $from, string $defaultModel): array
    {
        if ($modelUsage === [])
        {
            return $this->tokens->present(0, 0, $defaultModel, $from);
        }

        $prompt = 0;
        $completion = 0;
        $amount = 0;
        foreach ($modelUsage as $model => $usage)
        {
            $presented = $this->tokens->present(
                (int) $usage['prompt_tokens'],
                (int) $usage['completion_tokens'],
                (string) $model,
                $from,
            );
            $prompt += $presented['prompt_tokens'];
            $completion += $presented['completion_tokens'];
            $amount += $presented['amount_cents'];
        }

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
            'amount_cents' => $amount,
        ];
    }

    /**
     * @param  array<string, array{phone: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, models: array<string, int>, model_usage: array<string, array{prompt_tokens: int, completion_tokens: int, total_tokens: int}>, last_at: ?Carbon, from_metadata: bool}>  $buckets
     */
    private function addInboundTranscriptions(Team $team, Carbon $from, Carbon $to, array &$buckets): void
    {
        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        if ($teamNumber === '')
        {
            return;
        }

        $rows = Conversation::query()
            ->where('channel', 'whatsapp')
            ->where('direction', 'inbound')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where(function ($query) use ($teamNumber): void
            {
                $query->where('to', $teamNumber)
                    ->orWhere('to', 'like', $teamNumber.':%');
            })
            ->get(['from', 'to', 'body', 'metadata', 'created_at']);

        foreach ($rows as $row)
        {
            $usage = $this->transcriptionUsageFromMessage($row->body, $row->metadata);
            if ($usage === null)
            {
                continue;
            }

            $phone = $this->peerDigits($row, $teamNumber);
            if ($phone === '' || $phone === $teamNumber)
            {
                continue;
            }

            $this->addToBucket($buckets, $phone, $usage, $row->created_at, fromMetadata: true, countReply: false);
        }
    }

    /**
     * @param  array<string, array{phone: string, replies: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, models: array<string, int>, model_usage: array<string, array{prompt_tokens: int, completion_tokens: int, total_tokens: int}>, last_at: ?Carbon, from_metadata: bool}>  $buckets
     */
    private function addInboundImageOcr(Team $team, Carbon $from, Carbon $to, array &$buckets): void
    {
        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        if ($teamNumber === '')
        {
            return;
        }

        $ingestions = DocumentIngestion::query()
            ->where('team_id', $team->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->whereNotNull('conversation_id')
            ->with(['conversation:id,from,to,direction,created_at'])
            ->get(['id', 'conversation_id', 'ocr_text', 'mime_type', 'classification_meta', 'created_at']);

        $seen = [];
        foreach ($ingestions as $ingestion)
        {
            $usage = $this->ocrUsageFromIngestion($ingestion);
            if ($usage === null)
            {
                continue;
            }

            $conversation = $ingestion->conversation;
            if ($conversation === null)
            {
                continue;
            }

            $phone = $this->peerDigits($conversation, $teamNumber);
            if ($phone === '' || $phone === $teamNumber)
            {
                continue;
            }

            $this->addToBucket($buckets, $phone, $usage, $ingestion->created_at, fromMetadata: true, countReply: false);
            $seen[(int) $ingestion->conversation_id] = true;
        }

        $rows = Conversation::query()
            ->where('channel', 'whatsapp')
            ->where('direction', 'inbound')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where(function ($query) use ($teamNumber): void
            {
                $query->where('to', $teamNumber)
                    ->orWhere('to', 'like', $teamNumber.':%');
            })
            ->get(['id', 'from', 'to', 'media', 'metadata', 'created_at']);

        foreach ($rows as $row)
        {
            if (isset($seen[(int) $row->id]))
            {
                continue;
            }

            $usage = $this->ocrUsageFromConversation($row);
            if ($usage === null)
            {
                continue;
            }

            $phone = $this->peerDigits($row, $teamNumber);
            if ($phone === '' || $phone === $teamNumber)
            {
                continue;
            }

            $this->addToBucket($buckets, $phone, $usage, $row->created_at, fromMetadata: true, countReply: false);
        }
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, model: string}|null
     */
    private function ocrUsageFromIngestion(DocumentIngestion $ingestion): ?array
    {
        $meta = is_array($ingestion->classification_meta) ? $ingestion->classification_meta : [];
        $stored = is_array($meta['ocr_usage'] ?? null) ? $meta['ocr_usage'] : null;
        if (is_array($stored))
        {
            $fromStored = $this->usageFromArray($stored);
            if ($fromStored !== null)
            {
                $fromStored['model'] = $this->displayModel(
                    is_string($stored['model'] ?? null) ? $stored['model'] : $this->defaultOcrModel(),
                );

                return $fromStored;
            }
        }

        $engine = (string) ($meta['ocr_engine_used'] ?? '');
        if ($engine !== '' && ! str_contains($engine, 'ai'))
        {
            return null;
        }

        $text = trim((string) $ingestion->ocr_text);
        if ($text === '' && ! str_starts_with(strtolower((string) $ingestion->mime_type), 'image/'))
        {
            return null;
        }
        if ($text === '' && $engine === '')
        {
            return null;
        }

        $tokens = max(1, (int) ceil(max(1, str_word_count($text)) * 1.3));

        return [
            'prompt_tokens' => $tokens,
            'completion_tokens' => 0,
            'total_tokens' => $tokens,
            'tokens_saved' => 0,
            'model' => $this->defaultOcrModel(),
        ];
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, model: string}|null
     */
    private function ocrUsageFromConversation(Conversation $row): ?array
    {
        $meta = is_array($row->metadata) ? $row->metadata : [];
        $stored = is_array($meta['ocr_usage'] ?? null) ? $meta['ocr_usage'] : null;
        if (is_array($stored))
        {
            $fromStored = $this->usageFromArray($stored);
            if ($fromStored !== null)
            {
                $fromStored['model'] = $this->displayModel(
                    is_string($stored['model'] ?? null) ? $stored['model'] : $this->defaultOcrModel(),
                );

                return $fromStored;
            }
        }

        return null;
    }

    private function defaultOcrModel(): string
    {
        return app(DocumentAiOcrService::class)->resolveOcrModel();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, model: string}|null
     */
    private function transcriptionUsageFromMessage(mixed $body, mixed $metadata): ?array
    {
        $text = trim((string) $body);
        $meta = is_array($metadata) ? $metadata : [];
        $stored = is_array($meta['transcription_usage'] ?? null) ? $meta['transcription_usage'] : null;
        if (is_array($stored))
        {
            $fromStored = $this->usageFromArray($stored);
            if ($fromStored !== null)
            {
                $fromStored['model'] = $this->displayModel(
                    is_string($stored['model'] ?? null) ? $stored['model'] : 'whisper-1',
                );

                return $fromStored;
            }
        }

        $transcribed = ($meta['TranscribedAudio'] ?? $meta['transcribed_audio'] ?? null) === '1'
            || ($meta['TranscribedAudio'] ?? $meta['transcribed_audio'] ?? null) === true
            || preg_match('/^\s*\[audio\]\s*:/iu', $text) === 1;
        if (! $transcribed)
        {
            return null;
        }

        $transcript = trim((string) preg_replace('/^\s*\[audio\]\s*:?\s*/iu', '', $text));
        $tokens = max(1, (int) ceil(str_word_count($transcript) * 1.3));

        return [
            'prompt_tokens' => $tokens,
            'completion_tokens' => 0,
            'total_tokens' => $tokens,
            'tokens_saved' => 0,
            'model' => 'whisper-1',
        ];
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
}
