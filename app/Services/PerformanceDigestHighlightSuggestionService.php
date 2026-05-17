<?php

namespace App\Services;

use App\Models\Team;
use App\Models\UserDailyPerformanceInsight;
use Illuminate\Support\Facades\Route;

class PerformanceDigestHighlightSuggestionService
{
    public function __construct(
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
        private readonly PerformanceDigestUnreadMessageDetailService $messageDetailService,
    ) {}

    /**
     * @return list<array{key: string, label: string, count: int, suggestion: string, action_url: string|null, action_label: string|null}>
     */
    public function forInsight(UserDailyPerformanceInsight $insight, Team $team): array
    {
        $snapshot = $insight->context_snapshot ?? [];
        $items = $snapshot['highlight_items'] ?? [];

        if ($items === [])
        {
            $items = $this->digestCollector->buildHighlightItems($this->digestSubsetFromSnapshot($snapshot), $team);
        }

        return $this->enrichItems($items, $team);
    }

    /**
     * @param  list<array{key: string, label: string, count: int}>  $items
     * @return list<array{
     *     key: string,
     *     label: string,
     *     count: int,
     *     suggestion: string,
     *     action_url: string|null,
     *     action_label: string|null,
     *     detail_mode: string,
     *     messages: list<array<string, mixed>>
     * }>
     */
    public function enrichItems(array $items, Team $team): array
    {
        $enriched = [];

        foreach ($items as $item)
        {
            $key = (string) ($item['key'] ?? '');
            $count = (int) ($item['count'] ?? 0);
            $label = (string) ($item['label'] ?? '');

            if ($key === '' || $label === '')
            {
                continue;
            }

            $messages = $this->messageDetailService->forHighlightKey($key, $team);
            $detailMode = $messages !== [] ? 'messages' : 'single';

            $translationKey = 'app.performance_digest_suggestion_'.$key;
            $suggestion = __($translationKey, ['count' => $count]);
            if ($suggestion === $translationKey)
            {
                $suggestion = (string) __('app.performance_digest_suggestion_default', ['count' => $count]);
            }

            $actionUrl = $this->actionUrlForKey($key);
            $actionLabelKey = 'app.performance_digest_suggestion_action_'.$key;
            $actionLabel = __($actionLabelKey);
            if ($actionLabel === $actionLabelKey)
            {
                $actionLabel = $actionUrl !== null
                    ? (string) __('app.performance_digest_suggestion_action_default')
                    : null;
            }

            $enriched[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'suggestion' => $suggestion,
                'action_url' => $actionUrl,
                'action_label' => $actionLabel,
                'detail_mode' => $detailMode,
                'messages' => $messages,
            ];
        }

        return $enriched;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function digestSubsetFromSnapshot(array $snapshot): array
    {
        $keys = [
            'whatsapp',
            'email',
            'appointments',
            'client_sentiment',
            'tasks',
            'projects',
            'services',
            'invoices',
            'payments',
            'user_activity',
        ];

        $digest = [];
        foreach ($keys as $key)
        {
            if (isset($snapshot[$key]) && is_array($snapshot[$key]))
            {
                $digest[$key] = $snapshot[$key];
            }
        }

        return $digest;
    }

    private function actionUrlForKey(string $key): ?string
    {
        $route = match ($key)
        {
            'whatsapp_unread', 'whatsapp_inbound' => Route::has('chat.index') ? 'chat.index' : null,
            'email_unread' => Route::has('mail-list') ? 'mail-list' : null,
            'appointments_today' => Route::has('app-calendar') ? 'app-calendar' : null,
            'stressed_clients' => Route::has('contact-list') ? 'contact-list' : null,
            'tasks_overdue', 'tasks_due_today', 'tasks_pending', 'quiet_day' => Route::has('task.index') ? 'task.index' : null,
            'projects_due' => Route::has('project-list') ? 'project-list' : null,
            'services_action' => Route::has('service-list') ? 'service-list' : null,
            'invoices_overdue', 'invoices_unpaid' => Route::has('invoice.index') ? 'invoice.index' : null,
            'payments_claim' => Route::has('payment.index') ? 'payment.index' : null,
            default => null,
        };

        return $route !== null ? route($route) : null;
    }
}
