<?php

namespace App\Http\Controllers;

use App\DataTables\TaskDataTable;
use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskCommunication;
use App\Models\TaskStatus;
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
            // Original single-board kanban: always use default board
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

        // Get tasks grouped by status
        $tasksByStatus = [];
        foreach ($statuses as $status)
        {
            $tasks = Task::where('status_id', $status['id'])
                ->where('board_id', $board->id)
                ->with(['responsible', 'category'])
                ->orderBy('order')
                ->get();

            $tasksByStatus[$status['id']] = $tasks->map(function ($task)
            {
                $attachment = $task->getFirstMediaUrl('attachments');

                // Calculate total time for this task
                $times = \App\Models\Time::where('task_id', $task->id)->get();
                $totalSeconds = 0;

                foreach ($times as $time)
                {
                    if ($time->duration_seconds)
                    {
                        $totalSeconds += $time->duration_seconds;
                    } elseif ($time->start_time && $time->end_time)
                    {
                        $totalSeconds += $time->end_time->diffInSeconds($time->start_time);
                    } elseif ($time->start_time && ! $time->end_time)
                    {
                        $totalSeconds += now()->diffInSeconds($time->start_time);
                    }
                }

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
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

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
        $activities = $task
            ->activities()
            ->with('causer')
            ->latest()
            ->get()
            ->map(function ($activity)
            {
                $properties = $activity->properties;

                // Resolve IDs to names for better readability
                if (isset($properties['attributes']))
                {
                    $attributes = $properties['attributes'];

                    // Resolve status_id to status name
                    if (isset($attributes['status_id']))
                    {
                        $status = TaskStatus::find($attributes['status_id']);
                        if ($status)
                        {
                            $attributes['status_name'] = $status->translated_name;
                        }
                    }

                    // Resolve category_id to category name
                    if (isset($attributes['category_id']))
                    {
                        $category = Category::find($attributes['category_id']);
                        if ($category)
                        {
                            $attributes['category_name'] = $category->name;
                        }
                    }

                    // Resolve responsible_id to user name
                    if (isset($attributes['responsible_id']))
                    {
                        $responsible = User::find($attributes['responsible_id']);
                        if ($responsible)
                        {
                            $attributes['responsible_name'] = $responsible->name;
                        }
                    }

                    // Resolve board_id to board name
                    if (isset($attributes['board_id']))
                    {
                        $board = TaskBoard::find($attributes['board_id']);
                        if ($board)
                        {
                            $attributes['board_name'] = $board->name;
                        }
                    }

                    $properties['attributes'] = $attributes;
                }

                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'causer' => $activity->causer ? [
                        'name' => $activity->causer->name,
                        'initials' => strtoupper(substr($activity->causer->name, 0, 2)),
                    ] : null,
                    'created_at' => $activity->created_at->format('d/m/Y H:i'),
                    'properties' => $properties,
                ];
            });

        return response()->json($activities);
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
        $task = Task::with(['responsible', 'status', 'category'])
            ->findOrFail($id);

        return view('task.show', compact('task'));
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

            // Store communication record
            $communication = TaskCommunication::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'recipients' => $request->recipients,
                'method' => in_array('client', $request->recipients) ? 'email' : 'internal',
                'subject' => $request->subject,
                'message' => $request->message,
                'response_token' => $responseToken,
                'sent_at' => now(),
            ]);

            // Dispatch job to send emails in the background
            \App\Jobs\SendTaskCommunication::dispatch($communication)->onQueue('task-communications');

            // Build success message
            $messages = [];
            if (in_array('responsible', $request->recipients))
            {
                $messages[] = 'Nota interna guardada';
            }
            if (in_array('client', $request->recipients))
            {
                $messages[] = 'Email en cola de envío';
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
        $communication = \App\Models\TaskCommunication::with(['task.project.enterprise', 'user'])
            ->where('response_token', $token)
            ->firstOrFail();

        return view('task.communication-respond', compact('communication'));
    }

    public function storeCommunicationResponse(Request $request, $token)
    {
        $request->validate([
            'response' => 'required|string',
        ]);

        $communication = \App\Models\TaskCommunication::where('response_token', $token)
            ->firstOrFail();

        // Check if already responded
        if ($communication->response)
        {
            return redirect()
                ->route('task.communication.respond', $token)
                ->with('error', 'Ya se ha respondido a esta comunicación previamente.');
        }

        $communication->update([
            'response' => $request->response,
            'response_at' => now(),
        ]);

        return redirect()
            ->route('task.communication.respond', $token)
            ->with('success', 'Tu respuesta ha sido enviada correctamente.');
    }

    public function getTotalTime($taskId)
    {
        $task = Task::findOrFail($taskId);

        // Get all time entries for this task
        $times = \App\Models\Time::where('task_id', $task->id)->get();

        \Log::info('TaskController getTotalTime', [
            'task_id' => $taskId,
            'times_count' => $times->count(),
            'times' => $times->map(function ($t)
            {
                return [
                    'id' => $t->id,
                    'start_time' => $t->start_time,
                    'end_time' => $t->end_time,
                    'duration_seconds' => $t->duration_seconds,
                ];
            }),
        ]);

        $totalSeconds = 0;

        foreach ($times as $time)
        {
            if ($time->duration_seconds)
            {
                // Time entry already has duration calculated
                \Log::info('Adding duration_seconds', ['time_id' => $time->id, 'duration_seconds' => $time->duration_seconds]);
                $totalSeconds += $time->duration_seconds;
            } elseif ($time->start_time && $time->end_time)
            {
                // Calculate duration if not stored
                $calculated = $time->end_time->diffInSeconds($time->start_time);
                \Log::info('Calculating from start/end', ['time_id' => $time->id, 'calculated' => $calculated]);
                $totalSeconds += $calculated;
            } elseif ($time->start_time && ! $time->end_time)
            {
                // Timer is still running, calculate current elapsed time
                $calculated = now()->diffInSeconds($time->start_time);
                \Log::info('Calculating running timer', ['time_id' => $time->id, 'calculated' => $calculated]);
                $totalSeconds += $calculated;
            }
        }

        \Log::info('Total calculation result', ['total_seconds' => $totalSeconds, 'hours' => floor($totalSeconds / 3600), 'minutes' => floor(($totalSeconds % 3600) / 60)]);

        // Format as hours and minutes
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
