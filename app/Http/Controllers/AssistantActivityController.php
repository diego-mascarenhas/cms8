<?php

namespace App\Http\Controllers;

use App\Models\AgentConversationMessage;
use Illuminate\Contracts\View\View;

class AssistantActivityController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $team = $user?->currentTeam ?? $user?->teams()->first();

        abort_unless($user !== null && $team !== null, 403);
        abort_unless($user->hasRole(['root', 'admin']), 403);

        $defaultProvider = strtoupper((string) config('ai.assistant_provider', 'anthropic'));
        $defaultModel = (string) config('ai.assistant_model', 'cheapest');
        $estimatedCostPerMillion = (float) config('services.anthropic.estimated_cost_per_million', 6.0);

        $messages = AgentConversationMessage::query()
            ->where('agent', 'chat_assistant')
            ->where('role', 'assistant')
            ->whereHas('conversation', function ($query) use ($team)
            {
                $query->where('team_id', $team->id);
            })
            ->with([
                'conversation:id,title,user_id,team_id',
                'conversation.user:id,name,email',
                'user:id,name,email',
            ])
            ->latest()
            ->paginate(25);

        $messages->getCollection()->transform(function (AgentConversationMessage $message) use ($defaultProvider, $defaultModel, $estimatedCostPerMillion)
        {
            $usage = is_array($message->usage) ? $message->usage : [];
            $meta = is_array($message->meta) ? $message->meta : [];

            $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
            $totalTokens = (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens));

            $model = (string) ($meta['model'] ?? $usage['model'] ?? $defaultModel);
            $provider = strtoupper((string) ($meta['provider'] ?? $usage['provider'] ?? $defaultProvider));

            $message->setAttribute('prompt_tokens_value', $promptTokens);
            $message->setAttribute('completion_tokens_value', $completionTokens);
            $message->setAttribute('total_tokens_value', $totalTokens);
            $message->setAttribute('model_name', $model);
            $message->setAttribute('provider_name', $provider);
            $message->setAttribute('estimated_cost_usd', round(($totalTokens / 1000000) * $estimatedCostPerMillion, 6));

            return $message;
        });

        $totalMessages = (int) $messages->total();
        $totalTokens = (int) $messages->getCollection()->sum('total_tokens_value');
        $totalEstimatedCostUsd = round((float) $messages->getCollection()->sum('estimated_cost_usd'), 6);

        return view('assistant.activity', compact(
            'messages',
            'totalMessages',
            'totalTokens',
            'totalEstimatedCostUsd',
            'defaultProvider',
            'defaultModel',
        ));
    }
}
