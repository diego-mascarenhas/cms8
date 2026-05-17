<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\List60;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Time;
use App\Models\User;
use App\Services\Mail\MailInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private MailInboxService $mailInbox,
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

        return response()->json($payload);
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
