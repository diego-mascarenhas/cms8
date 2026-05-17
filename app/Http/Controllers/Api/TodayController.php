<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\Time;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    use ChecksTeamModule;

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if (! $team->hasModule('today') && ! $team->hasModule('calendar'))
        {
            return response()->json([
                'success' => false,
                'message' => __('Este módulo no está disponible en tu plan.'),
            ], 403);
        }

        $user = $request->user();
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $events = CalendarEvent::query()
            ->where('end', '>', $today)
            ->where('start', '<', $tomorrow)
            ->orderBy('start')
            ->get()
            ->map(fn (CalendarEvent $event) => [
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
            ])
            ->values()
            ->all();

        $tasksQuery = Task::query()
            ->with(['status', 'responsible'])
            ->where(function ($query) use ($today)
            {
                $query->whereBetween('due_date', [$today->toDateString(), $today->toDateString()])
                    ->orWhereBetween('start_date', [$today->toDateString(), $today->toDateString()])
                    ->orWhere(function ($inner) use ($today)
                    {
                        $inner->where('start_date', '<=', $today->toDateString())
                            ->where('due_date', '>=', $today->toDateString());
                    });
            });

        if (! $user->hasRole('admin'))
        {
            $tasksQuery->where('responsible_id', $user->id);
        }

        $tasks = $tasksQuery
            ->orderBy('due_date')
            ->orderBy('start_date')
            ->limit(50)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'start_date' => $task->start_date?->format('Y-m-d'),
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                    'color' => $task->status?->color,
                ],
                'responsible' => $task->responsible ? [
                    'id' => $task->responsible->id,
                    'name' => $task->responsible->name,
                ] : null,
            ])
            ->values()
            ->all();

        $runningTimer = Time::getRunningTimer($user->id);
        $runningTask = null;
        if ($runningTimer)
        {
            $runningTimer->load('task', 'task.status');
            $runningTask = [
                'time_id' => $runningTimer->id,
                'task_id' => $runningTimer->task_id,
                'title' => $runningTimer->task?->title,
                'status' => $runningTimer->task?->status?->translated_name,
                'elapsed_seconds' => $runningTimer->start_time
                    ? now()->diffInSeconds($runningTimer->start_time)
                    : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'date' => $today->toDateString(),
            'events' => $events,
            'tasks' => $tasks,
            'running_task' => $runningTask,
        ]);
    }
}
