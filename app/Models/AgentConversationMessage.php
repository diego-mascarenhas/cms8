<?php

namespace App\Models;

use App\Services\Billing\BilledUsage;
use App\Services\Billing\McpClientDiscount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentConversationMessage extends Model
{
    protected $table = 'agent_conversation_messages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'conversation_id',
        'user_id',
        'agent',
        'role',
        'content',
        'attachments',
        'tool_calls',
        'tool_results',
        'usage',
        'meta',
        'billed_usage',
        'usage_invoice_id',
    ];

    protected $casts = [
        'attachments' => 'array',
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'usage' => 'array',
        'meta' => 'array',
        'billed_usage' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (AgentConversationMessage $message): void
        {
            if ($message->role !== 'assistant')
            {
                return;
            }

            if (BilledUsage::read(is_array($message->billed_usage) ? $message->billed_usage : null) !== null)
            {
                return;
            }

            $usage = is_array($message->usage) ? $message->usage : [];
            $meta = is_array($message->meta) ? $message->meta : [];
            $prompt = (int) ($usage['prompt_tokens'] ?? 0);
            $completion = (int) ($usage['completion_tokens'] ?? 0);
            if ($prompt + $completion <= 0)
            {
                $prompt = (int) ($usage['total_tokens'] ?? 0);
                $completion = 0;
            }
            if ($prompt + $completion <= 0)
            {
                return;
            }

            $model = $usage['model'] ?? $meta['model'] ?? null;
            $teamId = AgentConversation::query()->whereKey($message->conversation_id)->value('team_id');
            $priced = McpClientDiscount::apply($prompt, $completion, true);
            $message->billed_usage = BilledUsage::capture(
                is_numeric($teamId) ? (int) $teamId : null,
                $priced['prompt_tokens'],
                $priced['completion_tokens'],
                $priced['tokens_saved'],
                is_string($model) ? $model : null,
                $message->created_at ?? now(),
            );
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id');
    }

    public function usageInvoice(): BelongsTo
    {
        return $this->belongsTo(TeamUsageInvoice::class, 'usage_invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
