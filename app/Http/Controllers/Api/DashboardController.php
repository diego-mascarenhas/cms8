<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdPlatform;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactIntent;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\List60;
use App\Models\Notification;
use App\Models\PaidAdMetricSnapshot;
use App\Models\PaymentSync;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\ServiceSync;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\TeamUsageInvoice;
use App\Models\Time;
use App\Models\User;
use App\Services\ContactDailySentimentService;
use App\Services\DashboardAnalyticsService;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Services\Finance\VatHaciendaCsvExportService;
use App\Services\Mail\CampaignMessageApiService;
use App\Services\Mail\MailInboxService;
use App\Support\ApplicationDateTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(
        private MailInboxService $mailInbox,
        private CampaignMessageApiService $campaignMessages,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $payload = [
            'success' => true,
            'contact_stats' => null,
            'running_task' => null,
            'today' => [
                'date' => $today->toDateString(),
                'events' => [],
                'tasks' => [],
                'events_count' => 0,
                'tasks_count' => 0,
            ],
            'notifications' => ['unread_count' => 0],
            'mail' => null,
            'chat' => null,
            'today_contacts' => null,
        ];

        if ($user->can('viewAny', Contact::class))
        {
            $payload['contact_stats'] = Contact::getContactStats($team->id);
        }

        $runningTimer = Time::getRunningTimer($user->id);
        if ($runningTimer)
        {
            $runningTimer->load('task', 'task.status', 'task.project');
            $task = $runningTimer->task;
            $payload['running_task'] = [
                'time_id' => $runningTimer->id,
                'task_id' => $runningTimer->task_id,
                'start_time' => $runningTimer->start_time?->toIso8601String(),
                'elapsed_seconds' => $runningTimer->start_time
                    ? now()->diffInSeconds($runningTimer->start_time)
                    : 0,
                'task' => $task ? [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status?->translated_name,
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                    ] : null,
                ] : null,
            ];
        }

        if ($team->hasModule('today') || $team->hasModule('calendar'))
        {
            $events = CalendarEvent::query()
                ->where('end', '>', $today)
                ->where('start', '<', $tomorrow)
                ->orderBy('start')
                ->limit(8)
                ->get()
                ->map(fn (CalendarEvent $event) => $this->formatCalendarEvent($event))
                ->values()
                ->all();

            $tasksQuery = Task::query()
                ->with(['status'])
                ->where(function ($query) use ($today)
                {
                    $query->whereDate('due_date', $today)
                        ->orWhereDate('start_date', $today)
                        ->orWhere(function ($inner) use ($today)
                        {
                            $inner->whereDate('start_date', '<=', $today)
                                ->whereDate('due_date', '>=', $today);
                        });
                });

            if (! $user->hasRole('admin'))
            {
                $tasksQuery->where('responsible_id', $user->id);
            }

            $tasks = $tasksQuery
                ->orderBy('due_date')
                ->limit(8)
                ->get()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => [
                        'translated_name' => $task->status?->translated_name,
                        'color' => $task->status?->color,
                    ],
                ])
                ->values()
                ->all();

            $payload['today'] = [
                'date' => $today->toDateString(),
                'events' => $events,
                'tasks' => $tasks,
                'events_count' => count($events),
                'tasks_count' => count($tasks),
            ];
        }

        $payload['notifications']['unread_count'] = Notification::query()
            ->forRecipientUser($user->id)
            ->unread()
            ->count();

        if ($team->hasModule('mailer'))
        {
            $counts = $this->mailInbox->folderCounts($team);
            $payload['mail'] = [
                'inbox_unread' => $counts['inbox_unread'] ?? 0,
                'inbox_total' => $counts['inbox'] ?? 0,
            ];
        }

        if ($team->hasModule('chat'))
        {
            $cachedUnread = \Illuminate\Support\Facades\Cache::get(
                'inbound_received_count_team_'.$team->id,
            );
            if (is_numeric($cachedUnread))
            {
                $payload['chat'] = ['unread_messages' => (int) $cachedUnread];
            }
        }

        if ($team->hasModule('list60') && $user->can('viewAny', Contact::class))
        {
            $payload['today_contacts'] = $this->todayContactsPayload($user, $team->id, $today);
        }

        $payload['widgets'] = $this->widgets($user, $team);
        $payload['follow_up'] = $this->followUpPayload($user, $team, $today);

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function widgets(User $user, Team $team): array
    {
        return [
            'projects' => $this->projectWidgets($team),
            'mailer' => $this->mailerWidget($team),
            'meetings' => $this->meetingWidgets($team),
            'ads' => $this->adsWidget($team),
            'income' => $this->incomeWidget($team),
            'analytics' => app(DashboardAnalyticsService::class)->forTeam($team),
            'expiring' => $this->expiringWidgets($team),
            'payments' => $this->paymentWidgets($team),
            'drafts' => $this->draftWidgets($user),
            'sentiment' => $this->sentimentWidget($team),
            'audience' => $this->audienceWidgets($user, $team),
            'intents' => $this->intentWidgets($user, $team),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectWidgets(Team $team): array
    {
        if (! $team->hasModule('projects'))
        {
            return [];
        }

        $projects = Project::query()
            ->with(['client:id,name', 'status:id,name'])
            ->where('team_id', $team->id)
            ->whereIn('status_id', ProjectStatus::ongoingDashboardStatusIds())
            ->orderByRaw('CASE status_id WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', [
                ProjectStatus::STATUS_IN_PROGRESS,
                ProjectStatus::STATUS_APPROVED,
                ProjectStatus::STATUS_AUTHORIZED,
            ])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        $this->attachProjectMetrics($projects, $team->id);

        return $projects->map(function (Project $project): array
        {
            $start = $project->date_start?->toDateString();
            $end = $project->date_end?->toDateString();
            if ($start && ! $end)
            {
                $end = $project->date_start->copy()->addDays(14)->toDateString();
            }
            if ($end && ! $start)
            {
                $start = $project->date_end->copy()->subDays(14)->toDateString();
            }
            if (! $start && ! $end && $project->updated_at)
            {
                $start = $project->updated_at->toDateString();
                $end = $project->updated_at->copy()->addDays(3)->toDateString();
            }

            $open = (int) $project->getAttribute('dashboard_open_tasks');
            $total = (int) $project->getAttribute('dashboard_total_tasks');

            return [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->name,
                'status' => $project->status?->translated_name,
                'open_tasks' => $open,
                'total_tasks' => $total,
                'worked_hours' => (float) $project->getAttribute('dashboard_worked_hours'),
                'estimated_hours' => (float) $project->getAttribute('dashboard_estimated_hours'),
                'progress' => $total > 0 ? (int) round((($total - $open) / $total) * 100) : 0,
                'start' => $start,
                'end' => $end,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function attachProjectMetrics(Collection $projects, int $teamId): void
    {
        $boardIds = $projects->pluck('board_id')->filter()->unique()->values();
        $statsByBoard = [];

        foreach ($boardIds as $boardId)
        {
            $statsByBoard[(int) $boardId] = [
                'worked_hours' => 0.0,
                'estimated_hours' => 0.0,
                'open_tasks' => 0,
                'total_tasks' => 0,
            ];
        }

        if ($boardIds->isNotEmpty())
        {
            $doneStatusId = TaskStatus::query()->where('name', 'DONE')->value('id');
            $taskRows = Task::query()
                ->where('team_id', $teamId)
                ->whereIn('board_id', $boardIds)
                ->get(['id', 'board_id', 'status_id', 'estimated_hours']);

            $taskIdsByBoard = [];
            foreach ($taskRows as $task)
            {
                $boardId = (int) $task->board_id;
                $statsByBoard[$boardId]['total_tasks']++;
                $statsByBoard[$boardId]['estimated_hours'] += (float) ($task->estimated_hours ?? 0);
                if ($doneStatusId === null || (int) $task->status_id !== (int) $doneStatusId)
                {
                    $statsByBoard[$boardId]['open_tasks']++;
                }
                $taskIdsByBoard[$boardId][] = (int) $task->id;
            }

            $allTaskIds = $taskRows->pluck('id')->all();
            if ($allTaskIds !== [])
            {
                $secondsByTask = Time::query()
                    ->where('team_id', $teamId)
                    ->whereIn('task_id', $allTaskIds)
                    ->selectRaw('task_id, SUM(duration_seconds) as total_seconds')
                    ->groupBy('task_id')
                    ->pluck('total_seconds', 'task_id');

                foreach ($taskIdsByBoard as $boardId => $taskIds)
                {
                    $seconds = 0;
                    foreach ($taskIds as $taskId)
                    {
                        $seconds += (int) ($secondsByTask[$taskId] ?? 0);
                    }
                    $statsByBoard[$boardId]['worked_hours'] = round($seconds / 3600, 1);
                }
            }
        }

        foreach ($projects as $project)
        {
            $stats = $project->board_id ? ($statsByBoard[(int) $project->board_id] ?? null) : null;
            $project->setAttribute('dashboard_worked_hours', (float) ($stats['worked_hours'] ?? 0));
            $project->setAttribute('dashboard_estimated_hours', round((float) ($stats['estimated_hours'] ?? 0), 1));
            $project->setAttribute('dashboard_open_tasks', (int) ($stats['open_tasks'] ?? 0));
            $project->setAttribute('dashboard_total_tasks', (int) ($stats['total_tasks'] ?? 0));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mailerWidget(Team $team): array
    {
        $empty = $this->emptyWeek();
        if (! $team->hasModule('mailer'))
        {
            return $empty;
        }

        $dashboard = $this->campaignMessages->dashboard($team, 90);
        $weeks = array_slice($dashboard['activity'] ?? [], -8);
        $labels = [];
        $sent = [];
        $opened = [];
        $clicks = [];

        $dates = [];

        foreach ($weeks as $week)
        {
            $start = Carbon::parse($week['date']);
            $labels[] = (string) $start->isoWeek();
            $dates[] = $start->day.'/'.$start->month;
            $sent[] = (int) ($week['sent'] ?? 0);
            $opened[] = (int) ($week['opened'] ?? 0);
            $clicks[] = (int) ($week['clicks'] ?? 0);
        }

        if ($labels === [])
        {
            return $empty;
        }

        $sentTotal = array_sum($sent);
        $openedTotal = array_sum($opened);

        return [
            'labels' => $labels,
            'dates' => $dates,
            'sent' => $sent,
            'opened' => $opened,
            'open_rate' => $sentTotal > 0 ? (int) round(($openedTotal / $sentTotal) * 100) : 0,
            'sent_total' => $sentTotal,
            'opened_total' => $openedTotal,
            'clicks_total' => array_sum($clicks),
            'period_label' => 'Últimas 8 semanas',
        ];
    }

    /**
     * @return array{labels: list<string>, sent: list<int>, opened: list<int>, open_rate: int, sent_total: int, opened_total: int, clicks_total: int}
     */
    private function emptyWeek(): array
    {
        $labels = [];
        $sent = [];
        $opened = [];

        for ($i = 6; $i >= 0; $i--)
        {
            $labels[] = $this->weekdayLabel(now()->subDays($i));
            $sent[] = 0;
            $opened[] = 0;
        }

        return [
            'labels' => $labels,
            'sent' => $sent,
            'opened' => $opened,
            'open_rate' => 0,
            'sent_total' => 0,
            'opened_total' => 0,
            'clicks_total' => 0,
            'period_label' => 'Últimos 7 días',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function meetingWidgets(Team $team): array
    {
        if (! $team->hasModule('calendar') && ! $team->hasModule('today'))
        {
            return [];
        }

        $upcoming = CalendarEvent::query()
            ->where('end', '>', now())
            ->where('start', '<=', now()->addDays(30)->endOfDay())
            ->orderBy('start')
            ->limit(6)
            ->get();

        $events = $upcoming->isNotEmpty()
            ? $upcoming
            : CalendarEvent::query()->orderByDesc('start')->limit(6)->get()->sortBy('start')->values();

        return $events
            ->map(function (CalendarEvent $event): array
            {
                $start = $event->start?->copy()->timezone('Europe/Madrid');
                $end = $event->end?->copy()->timezone('Europe/Madrid');
                $when = $start?->format('d/m | H:i') ?? '';
                if ($end && $start && ! $start->isSameDay($end))
                {
                    $when .= '–'.$end->format('d/m H:i');
                } elseif ($end)
                {
                    $when .= '–'.$end->format('H:i');
                }

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'when' => $when,
                    'label' => $event->label ?: ($event->location ?: 'Reunión'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{labels: list<string>, amounts: list<float>, expenses: list<float>, total: float, expense_total: float, currency: string, period_label: string}
     */
    private function incomeWidget(Team $team): array
    {
        $currency = app(PaymentReportingCurrencyService::class)->reportingCurrencyForTeam($team);
        $year = (int) now()->year;
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();
        $monthlyTotals = app(VatHaciendaCsvExportService::class)->monthlyBookTotals(
            (int) $team->id,
            $from,
            $to,
            $currency,
        )['months'];

        $labels = [];
        $amounts = [];
        $expenses = [];
        $total = 0.0;
        $expenseTotal = 0.0;

        for ($month = 1; $month <= 12; $month++)
        {
            $date = Carbon::create($year, $month, 1);
            $income = (float) ($monthlyTotals[$month]['income'] ?? 0);
            $expense = (float) ($monthlyTotals[$month]['expense'] ?? 0);
            $labels[] = $this->monthLabel($date);
            $amounts[] = round($income, 2);
            $expenses[] = round($expense, 2);
            $total += $income;
            $expenseTotal += $expense;
        }

        return [
            'labels' => $labels,
            'amounts' => $amounts,
            'expenses' => $expenses,
            'total' => round($total, 2),
            'expense_total' => round($expenseTotal, 2),
            'currency' => $currency,
            'period_label' => (string) $year,
        ];
    }

    private function monthLabel(Carbon $month): string
    {
        return ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][$month->month - 1];
    }

    /**
     * @return array<string, mixed>
     */
    private function adsWidget(Team $team): array
    {
        $labels = [];
        $clicks = [];
        $spend = [];
        for ($i = 6; $i >= 0; $i--)
        {
            $labels[] = $this->weekdayLabel(now()->subDays($i));
            $clicks[] = 0;
            $spend[] = 0;
        }

        $rows = [
            ['title' => 'Clics', 'value' => '0', 'trend' => 'up', 'trend_number' => '0%'],
            ['title' => 'Impresiones', 'value' => '0', 'trend' => 'up', 'trend_number' => '0%'],
            ['title' => 'CTR', 'value' => '0%', 'trend' => 'up', 'trend_number' => '0%'],
            ['title' => 'Gasto', 'value' => '0,00 €', 'trend' => 'up', 'trend_number' => '0%'],
        ];

        if (! $team->hasModule('paid_ads'))
        {
            return compact('labels', 'clicks', 'spend', 'rows');
        }

        $currentStart = now()->subDays(6)->toDateString();
        $previousStart = now()->subDays(13)->toDateString();
        $previousEnd = now()->subDays(7)->toDateString();

        $daily = $this->googleAdsByDay($team->id, $currentStart, now()->toDateString());
        $previous = $this->googleAdsTotals($team->id, $previousStart, $previousEnd);
        $current = ['clicks' => 0, 'impressions' => 0, 'spend' => 0.0];

        for ($i = 6; $i >= 0; $i--)
        {
            $key = now()->subDays($i)->toDateString();
            $index = 6 - $i;
            $clicks[$index] = (int) ($daily[$key]['clicks'] ?? 0);
            $spend[$index] = round((float) ($daily[$key]['spend'] ?? 0), 2);
            $current['clicks'] += $clicks[$index];
            $current['impressions'] += (int) ($daily[$key]['impressions'] ?? 0);
            $current['spend'] += $spend[$index];
        }

        $ctr = $current['impressions'] > 0
            ? round(($current['clicks'] / $current['impressions']) * 100, 1)
            : 0.0;
        $clickTrend = $this->trendPercent($current['clicks'], (int) $previous['clicks']);

        $rows = [
            ['title' => 'Clics', 'value' => number_format($current['clicks'], 0, ',', '.'), 'trend' => $clickTrend >= 0 ? 'up' : 'down', 'trend_number' => abs($clickTrend).'%'],
            ['title' => 'Impresiones', 'value' => number_format($current['impressions'], 0, ',', '.'), 'trend' => 'up', 'trend_number' => ''],
            ['title' => 'CTR', 'value' => str_replace('.', ',', (string) $ctr).'%', 'trend' => 'up', 'trend_number' => ''],
            ['title' => 'Gasto', 'value' => number_format($current['spend'], 2, ',', '.').' €', 'trend' => 'up', 'trend_number' => ''],
        ];

        return compact('labels', 'clicks', 'spend', 'rows');
    }

    /**
     * @return array<string, array{clicks: int, impressions: int, spend: float}>
     */
    private function googleAdsByDay(int $teamId, string $from, string $to): array
    {
        $indexed = [];
        $query = PaidAdMetricSnapshot::query()
            ->join('paid_ad_campaign_platforms', 'paid_ad_campaign_platforms.id', '=', 'paid_ad_metric_snapshots.paid_ad_campaign_platform_id')
            ->join('paid_ad_campaigns', 'paid_ad_campaigns.id', '=', 'paid_ad_campaign_platforms.paid_ad_campaign_id')
            ->where('paid_ad_campaigns.team_id', $teamId)
            ->where('paid_ad_campaign_platforms.platform', AdPlatform::GoogleAds->value)
            ->whereBetween('paid_ad_metric_snapshots.date', [$from, $to])
            ->groupBy('paid_ad_metric_snapshots.date')
            ->selectRaw('paid_ad_metric_snapshots.date as day, COALESCE(SUM(paid_ad_metric_snapshots.clicks),0) as clicks, COALESCE(SUM(paid_ad_metric_snapshots.impressions),0) as impressions, COALESCE(SUM(paid_ad_metric_snapshots.spend),0) as spend')
            ->get();

        foreach ($query as $row)
        {
            $indexed[substr((string) $row->day, 0, 10)] = [
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'spend' => (float) $row->spend,
            ];
        }

        return $indexed;
    }

    /**
     * @return array{clicks: int, impressions: int, spend: float}
     */
    private function googleAdsTotals(int $teamId, string $from, string $to): array
    {
        $row = PaidAdMetricSnapshot::query()
            ->join('paid_ad_campaign_platforms', 'paid_ad_campaign_platforms.id', '=', 'paid_ad_metric_snapshots.paid_ad_campaign_platform_id')
            ->join('paid_ad_campaigns', 'paid_ad_campaigns.id', '=', 'paid_ad_campaign_platforms.paid_ad_campaign_id')
            ->where('paid_ad_campaigns.team_id', $teamId)
            ->where('paid_ad_campaign_platforms.platform', AdPlatform::GoogleAds->value)
            ->whereBetween('paid_ad_metric_snapshots.date', [$from, $to])
            ->selectRaw('COALESCE(SUM(paid_ad_metric_snapshots.clicks),0) as clicks, COALESCE(SUM(paid_ad_metric_snapshots.impressions),0) as impressions, COALESCE(SUM(paid_ad_metric_snapshots.spend),0) as spend')
            ->first();

        return [
            'clicks' => (int) ($row->clicks ?? 0),
            'impressions' => (int) ($row->impressions ?? 0),
            'spend' => (float) ($row->spend ?? 0),
        ];
    }

    private function trendPercent(int $current, int $previous): int
    {
        if ($previous === 0)
        {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    /**
     * Contacts grouped by status: leads, follow-up, clients and the rest.
     *
     * @return list<array{name: string, count: int, label_class: string}>
     */
    private function audienceWidgets(User $user, Team $team): array
    {
        if (! $user->can('viewAny', Contact::class))
        {
            return [];
        }

        $counts = Contact::query()
            ->where('team_id', $team->id)
            ->whereNotNull('status_id')
            ->selectRaw('status_id, COUNT(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');

        $order = array_flip(['Lead', 'En seguimiento', 'Conversión', 'Cliente', 'Perdido', 'Finalizado']);

        return ContactStatus::query()
            ->orderBy('id')
            ->get(['id', 'name', 'label_class'])
            ->map(function (ContactStatus $status) use ($counts): array
            {
                return [
                    'name' => $status->name,
                    'count' => (int) ($counts[$status->id] ?? 0),
                    'label_class' => (string) $status->label_class,
                ];
            })
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortBy(fn (array $row): int => $order[$row['name']] ?? 99)
            ->values()
            ->all();
    }

    /**
     * Latest purchase intent of each contact.
     *
     * @return list<array{key: string, name: string, emoji: string, count: int}>
     */
    private function intentWidgets(User $user, Team $team): array
    {
        if (! $user->can('viewAny', Contact::class))
        {
            return [];
        }

        $latest = ContactSentimentHistory::query()
            ->selectRaw('contact_id, MAX(id) as id')
            ->groupBy('contact_id');

        $counts = ContactSentimentHistory::query()
            ->joinSub($latest, 'latest', function ($join): void
            {
                $join->on('latest.id', '=', 'contact_sentiment_histories.id');
            })
            ->join('contacts', 'contacts.id', '=', 'contact_sentiment_histories.contact_id')
            ->where('contacts.team_id', $team->id)
            ->whereNotNull('contact_sentiment_histories.intent_id')
            ->selectRaw('contact_sentiment_histories.intent_id as intent_id, COUNT(*) as aggregate')
            ->groupBy('contact_sentiment_histories.intent_id')
            ->pluck('aggregate', 'intent_id');

        return ContactIntent::query()
            ->orderBy('id')
            ->get()
            ->map(function (ContactIntent $intent) use ($counts): array
            {
                return [
                    'key' => $intent->key,
                    'name' => $intent->displayName(),
                    'emoji' => $intent->emoji,
                    'count' => (int) ($counts[(int) $intent->id] ?? $counts[(string) $intent->id] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Latest card on each Stripe customer that expires within 60 days.
     *
     * @return list<array{id: int, subscription_id: int|null, customer_name: string, brand: string, funding: string, last4: string, exp_month: int, exp_year: int}>
     */
    private function expiringWidgets(Team $team): array
    {
        $from = now()->startOfDay();
        $until = now()->addDays(60)->endOfDay();
        $latestByCustomer = [];
        $seenCustomers = [];

        foreach ($this->latestCardCharges($team) as $charge)
        {
            $customerId = trim((string) $charge->customer_id);
            $key = $customerId !== '' ? $customerId : 'charge-'.$charge->id;
            if (isset($seenCustomers[$key]))
            {
                continue;
            }

            $seenCustomers[$key] = true;
            $month = (int) $charge->card_exp_month;
            $year = (int) $charge->card_exp_year;
            if ($month < 1 || $month > 12 || $year < 1)
            {
                continue;
            }

            $expires = Carbon::create($year, $month, 1)->endOfMonth();
            if ($expires->lt($from) || $expires->gt($until))
            {
                continue;
            }

            $latestByCustomer[$key] = [
                'charge' => $charge,
                'month' => $month,
                'year' => $year,
            ];
        }

        $customerIds = collect($latestByCustomer)
            ->map(fn (array $row): string => trim((string) $row['charge']->customer_id))
            ->filter()
            ->values();
        $subscriptions = ServiceSync::query()
            ->where('team_id', $team->id)
            ->whereIn('customer_id', $customerIds)
            ->orderByDesc('id')
            ->get(['id', 'customer_id', 'customer_name'])
            ->groupBy('customer_id');

        $rows = [];
        foreach ($latestByCustomer as $row)
        {
            $charge = $row['charge'];
            $subscription = $subscriptions->get($charge->customer_id)?->first();
            $rows[] = [
                'id' => (int) $charge->id,
                'subscription_id' => $subscription?->id,
                'customer_name' => $subscription?->customer_name
                    ?: ($charge->customer_email ?: 'Cliente'),
                'brand' => strtolower(trim((string) $charge->card_brand)),
                'funding' => strtolower(trim((string) $charge->card_funding)),
                'last4' => (string) $charge->card_last4,
                'exp_month' => $row['month'],
                'exp_year' => $row['year'],
            ];
        }

        usort($rows, function (array $left, array $right): int
        {
            return [$left['exp_year'], $left['exp_month'], $left['customer_name']]
                <=> [$right['exp_year'], $right['exp_month'], $right['customer_name']];
        });

        return array_slice($rows, 0, 8);
    }

    /**
     * Latest successful card charges, newest first.
     *
     * @return list<array{id: int, subscription_id: int|null, customer_name: string, brand: string, funding: string, last4: string, date: string, amount: string}>
     */
    private function paymentWidgets(Team $team): array
    {
        $charges = $this->latestCardCharges($team)
            ->filter(function ($charge): bool
            {
                return strtolower(trim((string) $charge->status)) === 'succeeded'
                    && trim((string) $charge->card_last4) !== '';
            })
            ->take(6)
            ->values();

        $customerIds = $charges
            ->map(fn ($charge): string => trim((string) $charge->customer_id))
            ->filter()
            ->values();
        $subscriptions = ServiceSync::query()
            ->where('team_id', $team->id)
            ->whereIn('customer_id', $customerIds)
            ->orderByDesc('id')
            ->get(['id', 'customer_id', 'customer_name'])
            ->groupBy('customer_id');

        return $charges->map(function ($charge) use ($subscriptions): array
        {
            $subscription = $subscriptions->get($charge->customer_id)?->first();
            $when = $charge->charge_created_at ? Carbon::parse($charge->charge_created_at) : null;
            $currency = strtoupper((string) ($charge->currency ?: 'eur'));

            return [
                'id' => (int) $charge->id,
                'subscription_id' => $subscription?->id,
                'customer_name' => $subscription?->customer_name
                    ?: ($charge->customer_email ?: 'Cliente'),
                'brand' => strtolower(trim((string) $charge->card_brand)),
                'funding' => strtolower(trim((string) $charge->card_funding)),
                'last4' => (string) $charge->card_last4,
                'date' => $when?->locale(app()->getLocale())->isoFormat('DD/MMM') ?? '',
                'amount' => number_format(((int) $charge->amount_cents) / 100, 2, ',', '.').' '.$currency,
            ];
        })->all();
    }

    /**
     * @return Collection<int, PaymentSync>
     */
    private function latestCardCharges(Team $team): Collection
    {
        $query = PaymentSync::query()
            ->where('team_id', $team->id)
            ->where('provider', 'stripe')
            ->whereNotNull('raw_payload->payment_method_details->card->last4');

        if ($query->getConnection()->getDriverName() === 'pgsql')
        {
            $query->selectRaw("
                id,
                customer_id,
                customer_email,
                status,
                currency,
                amount_cents,
                charge_created_at,
                raw_payload->'payment_method_details'->'card'->>'brand' as card_brand,
                raw_payload->'payment_method_details'->'card'->>'funding' as card_funding,
                raw_payload->'payment_method_details'->'card'->>'last4' as card_last4,
                (raw_payload->'payment_method_details'->'card'->>'exp_month')::int as card_exp_month,
                (raw_payload->'payment_method_details'->'card'->>'exp_year')::int as card_exp_year
            ");
        } else
        {
            $query->selectRaw("
                id,
                customer_id,
                customer_email,
                status,
                currency,
                amount_cents,
                charge_created_at,
                json_extract(raw_payload, '$.payment_method_details.card.brand') as card_brand,
                json_extract(raw_payload, '$.payment_method_details.card.funding') as card_funding,
                json_extract(raw_payload, '$.payment_method_details.card.last4') as card_last4,
                CAST(json_extract(raw_payload, '$.payment_method_details.card.exp_month') AS INTEGER) as card_exp_month,
                CAST(json_extract(raw_payload, '$.payment_method_details.card.exp_year') AS INTEGER) as card_exp_year
            ");
        }

        return $query->orderByDesc('charge_created_at')->orderByDesc('id')->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function draftWidgets(User $user): ?array
    {
        if (! $user->canSeeCrossTeamUsageBilling())
        {
            return null;
        }

        $drafts = TeamUsageInvoice::query()
            ->with(['team:id,name'])
            ->where('status', TeamUsageInvoice::STATUS_DRAFT)
            ->whereNotNull('stripe_invoice_id')
            ->orderByDesc('period_to')
            ->orderByDesc('id');

        $currency = strtolower((string) ($drafts->clone()->value('currency') ?: 'eur'));
        $openCount = TeamUsageInvoice::query()->where('status', TeamUsageInvoice::STATUS_OPEN)->count();
        $overdueCutoff = now()->subDays(7);
        $overdueCount = TeamUsageInvoice::query()
            ->where('status', TeamUsageInvoice::STATUS_OPEN)
            ->where(function ($query) use ($overdueCutoff)
            {
                $query->where(function ($inner) use ($overdueCutoff)
                {
                    $inner->whereNotNull('issued_at')->where('issued_at', '<', $overdueCutoff);
                })->orWhere(function ($inner) use ($overdueCutoff)
                {
                    $inner->whereNull('issued_at')
                        ->whereNotNull('period_to')
                        ->where('period_to', '<', $overdueCutoff);
                });
            })
            ->count();

        $items = $drafts->clone()->limit(6)->get();
        $enterpriseIds = $this->draftEnterpriseIds($user, $items);

        return [
            'draft_count' => $drafts->clone()->count(),
            'open_count' => $openCount,
            'overdue_count' => $overdueCount,
            'total_label' => $this->moneyLabel((int) $drafts->clone()->sum('billed_cents'), $currency),
            'items' => $items->map(function (TeamUsageInvoice $invoice) use ($enterpriseIds): array
            {
                return [
                    'id' => $invoice->id,
                    'team_name' => $invoice->team?->name ?? ('Team #'.$invoice->team_id),
                    'enterprise_id' => $enterpriseIds[(int) $invoice->team_id] ?? null,
                    'amount' => $this->moneyLabel((int) $invoice->billed_cents, (string) ($invoice->currency ?: 'eur')),
                    'status' => 'Borrador',
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, TeamUsageInvoice>  $items
     * @return array<int, int>
     */
    private function draftEnterpriseIds(User $user, Collection $items): array
    {
        $teamId = (int) ($user->current_team_id ?? 0);

        if ($teamId < 1 || $items->isEmpty())
        {
            return [];
        }

        $teams = Team::query()
            ->whereIn('id', $items->pluck('team_id')->filter()->unique()->all())
            ->get(['id', 'name', 'stripe_id']);
        $enterprises = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->get(['id', 'name', 'code']);
        $byCode = $enterprises
            ->filter(fn (Enterprise $enterprise): bool => trim((string) $enterprise->code) !== '')
            ->keyBy(fn (Enterprise $enterprise): string => strtolower(trim((string) $enterprise->code)));
        $byName = [];

        foreach ($enterprises as $enterprise)
        {
            $key = $this->enterpriseMatchKey((string) $enterprise->name);

            if ($key === '' || isset($byName[$key]))
            {
                continue;
            }

            $byName[$key] = $enterprise;
        }

        $map = [];

        foreach ($teams as $team)
        {
            $code = strtolower(trim((string) $team->stripe_id));
            $enterprise = ($code !== '' ? $byCode->get($code) : null)
                ?? $byName[$this->enterpriseMatchKey((string) $team->name)] ?? null;

            if ($enterprise)
            {
                $map[(int) $team->id] = (int) $enterprise->id;
            }
        }

        return $map;
    }

    private function enterpriseMatchKey(string $name): string
    {
        $value = mb_strtolower(trim($name));
        $value = str_replace(['.', ',', '-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/\b(s l|s a|s r l|s l u|llc|ltd|inc|sa|sl|srl|slu)\b/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function moneyLabel(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.strtoupper($currency);
    }

    /**
     * @return list<array{id: int, label: string, count: int, emoji: string}>
     */
    private function sentimentWidget(Team $team): array
    {
        $emojis = ['😡', '🙁', '😐', '🙂', '🥳'];
        $rows = app(ContactDailySentimentService::class)->chartDataForTeam($team);

        return collect($rows)->values()->map(function (array $row, int $index) use ($emojis): array
        {
            return [
                'id' => $index + 1,
                'label' => (string) $row['label'],
                'count' => (int) $row['count'],
                'emoji' => $emojis[$index] ?? '😐',
            ];
        })->all();
    }

    private function weekdayLabel(Carbon $day): string
    {
        return ['L', 'M', 'X', 'J', 'V', 'S', 'D'][$day->dayOfWeekIso - 1];
    }

    /**
     * Commercial follow-up: people still due, due today, and scheduled ahead.
     *
     * @return array<string, mixed>
     */
    private function followUpPayload(User $user, Team $team, Carbon $today): array
    {
        $empty = [
            'enabled' => false,
            'counts' => [
                'overdue' => 0,
                'today' => 0,
                'upcoming' => 0,
                'total' => 0,
            ],
            'statuses' => [],
            'weeks' => [],
            'due' => [],
            'upcoming' => [],
        ];

        if (! $team->hasModule('list60') || ! $user->can('viewAny', Contact::class))
        {
            return $empty;
        }

        $query = List60::query()
            ->with(['contact.enterprises', 'contact.currentEnterprise', 'status', 'responsible'])
            ->whereHas('contact', function ($query) use ($team)
            {
                $query->where('team_id', $team->id);
            });

        if (! $user->hasRole('admin'))
        {
            $query->where('responsible_id', $user->id);
        }

        $due = [];
        $upcoming = [];
        $statusCounts = [];
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weeks = [];

        for ($index = 0; $index < 8; $index++)
        {
            $from = $weekStart->copy()->addWeeks($index);
            $weeks[] = [
                'label' => $from->locale(app()->getLocale())->isoFormat('D MMM'),
                'count' => 0,
            ];
        }

        foreach ($query->orderBy('date_next')->orderBy('id')->get() as $entry)
        {
            if ($entry->contact === null)
            {
                continue;
            }

            $item = $this->formatFollowUp($entry, $today);
            $statusName = $item['status'] ?: 'Sin estado';
            $statusCounts[$statusName] = ($statusCounts[$statusName] ?? 0) + 1;
            $bucketDate = $entry->date_next?->copy()->startOfDay() ?? $today;
            $dayOffset = (int) $weekStart->diffInDays($bucketDate, false);

            if ($dayOffset >= 0)
            {
                $weekIndex = intdiv($dayOffset, 7);

                if ($weekIndex < 8)
                {
                    $weeks[$weekIndex]['count']++;
                }
            }

            if ($item['when'] === 'upcoming')
            {
                $upcoming[] = $item;

                continue;
            }

            $due[] = $item;
        }

        usort($due, function (array $left, array $right): int
        {
            $rank = ['overdue' => 0, 'today' => 1];
            $byWhen = ($rank[$left['when']] ?? 2) <=> ($rank[$right['when']] ?? 2);

            if ($byWhen !== 0)
            {
                return $byWhen;
            }

            return strcmp((string) $left['date_next'], (string) $right['date_next']);
        });

        $order = array_flip(['Sin contactar', '1 Contacto', '2 Contactos', '3 Contactos', 'Sin respuesta', 'Parado']);
        uksort($statusCounts, function (string $left, string $right) use ($order): int
        {
            return ($order[$left] ?? 99) <=> ($order[$right] ?? 99);
        });

        $statuses = [];
        foreach ($statusCounts as $name => $count)
        {
            $statuses[] = [
                'name' => $name,
                'count' => $count,
            ];
        }

        return [
            'enabled' => true,
            'counts' => [
                'overdue' => count(array_filter($due, fn (array $item): bool => $item['when'] === 'overdue')),
                'today' => count(array_filter($due, fn (array $item): bool => $item['when'] === 'today')),
                'upcoming' => count($upcoming),
                'total' => count($due) + count($upcoming),
            ],
            'statuses' => $statuses,
            'weeks' => $weeks,
            'due' => array_slice($due, 0, 12),
            'upcoming' => array_slice($upcoming, 0, 8),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFollowUp(List60 $entry, Carbon $today): array
    {
        $contact = $entry->contact;
        $due = $entry->date_next?->copy()->startOfDay();
        $enterprise = $contact->currentEnterprise ?? $contact->enterprises->first();
        $notes = trim((string) ($entry->notes ?? ''));

        if ($due === null || $due->equalTo($today))
        {
            $when = 'today';
        } elseif ($due->lt($today))
        {
            $when = 'overdue';
        } else
        {
            $when = 'upcoming';
        }

        return [
            'id' => (int) $entry->id,
            'when' => $when,
            'date_next' => $entry->date_next?->toDateString(),
            'date_label' => $due === null
                ? __('app.list60_date_today')
                : ApplicationDateTime::formatUpcomingContactDate($entry->date_next),
            'status' => $entry->status?->name,
            'responsible' => $entry->responsible?->name,
            'notes' => $notes !== '' ? $notes : null,
            'contact' => [
                'id' => (int) $contact->id,
                'name' => trim($contact->name.' '.($contact->surname ?? '')),
                'enterprise_id' => $enterprise ? (int) $enterprise->id : null,
                'enterprise_name' => $enterprise ? trim((string) $enterprise->name) : null,
            ],
        ];
    }

    /**
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    private function todayContactsPayload(User $user, int $teamId, $today): array
    {
        $query = List60::query()
            ->with([
                'contact.enterprises',
                'contact.currentSentiment.sentiment',
                'status',
            ])
            ->whereHas('contact', function ($query) use ($teamId)
            {
                $query->where('team_id', $teamId);
            })
            ->whereDate('date_next', $today);

        if (! $user->hasRole('admin'))
        {
            $query->where('responsible_id', $user->id);
        }

        $entries = $query
            ->orderBy('date_next')
            ->limit(20)
            ->get();

        $items = $entries
            ->filter(fn (List60 $entry) => $entry->contact !== null)
            ->map(function (List60 $entry)
            {
                $contact = $entry->contact;
                $enterprise = $contact->enterprises->first();

                return [
                    'id' => $entry->id,
                    'contact' => [
                        'id' => $contact->id,
                        'name' => trim($contact->name.' '.($contact->surname ?? '')),
                        'enterprise_name' => $enterprise?->name,
                    ],
                    'status' => $entry->status ? [
                        'name' => $entry->status->name,
                        'label_class' => $entry->status->label_class,
                    ] : null,
                    'sentiment_emoji' => $contact->currentSentiment?->sentiment?->emoji,
                ];
            })
            ->values()
            ->all();

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCalendarEvent(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->all_day
                ? $event->start->utc()->toDateString()
                : $event->start->toIso8601String(),
            'end' => $event->all_day
                ? $event->end->utc()->toDateString()
                : $event->end->toIso8601String(),
            'all_day' => (bool) $event->all_day,
            'location' => $event->location,
            'label' => $event->label,
        ];
    }
}
