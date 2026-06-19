<?php

namespace App\Services;

use App\Models\Team;
use App\Models\UserDailyPerformanceInsight;
use App\Support\PerformanceDigestReplyParser;
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

            $scheduleMeta = $this->resolveHighlightScheduleMeta($key, $messages, $suggestion);
            $actionUrl = $scheduleMeta['action_url'];
            $actionLabel = $scheduleMeta['action_label'];

            if ($actionLabel === null && $actionUrl !== null)
            {
                $actionLabelKey = 'app.performance_digest_suggestion_action_'.$key;
                $actionLabel = __($actionLabelKey);
                if ($actionLabel === $actionLabelKey)
                {
                    $actionLabel = (string) __('app.performance_digest_suggestion_action_default');
                }
            }

            $enriched[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'suggestion' => $suggestion,
                'action_url' => $actionUrl,
                'action_label' => $actionLabel,
                'schedule_action' => $scheduleMeta['schedule_action'],
                'schedule_recipient' => $scheduleMeta['schedule_recipient'],
                'schedule_subject' => $scheduleMeta['schedule_subject'],
                'detail_mode' => $detailMode,
                'messages' => $messages,
            ];
        }

        return $enriched;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{
     *     schedule_action: string|null,
     *     schedule_recipient: string,
     *     schedule_subject: string|null,
     *     action_url: string|null,
     *     action_label: string|null
     * }
     */
    private function resolveHighlightScheduleMeta(string $key, array $messages, string $suggestion): array
    {
        if ($messages !== [])
        {
            $first = $messages[0];
            $scheduleAction = $first['schedule_action'] ?? null;

            if (is_string($scheduleAction) && $scheduleAction !== '')
            {
                return [
                    'schedule_action' => $scheduleAction,
                    'schedule_recipient' => (string) ($first['schedule_recipient'] ?? ''),
                    'schedule_subject' => $first['schedule_subject'] ?? null,
                    'action_url' => null,
                    'action_label' => (string) ($first['action_label'] ?? ''),
                ];
            }
        }

        if ($key === 'email_unread')
        {
            $parsed = PerformanceDigestReplyParser::parseEmailSuggestion($suggestion);
            $recipient = $this->firstScheduleRecipientFromMessages($messages, 'email');

            if ($recipient !== null)
            {
                return [
                    'schedule_action' => 'email',
                    'schedule_recipient' => $recipient,
                    'schedule_subject' => $parsed['subject'] !== '' ? $parsed['subject'] : null,
                    'action_url' => null,
                    'action_label' => (string) __('app.performance_digest_schedule_email'),
                ];
            }
        }

        if (in_array($key, ['whatsapp_unread', 'whatsapp_inbound'], true))
        {
            $recipient = $this->firstScheduleRecipientFromMessages($messages, 'whatsapp');

            if ($recipient !== null)
            {
                return [
                    'schedule_action' => 'whatsapp',
                    'schedule_recipient' => $recipient,
                    'schedule_subject' => null,
                    'action_url' => null,
                    'action_label' => (string) __('app.performance_digest_schedule_whatsapp'),
                ];
            }
        }

        return [
            'schedule_action' => null,
            'schedule_recipient' => '',
            'schedule_subject' => null,
            'action_url' => $this->actionUrlForKey($key),
            'action_label' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function firstScheduleRecipientFromMessages(array $messages, string $channel): ?string
    {
        foreach ($messages as $message)
        {
            if (($message['schedule_action'] ?? null) !== $channel)
            {
                continue;
            }

            $recipient = trim((string) ($message['schedule_recipient'] ?? ''));
            if ($recipient !== '')
            {
                return $recipient;
            }
        }

        return null;
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
