<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\UserContactAction;
use App\Services\Finance\PaymentSummaryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class DailyTeamDigestMetricsCollector
{
    public const DIGEST_VERSION = 3;

    /**
     * Team-wide operational digest plus user activity (calls, interactions, tasks).
     *
     * @return array<string, mixed>
     */
    public function collect(User $user, Team $team, ?CarbonInterface $onDate = null): array
    {
        $anchor = ($onDate ?? now())->copy();
        $since7d = $anchor->copy()->subDays(7)->startOfDay();
        $since24h = $anchor->copy()->subDay();
        $todayStart = $anchor->copy()->startOfDay();
        $todayEnd = $anchor->copy()->endOfDay();
        $weekAhead = $anchor->copy()->addDays(7)->endOfDay();

        $digest = [
            'digest_version' => self::DIGEST_VERSION,
            'insight_date' => $anchor->toDateString(),
            'user_activity' => $this->collectUserActivity($user, $team, $since7d),
        ];

        if ($team->hasModule('chat'))
        {
            $digest['whatsapp'] = $this->collectWhatsAppMetrics($team, $since7d, $since24h);
        }

        if ($team->hasModule('mailbox'))
        {
            $digest['email'] = $this->collectEmailMetrics($team, $since7d, $since24h);
        }

        if ($team->hasModule('calendar'))
        {
            $digest['appointments'] = $this->collectAppointmentMetrics($team, $todayStart, $todayEnd, $weekAhead);
        }

        if ($team->hasModule('contacts'))
        {
            $digest['client_sentiment'] = $this->collectClientSentimentMetrics($team, $since7d);
        }

        if ($team->hasModule('tasks'))
        {
            $digest['tasks'] = $this->collectTaskMetrics($user, $team, $since7d, $todayStart);
        }

        if ($team->hasModule('projects'))
        {
            $digest['projects'] = $this->collectProjectMetrics($user, $team, $weekAhead);
        }

        if ($team->hasModule('services'))
        {
            $digest['services'] = $this->collectServiceMetrics($team, $todayStart, $weekAhead);
        }

        if ($team->hasModule('invoices'))
        {
            $digest['invoices'] = $this->collectInvoiceMetrics($team, $todayStart);
        }

        if ($team->hasModule('payments'))
        {
            $digest['payments'] = $this->collectPaymentMetrics($team);
        }

        $digest['highlight_items'] = $this->buildHighlightItems($digest, $team);
        $digest['highlights'] = array_column($digest['highlight_items'], 'label');

        return $digest;
    }

    /**
     * @return array{interactions_count: int, call_minutes: float, tasks_done: int}
     */
    private function collectUserActivity(User $user, Team $team, CarbonInterface $since7d): array
    {
        $interactionsCount = ContactInteraction::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $since7d)
            ->whereHas('contact', function ($q) use ($team): void
            {
                $q->where('team_id', $team->id);
            })
            ->count();

        $seconds = (int) UserContactAction::query()
            ->where('user_id', $user->id)
            ->where('start_time', '>=', $since7d)
            ->whereHas('contact', function ($q) use ($team): void
            {
                $q->where('team_id', $team->id);
            })
            ->sum('duration_seconds');

        $doneStatusId = TaskStatus::query()->where('name', 'DONE')->value('id');
        $tasksDone = 0;
        if ($doneStatusId)
        {
            $tasksDone = (int) Task::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('responsible_id', $user->id)
                ->where('status_id', $doneStatusId)
                ->where('updated_at', '>=', $since7d)
                ->count();
        }

        return [
            'interactions_count' => $interactionsCount,
            'call_minutes' => max(0, $seconds / 60),
            'tasks_done' => $tasksDone,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectWhatsAppMetrics(Team $team, CarbonInterface $since7d, CarbonInterface $since24h): array
    {
        $base = $this->whatsappConversationQuery($team);

        return [
            'inbound_24h' => (clone $base)->where('direction', 'inbound')->where('created_at', '>=', $since24h)->count(),
            'inbound_7d' => (clone $base)->where('direction', 'inbound')->where('created_at', '>=', $since7d)->count(),
            'unread_inbound' => (clone $base)->where('direction', 'inbound')->where('status', 'received')->count(),
            'outbound_7d' => (clone $base)->where('direction', 'outbound')->where('created_at', '>=', $since7d)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectEmailMetrics(Team $team, CarbonInterface $since7d, CarbonInterface $since24h): array
    {
        $base = Email::query()->where('team_id', $team->id);

        return [
            'received_24h' => (clone $base)->where('message_date', '>=', $since24h)->count(),
            'received_7d' => (clone $base)->where('message_date', '>=', $since7d)->count(),
            'unread' => (clone $base)->where('seen', false)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectAppointmentMetrics(Team $team, CarbonInterface $todayStart, CarbonInterface $todayEnd, CarbonInterface $weekAhead): array
    {
        $base = CalendarEvent::withoutGlobalScopes()->where('team_id', $team->id);

        return [
            'today' => (clone $base)->whereBetween('start', [$todayStart, $todayEnd])->count(),
            'next_7_days' => (clone $base)->where('start', '>', $todayEnd)->where('start', '<=', $weekAhead)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectClientSentimentMetrics(Team $team, CarbonInterface $since7d): array
    {
        $negativeIds = [1, 2];

        $stressedContacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereHas('currentSentiment', function ($q) use ($negativeIds): void
            {
                $q->whereIn('sentiment_id', $negativeIds);
            })
            ->count();

        $sentimentUpdates7d = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereHas('sentimentHistories', function ($q) use ($since7d): void
            {
                $q->where('created_at', '>=', $since7d);
            })
            ->count();

        return [
            'stressed_contacts' => $stressedContacts,
            'sentiment_updates_7d' => $sentimentUpdates7d,
        ];
    }

    /**
     * @return array{pending: int, overdue: int, due_today: int, done_7d: int, daily_items: list<array{id: int, title: string, due_date: ?string, due_label: string, is_overdue: bool, is_due_today: bool, status: string}>}
     */
    private function collectTaskMetrics(User $user, Team $team, CarbonInterface $since7d, CarbonInterface $todayStart): array
    {
        $todayEnd = $todayStart->copy()->endOfDay();
        $doneStatusId = TaskStatus::query()->where('name', 'DONE')->value('id');

        $base = Task::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $user->id);

        $openQuery = $doneStatusId
            ? (clone $base)->where('status_id', '!=', $doneStatusId)
            : clone $base;

        $pending = (clone $openQuery)->count();

        $overdue = $doneStatusId
            ? (clone $openQuery)->whereNotNull('due_date')->whereDate('due_date', '<', $todayStart)->count()
            : 0;

        $dueToday = (clone $openQuery)
            ->whereNotNull('due_date')
            ->whereDate('due_date', $todayStart)
            ->count();

        $done7d = $doneStatusId
            ? (clone $base)->where('status_id', $doneStatusId)->where('updated_at', '>=', $since7d)->count()
            : 0;

        return [
            'pending' => $pending,
            'overdue' => $overdue,
            'due_today' => $dueToday,
            'done_7d' => $done7d,
            'daily_items' => $this->collectDailyTaskItems($openQuery, $todayStart, $todayEnd),
        ];
    }

    /**
     * Open tasks relevant for the digest day: overdue, due today, or without due date.
     *
     * @return list<array{id: int, title: string, due_date: ?string, due_label: string, is_overdue: bool, is_due_today: bool, status: string}>
     */
    private function collectDailyTaskItems(Builder $openQuery, CarbonInterface $todayStart, CarbonInterface $todayEnd): array
    {
        $tasks = (clone $openQuery)
            ->with('status:id,name')
            ->where(function ($query) use ($todayEnd): void
            {
                $query->whereNull('due_date')
                    ->orWhereDate('due_date', '<=', $todayEnd);
            })
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('title')
            ->limit(20)
            ->get(['id', 'title', 'due_date', 'status_id']);

        $items = [];
        foreach ($tasks as $task)
        {
            $dueDate = $task->due_date;
            $isOverdue = $dueDate !== null && $dueDate->lt($todayStart);
            $isDueToday = $dueDate !== null && $dueDate->between($todayStart, $todayEnd);

            $items[] = [
                'id' => (int) $task->id,
                'title' => (string) $task->title,
                'due_date' => $dueDate?->toDateString(),
                'due_label' => $dueDate !== null
                    ? $dueDate->isoFormat('D MMM YYYY')
                    : (string) __('app.performance_digest_task_no_due_date'),
                'is_overdue' => $isOverdue,
                'is_due_today' => $isDueToday,
                'status' => (string) ($task->status?->translated_name ?? __('task_status.UNKNOWN')),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, int>
     */
    private function collectProjectMetrics(User $user, Team $team, CarbonInterface $weekAhead): array
    {
        $activeStatuses = [3, 7, 8, 9];

        $base = Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $user->id);

        return [
            'active' => (clone $base)->whereIn('status_id', $activeStatuses)->count(),
            'ending_this_week' => (clone $base)->whereIn('status_id', $activeStatuses)
                ->whereNotNull('date_end')
                ->whereDate('date_end', '<=', $weekAhead)
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectServiceMetrics(Team $team, CarbonInterface $todayStart, CarbonInterface $weekAhead): array
    {
        $base = Service::withoutGlobalScopes()
            ->whereHas('client', fn ($q) => $q->where('team_id', $team->id));

        return [
            'billing_due_7d' => (clone $base)->whereNotNull('next_billing')
                ->whereDate('next_billing', '>=', $todayStart)
                ->whereDate('next_billing', '<=', $weekAhead)
                ->count(),
            'expiring_7d' => (clone $base)->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', $todayStart)
                ->whereDate('expires_at', '<=', $weekAhead)
                ->count(),
            'needs_attention' => (clone $base)->where('status', '<', 4)->count(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function collectInvoiceMetrics(Team $team, CarbonInterface $todayStart): array
    {
        $base = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('balance', '>', 0);

        return [
            'unpaid_count' => (clone $base)->count(),
            'unpaid_balance_total' => round((float) (clone $base)->sum('balance'), 2),
            'overdue_count' => (clone $base)->whereNotNull('due_date')->whereDate('due_date', '<', $todayStart)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectPaymentMetrics(Team $team): array
    {
        $summary = app(PaymentSummaryService::class)->forTeam($team);

        return [
            'pending_claim_count' => $summary['pending_claim_count'],
        ];
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return list<array{key: string, label: string, count: int}>
     */
    public function buildHighlightItems(array $digest, Team $team): array
    {
        $items = [];

        if (isset($digest['whatsapp']))
        {
            $w = $digest['whatsapp'];
            if (($w['unread_inbound'] ?? 0) > 0)
            {
                $items[] = $this->highlightItem('whatsapp_unread', (int) $w['unread_inbound']);
            } elseif (($w['inbound_24h'] ?? 0) > 0)
            {
                $items[] = $this->highlightItem('whatsapp_inbound', (int) $w['inbound_24h']);
            }
        }

        if (isset($digest['email']) && ($digest['email']['unread'] ?? 0) > 0)
        {
            $items[] = $this->highlightItem('email_unread', (int) $digest['email']['unread']);
        }

        if (isset($digest['appointments']) && ($digest['appointments']['today'] ?? 0) > 0)
        {
            $items[] = $this->highlightItem('appointments_today', (int) $digest['appointments']['today']);
        }

        if (isset($digest['client_sentiment']) && ($digest['client_sentiment']['stressed_contacts'] ?? 0) > 0)
        {
            $items[] = $this->highlightItem('stressed_clients', (int) $digest['client_sentiment']['stressed_contacts']);
        }

        if (isset($digest['tasks']))
        {
            if (($digest['tasks']['overdue'] ?? 0) > 0)
            {
                $items[] = $this->highlightItem('tasks_overdue', (int) $digest['tasks']['overdue']);
            }
            if (($digest['tasks']['due_today'] ?? 0) > 0)
            {
                $items[] = $this->highlightItem('tasks_due_today', (int) $digest['tasks']['due_today']);
            }
            if (($digest['tasks']['pending'] ?? 0) > 0)
            {
                $items[] = $this->highlightItem('tasks_pending', (int) $digest['tasks']['pending']);
            }
        }

        if (isset($digest['projects']) && ($digest['projects']['ending_this_week'] ?? 0) > 0)
        {
            $items[] = $this->highlightItem('projects_due', (int) $digest['projects']['ending_this_week']);
        }

        if (isset($digest['services']) && (($digest['services']['billing_due_7d'] ?? 0) + ($digest['services']['needs_attention'] ?? 0)) > 0)
        {
            $items[] = $this->highlightItem('services_action', (int) (($digest['services']['billing_due_7d'] ?? 0) + ($digest['services']['needs_attention'] ?? 0)));
        }

        if (isset($digest['invoices']) && ($digest['invoices']['overdue_count'] ?? 0) > 0)
        {
            $items[] = $this->highlightItem('invoices_overdue', (int) $digest['invoices']['overdue_count']);
        } elseif (isset($digest['invoices']) && ($digest['invoices']['unpaid_count'] ?? 0) > 0)
        {
            $items[] = $this->highlightItem('invoices_unpaid', (int) $digest['invoices']['unpaid_count']);
        }

        if (isset($digest['payments']) && ($digest['payments']['pending_claim_count'] ?? 0) > 0)
        {
            $items[] = $this->highlightItem('payments_claim', (int) $digest['payments']['pending_claim_count']);
        }

        $activity = $digest['user_activity'] ?? [];
        if ($items === [] && ((int) ($activity['interactions_count'] ?? 0)) === 0)
        {
            $items[] = $this->highlightItem('quiet_day', 0);
        }

        return array_slice($items, 0, 12);
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return list<string>
     */
    public function buildHighlights(array $digest, Team $team): array
    {
        return array_column($this->buildHighlightItems($digest, $team), 'label');
    }

    /**
     * @return array{key: string, label: string, count: int}
     */
    private function highlightItem(string $key, int $count): array
    {
        return [
            'key' => $key,
            'label' => $key === 'quiet_day'
                ? (string) __('app.performance_digest_highlight_quiet_day')
                : (string) trans_choice('app.performance_digest_highlight_'.$key, $count, ['count' => $count]),
            'count' => $count,
        ];
    }

    public function whatsappConversationQueryForTeam(Team $team): Builder
    {
        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());

        $query = Conversation::query()->where('channel', 'whatsapp');

        if ($teamNumber !== '')
        {
            $query->where(function ($q) use ($teamNumber): void
            {
                $q->where('from', $teamNumber)
                    ->orWhere('to', $teamNumber)
                    ->orWhere('from', 'like', $teamNumber.':%')
                    ->orWhere('to', 'like', $teamNumber.':%');
            });
        }

        return $query;
    }

    private function whatsappConversationQuery(Team $team): Builder
    {
        return $this->whatsappConversationQueryForTeam($team);
    }
}
