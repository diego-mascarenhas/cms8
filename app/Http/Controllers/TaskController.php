<?php

namespace App\Http\Controllers;

use App\DataTables\TaskDataTable;
use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskCommunication;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Models\User;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(TaskDataTable $dataTable, Request $request)
    {
        if ($request->has('view') && $request->view === 'kanban')
        {
            return $this->kanban($request);
        }

        return $dataTable->render('task.index');
    }

    public function kanban(Request $request)
    {
        $board = null;
        $project = null;

        // Check if we have a project_id parameter
        if ($request->has('project_id'))
        {
            $project = Project::findOrFail($request->project_id);

            // Use project's board if it exists, otherwise create one
            if ($project->board_id)
            {
                $board = TaskBoard::find($project->board_id);
            } else
            {
                // Create board for project if it doesn't exist
                $board = TaskBoard::create([
                    'team_id' => auth()->user()->currentTeam->id,
                    'name' => "Project: {$project->name}",
                    'description' => "Task board for project: {$project->name}",
                    'is_default' => false,
                    'order' => 0,
                ]);

                $project->update(['board_id' => $board->id]);
            }
        } else
        {
            // Tablero general: use default board for new tasks, but show tasks from all team boards
            $board = TaskBoard::getDefaultBoard();
        }

        $statuses = TaskStatus::orderBy('order')->get()->map(function ($status)
        {
            return [
                'id' => $status->id,
                'name' => $status->translated_name,
                'original_name' => $status->name,
            ];
        });

        // When no project (Tablero general), show tasks from team boards that are NOT linked to a project
        $boardIds = $project ? [$board->id] : TaskBoard::pluck('id')->all();
        if (empty($boardIds))
        {
            $boardIds = [$board->id];
        }

        $projectBoardIds = $project ? [] : Project::whereNotNull('board_id')->pluck('board_id')->unique()->values()->all();

        // Get tasks grouped by status
        $tasksByStatus = [];
        foreach ($statuses as $status)
        {
            $query = Task::where('status_id', $status['id'])
                ->whereIn('board_id', $boardIds)
                ->with(['responsible', 'category']);
            if (! $project && ! empty($projectBoardIds))
            {
                $query->whereNotIn('board_id', $projectBoardIds);
            }
            $tasks = $query->orderBy('order')->get();

            $tasksByStatus[$status['id']] = $tasks->map(function ($task)
            {
                $attachment = $task->getFirstMediaUrl('attachments');

                // Calculate total time for this task
                $times = \App\Models\Time::where('task_id', $task->id)->get();
                $totalSeconds = 0;

                foreach ($times as $time)
                {
                    $add = 0;
                    if ($time->duration_seconds && $time->duration_seconds > 0)
                    {
                        $add = $time->duration_seconds;
                    } elseif ($time->start_time && $time->end_time)
                    {
                        $add = max(0, $time->end_time->getTimestamp() - $time->start_time->getTimestamp());
                    } elseif ($time->start_time && ! $time->end_time)
                    {
                        $add = max(0, now()->getTimestamp() - $time->start_time->getTimestamp());
                    }
                    $totalSeconds += $add;
                }
                $totalSeconds = max(0, (int) $totalSeconds);

                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                $totalTimeFormatted = $hours > 0 ? "{$hours}h {$minutes}min" : ($minutes > 0 ? "{$minutes}min" : '0min');

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                    'estimated_hours' => $task->estimated_hours,
                    'attachment' => $attachment ?: null,
                    'total_time' => $totalTimeFormatted,
                    'total_seconds' => $totalSeconds,
                    'responsible' => $task->responsible ? [
                        'id' => $task->responsible->id,
                        'name' => $task->responsible->name,
                        'profile_photo_url' => $task->responsible->profile_photo_url ?? null,
                    ] : null,
                    'category' => $task->category ? [
                        'id' => $task->category->id,
                        'name' => $task->category->name,
                    ] : null,
                ];
            });
        }

        // Options for offcanvas editing - Only admin and collaborators
        $users = User::query()
            ->whereHas('teams', function ($q)
            {
                $q->where('team_id', auth()->user()->currentTeam->id);
            })
            ->whereHas('roles', function ($q)
            {
                $q->whereIn('name', ['admin', 'collaborator']);
            })
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'profile_photo_url' => $u->profile_photo_url ?? null]);

        $categories = class_exists(Category::class)
            ? Category::query()
                ->whereHas('module', function ($q)
                {
                    $q->where('key', 'tasks');
                })
                ->where('team_id', auth()->user()->currentTeam->id)
                ->with('parent')
                ->get(['id', 'name', 'parent_id'])
                ->whereNotNull('parent_id')  // Only get subcategories
                ->groupBy('parent.name')  // Group by parent category name
                ->map(function ($group, $parentName)
                {
                    return [
                        'name' => $parentName,
                        'categories' => $group->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                    ];
                })
                ->values()
            : collect();

        // Get only IN_PROGRESS projects (status_id = 9) for the selector
        $projects = Project::query()
            ->where('team_id', auth()->user()->currentTeam->id)
            ->where('status_id', 9)  // Only show projects IN_PROGRESS
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('task.kanban', compact('statuses', 'tasksByStatus', 'project', 'board', 'users', 'categories', 'projects'));
    }

    public function getActivities($taskId)
    {
        $task = Task::findOrFail($taskId);

        $times = Time::where('task_id', $task->id)
            ->with('user')
            ->orderByDesc('start_time')
            ->get();

        $totalSeconds = 0;
        $entries = $times->map(function (Time $time) use (&$totalSeconds)
        {
            $seconds = $time->duration_seconds;
            if ((! $seconds || $seconds < 0) && $time->start_time && $time->end_time)
            {
                $seconds = max(0, $time->end_time->getTimestamp() - $time->start_time->getTimestamp());
            }
            if ((! $seconds || $seconds < 0) && $time->start_time && ! $time->end_time)
            {
                $seconds = max(0, now()->getTimestamp() - $time->start_time->getTimestamp());
            }
            $seconds = max(0, (int) $seconds);
            $totalSeconds += $seconds;

            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $durationFormatted = $hours > 0
                ? sprintf('%dh %dm', $hours, $minutes)
                : sprintf('%dm', $minutes);

            $user = $time->user;

            return [
                'id' => $time->id,
                'user_name' => $user ? $user->name : __('Sistema'),
                'user_initials' => $user ? strtoupper(substr($user->name, 0, 2)) : 'SY',
                'user_avatar_url' => $user ? $user->profile_photo_url : null,
                'description' => $time->description ?: null,
                'duration_seconds' => (int) $seconds,
                'duration_formatted' => $durationFormatted,
                'start_time' => $time->start_time?->format('d/m/Y H:i'),
                'end_time' => $time->end_time?->format('d/m/Y H:i'),
                'is_running' => $time->isRunning(),
            ];
        });

        $totalSeconds = max(0, (int) $totalSeconds);
        $totalHours = floor($totalSeconds / 3600);
        $totalMinutes = floor(($totalSeconds % 3600) / 60);
        $totalFormatted = $totalHours > 0
            ? sprintf('%dh %dmin', $totalHours, $totalMinutes)
            : ($totalMinutes > 0 ? sprintf('%dmin', $totalMinutes) : '0min');

        return response()->json([
            'total_seconds' => $totalSeconds,
            'total_formatted' => $totalFormatted,
            'times' => $entries->values()->all(),
        ]);
    }

    public function create(Request $request)
    {
        $statuses = TaskStatus::getOptions();
        $boards = TaskBoard::getOptions();
        $defaultBoardId = null;
        $projectId = $request->input('project_id');
        $defaultStatusId = $request->integer('status_id') ?: 1;

        // If coming from kanban view, get the board_id
        if ($request->has('board_id'))
        {
            $defaultBoardId = $request->input('board_id');
        } else
        {
            $defaultBoard = TaskBoard::getDefaultBoard();
            $defaultBoardId = $defaultBoard->id;
        }

        return view('task.form', compact('statuses', 'boards', 'defaultBoardId', 'projectId', 'defaultStatusId'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['id', '_token', 'attachment']);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'responsible_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'status_id' => 'required|exists:task_statuses,id',
            'category_id' => 'nullable|exists:categories,id',
            'board_id' => 'nullable|exists:task_boards,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'attachment' => 'nullable|file|image|max:10240',  // Max 10MB
        ]);

        $boardId = $data['board_id'] ?? null;
        if (! $boardId)
        {
            // Get default board if none provided
            $board = TaskBoard::getDefaultBoard();
            $boardId = $board->id;
        }

        $task = Task::updateOrCreate(
            ['id' => $request->id],
            [
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'category_id' => $data['category_id'] ?? null,
                'responsible_id' => $data['responsible_id'],
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'order' => 0,
                'status_id' => $data['status_id'] ?? 1,
                'board_id' => $boardId,
                'team_id' => auth()->user()->currentTeam->id,
            ],
        );

        // Handle file upload using Spatie Media Library
        \Log::info('TaskController Store - Request has file?', [
            'hasFile' => $request->hasFile('attachment'),
            'removeAttachment' => $request->has('remove_attachment'),
            'allFiles' => $request->allFiles(),
            'all' => $request->all(),
        ]);

        // Check if we need to remove attachment
        if ($request->has('remove_attachment') && $request->input('remove_attachment') == '1')
        {
            \Log::info('Removing attachment for task', ['task_id' => $task->id]);

            // Delete all attachments
            try
            {
                $existingMedia = $task->getMedia('attachments');
                foreach ($existingMedia as $media)
                {
                    $media->delete();
                }
                \Log::info('Attachment removed successfully');
            } catch (\Exception $e)
            {
                \Log::error('Error removing attachment: '.$e->getMessage());
            }
        } elseif ($request->hasFile('attachment'))
        {
            \Log::info('Processing attachment upload', [
                'file' => $request->file('attachment')->getClientOriginalName(),
            ]);

            // Delete old attachments first (safely)
            try
            {
                $existingMedia = $task->getMedia('attachments');
                foreach ($existingMedia as $media)
                {
                    $media->delete();
                }
            } catch (\Exception $e)
            {
                // Ignore errors when deleting old files
                \Log::warning('Could not delete old attachment: '.$e->getMessage());
            }

            // Add new attachment
            try
            {
                $task->addMediaFromRequest('attachment')->toMediaCollection('attachments');
                \Log::info('Attachment uploaded successfully');
            } catch (\Exception $e)
            {
                \Log::error('Error uploading attachment: '.$e->getMessage());
            }
        }

        if ($request->expectsJson())
        {
            // Refresh task to get latest media
            $task->refresh();
            $attachmentUrl = $task->getFirstMediaUrl('attachments');

            // If no URL, try to get the full media object
            if (empty($attachmentUrl))
            {
                $media = $task->getFirstMedia('attachments');
                if ($media)
                {
                    $attachmentUrl = $media->getUrl();
                }
            }

            \Log::info('Returning attachment URL', [
                'attachmentUrl' => $attachmentUrl,
                'task_id' => $task->id,
            ]);

            return response()->json([
                'success' => true,
                'id' => $task->id,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'start_date' => $task->start_date?->format('Y-m-d'),
                'attachment' => $attachmentUrl ?: null,
            ]);
        }

        if ($request->has('view') && $request->view === 'kanban')
        {
            // Check if we have a project_id to redirect back to project kanban
            $projectId = $request->input('project_id');
            if ($projectId)
            {
                return redirect()->route('task.index', ['view' => 'kanban', 'project_id' => $projectId])->with('success', 'Record saved successfully.');
            }

            return redirect()->route('task.index', ['view' => 'kanban', 'board_id' => $boardId])->with('success', 'Record saved successfully.');
        }

        return redirect()->route('task.index')->with('success', 'Record saved successfully.');
    }

    public function show(string $id)
    {
        $task = Task::with(['responsible', 'status', 'category', 'project'])
            ->findOrFail($id);

        $actualStartAt = Time::where('task_id', $task->id)->min('start_time');
        $actualEndAt = Time::where('task_id', $task->id)->whereNotNull('end_time')->max('end_time');

        return view('task.show', compact('task', 'actualStartAt', 'actualEndAt'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Task::findOrFail($id);
        $statuses = TaskStatus::getOptions();
        $boards = TaskBoard::getOptions();
        $defaultBoardId = $data->board_id;

        return view('task.form', compact('data', 'statuses', 'boards', 'defaultBoardId'));
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'status_id' => 'required|exists:task_statuses,id',
            'order' => 'required|integer|min:0',
        ]);

        $task = Task::findOrFail($request->task_id);
        $task->status_id = $request->status_id;
        $task->order = $request->order;
        $task->save();

        return response()->json(['success' => true]);
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->tasks as $taskData)
        {
            $task = Task::findOrFail($taskData['id']);
            $task->order = $taskData['order'];
            $task->save();
        }

        return response()->json(['success' => true]);
    }

    public function destroy(string $id)
    {
        try
        {
            $task = Task::findOrFail($id);
            $task->delete();

            return response()->json(['success' => true, 'message' => 'Task deleted successfully']);
        } catch (\Exception $e)
        {
            return response()->json(['success' => false, 'message' => 'Error deleting task'], 500);
        }
    }

    public function sendCommunication(Request $request)
    {
        try
        {
            $request->validate([
                'task_id' => 'required|exists:tasks,id',
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'in:responsible,client',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
            ]);

            $task = Task::findOrFail($request->task_id);

            // Generate unique token if client is in recipients
            $responseToken = null;
            if (in_array('client', $request->recipients))
            {
                $responseToken = \Str::random(64);
            }

            // Store communication record (method = email when any recipient gets an email)
            $method = (in_array('responsible', $request->recipients) || in_array('client', $request->recipients))
                ? 'email'
                : 'internal';
            $communication = TaskCommunication::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'recipients' => $request->recipients,
                'method' => $method,
                'subject' => $request->subject,
                'message' => $request->message,
                'response_token' => $responseToken,
                'sent_at' => now(),
            ]);

            // In local, send synchronously so Mailpit receives immediately without running queue:work
            if (app()->environment('local'))
            {
                \App\Jobs\SendTaskCommunication::dispatchSync($communication);
            } else
            {
                \App\Jobs\SendTaskCommunication::dispatch($communication)->onQueue('task-communications');
            }

            // Build success message
            $messages = [];
            if (in_array('responsible', $request->recipients))
            {
                $messages[] = app()->environment('local') ? __('Email enviado al responsable') : __('Email al responsable en cola');
            }
            if (in_array('client', $request->recipients))
            {
                $messages[] = app()->environment('local') ? __('Email enviado al cliente') : __('Email al cliente en cola');
            }

            return response()->json([
                'success' => true,
                'message' => implode(' y ', $messages),
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Error creating task communication', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la comunicación: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getCommunications($id)
    {
        try
        {
            $task = Task::findOrFail($id);

            $communications = TaskCommunication::where('task_id', $task->id)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($comm)
                {
                    // Recipients is already cast to array in the model
                    $recipients = $comm->recipients ?? [];
                    $recipientsDisplay = [];

                    if (in_array('responsible', $recipients))
                    {
                        $recipientsDisplay[] = 'Responsable';
                    }
                    if (in_array('client', $recipients))
                    {
                        $recipientsDisplay[] = 'Cliente';
                    }

                    return [
                        'id' => $comm->id,
                        'method' => $comm->method,
                        'subject' => $comm->subject,
                        'message' => $comm->message,
                        'recipients' => $recipients,
                        'recipients_display' => implode(', ', $recipientsDisplay),
                        'sender_name' => $comm->user ? $comm->user->name : 'Sistema',
                        'created_at' => $comm->created_at->format('d/m/Y H:i'),
                        'has_response' => ! empty($comm->response),
                        'response' => $comm->response,
                        'response_at' => $comm->response_at ? $comm->response_at->format('d/m/Y H:i') : null,
                    ];
                });

            return response()->json($communications);
        } catch (\Exception $e)
        {
            \Log::error('Error loading task communications', [
                'task_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([], 500);
        }
    }

    public function showCommunicationResponse($token)
    {
        $communication = TaskCommunication::with(['task.project.enterprise', 'user'])
            ->where('response_token', $token)
            ->firstOrFail();

        $task = $communication->task;
        $project = $task->project;

        if (! $project)
        {
            return view('task.communication-respond', compact('communication'));
        }

        // Record client visit (first time only)
        if (! $communication->client_visited_at)
        {
            $communication->update(['client_visited_at' => now()]);
        }

        // Project tasks (no activity/times) - without global scope for unauthenticated
        $tasks = Task::withoutGlobalScopes()
            ->where('board_id', $project->board_id)
            ->with(['status', 'responsible'])
            ->orderBy('order')
            ->get();

        return view('task.communication-landing', compact('communication', 'project', 'tasks'));
    }

    public function storeCommunicationResponse(Request $request, $token)
    {
        $request->validate([
            'response' => 'required|string',
            'action' => 'required|in:respond_todo,mark_complete',
        ]);

        $communication = TaskCommunication::with('task')->where('response_token', $token)->firstOrFail();

        if ($communication->response)
        {
            return redirect()
                ->route('task.communication.respond', $token)
                ->with('error', __('Ya se ha respondido a esta comunicación previamente.'));
        }

        $now = now();
        $task = $communication->task;

        $communication->update([
            'response' => $request->response,
            'response_at' => $now,
            'client_responded_at' => $communication->client_visited_at ? $now : null,
        ]);

        $statusToDo = TaskStatus::where('name', 'TO_DO')->value('id');
        $statusDone = TaskStatus::where('name', 'DONE')->value('id');

        if ($request->action === 'respond_todo' && $statusToDo)
        {
            $task->update(['status_id' => $statusToDo]);
        }
        if ($request->action === 'mark_complete' && $statusDone)
        {
            $task->update(['status_id' => $statusDone]);
        }

        // Record non-billable client time (visit to response)
        if ($communication->client_visited_at && $communication->client_responded_at)
        {
            $durationSeconds = $communication->client_responded_at->diffInSeconds($communication->client_visited_at);
            $userId = $task->responsible_id ?? \App\Models\Team::find($task->team_id)?->users()->first()?->id;
            if ($userId)
            {
                Time::create([
                    'team_id' => $task->team_id,
                    'user_id' => $userId,
                    'task_id' => $task->id,
                    'description' => __('Client view and response (non-billable)'),
                    'start_time' => $communication->client_visited_at,
                    'end_time' => $communication->client_responded_at,
                    'duration_seconds' => $durationSeconds,
                    'is_billable' => false,
                ]);
            }
        }

        return redirect()
            ->route('task.communication.respond', $token)
            ->with('success', __('Tu respuesta ha sido enviada correctamente.'));
    }

    public function getTotalTime($taskId)
    {
        $task = Task::findOrFail($taskId);

        $times = Time::where('task_id', $task->id)->get();
        $totalSeconds = 0;

        foreach ($times as $time)
        {
            $add = 0;
            if ($time->duration_seconds && $time->duration_seconds > 0)
            {
                $add = $time->duration_seconds;
            } elseif ($time->start_time && $time->end_time)
            {
                $add = max(0, $time->end_time->getTimestamp() - $time->start_time->getTimestamp());
            } elseif ($time->start_time && ! $time->end_time)
            {
                $add = max(0, now()->getTimestamp() - $time->start_time->getTimestamp());
            }
            $totalSeconds += $add;
        }

        $totalSeconds = max(0, (int) $totalSeconds);
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);

        return response()->json([
            'total_seconds' => $totalSeconds,
            'hours' => $hours,
            'minutes' => $minutes,
            'formatted' => $hours > 0 ? "{$hours}h {$minutes}min" : ($minutes > 0 ? "{$minutes}min" : '0min'),
        ]);
    }
}
