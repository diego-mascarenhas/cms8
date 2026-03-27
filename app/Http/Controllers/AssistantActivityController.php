<?php

namespace App\Http\Controllers;

use App\Models\AgentConversationMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AssistantActivityController extends Controller
{
    /**
     * @return array{0: \App\Models\User, 1: \App\Models\Team}
     */
    private function authorizeAdminInCurrentTeam(): array
    {
        $user = auth()->user();
        $team = $user?->currentTeam ?? $user?->teams()->first();

        abort_unless($user !== null && $team !== null, 403);
        abort_unless($user->hasRole(['root', 'admin']), 403);

        return [$user, $team];
    }

    private function baseQuery(int $teamId, string $startDate, string $endDate): Builder
    {
        return AgentConversationMessage::query()
            ->where('agent', 'chat_assistant')
            ->where('role', 'assistant')
            ->whereHas('conversation', function ($query) use ($teamId)
            {
                $query->where('team_id', $teamId);
            })
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
    }

    public function index(Request $request): View
    {
        [, $team] = $this->authorizeAdminInCurrentTeam();

        $defaultProvider = strtoupper((string) config('ai.assistant_provider', 'anthropic'));
        $defaultModel = (string) config('ai.assistant_model', 'cheapest');
        $estimatedCostPerMillion = (float) config('services.anthropic.estimated_cost_per_million', 6.0);
        $startDate = (string) ($request->input('start_date') ?: now()->subDays(30)->toDateString());
        $endDate = (string) ($request->input('end_date') ?: now()->toDateString());

        $messages = $this->baseQuery((int) $team->id, $startDate, $endDate)
            ->with([
                'conversation:id,title,user_id,team_id',
                'conversation.user:id,name,email',
                'user:id,name,email',
            ])
            ->latest()
            ->get();

        $messages->transform(function (AgentConversationMessage $message) use ($defaultProvider, $defaultModel, $estimatedCostPerMillion)
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

        $totalMessages = (int) $messages->count();
        $totalTokens = (int) $messages->sum('total_tokens_value');
        $totalEstimatedCostUsd = round((float) $messages->sum('estimated_cost_usd'), 6);

        return view('assistant.activity', compact(
            'messages',
            'totalMessages',
            'totalTokens',
            'totalEstimatedCostUsd',
            'defaultProvider',
            'defaultModel',
            'startDate',
            'endDate',
        ));
    }

    public function data(Request $request): JsonResponse
    {
        [, $team] = $this->authorizeAdminInCurrentTeam();

        $defaultProvider = strtoupper((string) config('ai.assistant_provider', 'anthropic'));
        $defaultModel = (string) config('ai.assistant_model', 'cheapest');
        $estimatedCostPerMillion = (float) config('services.anthropic.estimated_cost_per_million', 6.0);
        $startDate = (string) ($request->input('start_date') ?: now()->subDays(30)->toDateString());
        $endDate = (string) ($request->input('end_date') ?: now()->toDateString());

        $query = $this->baseQuery((int) $team->id, $startDate, $endDate)
            ->with([
                'conversation:id,title,user_id,team_id',
                'conversation.user:id,name,email',
                'user:id,name,email',
            ]);

        return DataTables::eloquent($query)
            ->addColumn('date_display', fn (AgentConversationMessage $message) => optional($message->created_at)->format('Y-m-d H:i'))
            ->addColumn('date_human', fn (AgentConversationMessage $message) => optional($message->created_at)->diffForHumans())
            ->addColumn('user_name', fn (AgentConversationMessage $message) => $message->conversation?->user?->name ?? $message->user?->name ?? 'Desconocido')
            ->addColumn('user_email', fn (AgentConversationMessage $message) => $message->conversation?->user?->email ?? $message->user?->email)
            ->addColumn('conversation_title', fn (AgentConversationMessage $message) => $message->conversation?->title ?? 'Sin título')
            ->addColumn('provider_name', function (AgentConversationMessage $message) use ($defaultProvider)
            {
                $usage = is_array($message->usage) ? $message->usage : [];
                $meta = is_array($message->meta) ? $message->meta : [];

                return strtoupper((string) ($meta['provider'] ?? $usage['provider'] ?? $defaultProvider));
            })
            ->addColumn('model_name', function (AgentConversationMessage $message) use ($defaultModel)
            {
                $usage = is_array($message->usage) ? $message->usage : [];
                $meta = is_array($message->meta) ? $message->meta : [];

                return (string) ($meta['model'] ?? $usage['model'] ?? $defaultModel);
            })
            ->addColumn('prompt_tokens_value', function (AgentConversationMessage $message)
            {
                $usage = is_array($message->usage) ? $message->usage : [];

                return (int) ($usage['prompt_tokens'] ?? 0);
            })
            ->addColumn('completion_tokens_value', function (AgentConversationMessage $message)
            {
                $usage = is_array($message->usage) ? $message->usage : [];

                return (int) ($usage['completion_tokens'] ?? 0);
            })
            ->addColumn('total_tokens_value', function (AgentConversationMessage $message)
            {
                $usage = is_array($message->usage) ? $message->usage : [];
                $prompt = (int) ($usage['prompt_tokens'] ?? 0);
                $completion = (int) ($usage['completion_tokens'] ?? 0);

                return (int) ($usage['total_tokens'] ?? ($prompt + $completion));
            })
            ->addColumn('estimated_cost_usd', function (AgentConversationMessage $message) use ($estimatedCostPerMillion)
            {
                $usage = is_array($message->usage) ? $message->usage : [];
                $prompt = (int) ($usage['prompt_tokens'] ?? 0);
                $completion = (int) ($usage['completion_tokens'] ?? 0);
                $totalTokens = (int) ($usage['total_tokens'] ?? ($prompt + $completion));

                return round(($totalTokens / 1000000) * $estimatedCostPerMillion, 6);
            })
            ->toJson();
    }
}
