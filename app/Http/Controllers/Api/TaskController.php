<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTaskCommunication;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCommunication;
use App\Models\TaskStatus;
use App\Models\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    /**
     * Obtiene la lista de estados disponibles para las tareas.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statuses()
    {
        $statuses = TaskStatus::orderBy('order')
            ->get()
            ->map(function ($status)
            {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'translated_name' => $status->translated_name,
                    'color' => $status->color,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Lista todas las tareas asociadas a un proyecto por project_key (sin autenticación).
     * Para integración externa (ej. Oba) igual que time/store-by-project-key.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tasksByProjectKey(Request $request)
    {
        $validated = $request->validate([
            'project_key' => 'required|string|size:64',
        ]);

        $project = Project::findByKey($validated['project_key']);
        if (! $project)
        {
            return response()->json([
                'success' => false,
                'message' => __('Proyecto no encontrado con la clave indicada.'),
            ], 404);
        }

        if (! $project->board_id)
        {
            return response()->json([
                'success' => true,
                'data' => [],
                'total' => 0,
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                ],
            ]);
        }

        $tasks = Task::withoutGlobalScopes()
            ->where('board_id', $project->board_id)
            ->with(['status', 'category', 'responsible'])
            ->defaultOrder()
            ->get();

        $data = $tasks->map(function ($task)
        {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'start_date' => $task->start_date?->format('Y-m-d'),
                'due_date' => $task->due_date?->format('Y-m-d'),
                'estimated_hours' => $task->estimated_hours,
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
                'category' => [
                    'id' => $task->category?->id,
                    'name' => $task->category?->name,
                ],
                'responsible' => [
                    'id' => $task->responsible?->id,
                    'name' => $task->responsible?->name,
                    'email' => $task->responsible?->email,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $data->count(),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
        ]);
    }

    /**
     * Lista tareas por context_key (proyecto + usuario). Con una sola clave puedes listar y luego asignar.
     * Misma respuesta que tasks-by-project-key.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tasksByContextKey(Request $request)
    {
        $validated = $request->validate([
            'context_key' => 'required|string',
        ]);

        $decoded = Project::decodeContextKey($validated['context_key']);
        if (! $decoded)
        {
            return response()->json([
                'success' => false,
                'message' => __('Clave de contexto inválida o corrupta.'),
            ], 422);
        }

        $project = Project::findByKey($decoded['project_key']);
        if (! $project)
        {
            return response()->json([
                'success' => false,
                'message' => __('Proyecto no encontrado con la clave indicada.'),
            ], 404);
        }

        if (! $project->board_id)
        {
            return response()->json([
                'success' => true,
                'data' => [],
                'total' => 0,
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                ],
            ]);
        }

        $tasks = Task::withoutGlobalScopes()
            ->where('board_id', $project->board_id)
            ->with(['status', 'category', 'responsible'])
            ->defaultOrder()
            ->get();

        $data = $tasks->map(function ($task)
        {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'start_date' => $task->start_date?->format('Y-m-d'),
                'due_date' => $task->due_date?->format('Y-m-d'),
                'estimated_hours' => $task->estimated_hours,
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
                'category' => [
                    'id' => $task->category?->id,
                    'name' => $task->category?->name,
                ],
                'responsible' => [
                    'id' => $task->responsible?->id,
                    'name' => $task->responsible?->name,
                    'email' => $task->responsible?->email,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $data->count(),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
        ]);
    }

    /**
     * Asigna la tarea al usuario indicado en la context_key y pone la tarea en estado "En progreso".
     * La context_key contiene proyecto + usuario (generada en la ficha del proyecto como "Clave MCP").
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function taskAssignAndStart(Request $request)
    {
        $validated = $request->validate([
            'context_key' => 'required|string',
            'task_id' => 'required|integer|min:1',
        ]);

        $decoded = Project::decodeContextKey($validated['context_key']);
        if (! $decoded)
        {
            return response()->json([
                'success' => false,
                'message' => __('Clave de contexto inválida o corrupta.'),
            ], 422);
        }

        $project = Project::findByKey($decoded['project_key']);
        if (! $project || ! $project->board_id)
        {
            return response()->json([
                'success' => false,
                'message' => __('Proyecto no encontrado o sin tablero.'),
            ], 404);
        }

        $user = \App\Models\User::withoutGlobalScopes()->find($decoded['user_id']);
        if (! $user)
        {
            return response()->json([
                'success' => false,
                'message' => __('Usuario no encontrado.'),
            ], 404);
        }

        $isMember = $user->teams()->where('team_id', $project->team_id)->exists();
        if (! $isMember)
        {
            return response()->json([
                'success' => false,
                'message' => __('El usuario no pertenece al equipo del proyecto.'),
            ], 403);
        }

        $inProgressStatus = TaskStatus::where('name', 'IN_PROGRESS')->first();
        if (! $inProgressStatus)
        {
            return response()->json([
                'success' => false,
                'message' => __('Estado "En progreso" no configurado.'),
            ], 500);
        }

        $task = Task::withoutGlobalScopes()
            ->where('id', $validated['task_id'])
            ->where('board_id', $project->board_id)
            ->first();

        if (! $task)
        {
            return response()->json([
                'success' => false,
                'message' => __('Tarea no encontrada o no pertenece a este proyecto.'),
            ], 404);
        }

        $task->update([
            'responsible_id' => $user->id,
            'status_id' => $inProgressStatus->id,
        ]);

        // Stop any other running timer for this user (except this task) and persist duration
        Time::withoutGlobalScope('team')
            ->where('user_id', $user->id)
            ->where('task_id', '!=', $task->id)
            ->whereNull('end_time')
            ->get()
            ->each(function (Time $t)
            {
                $t->update(['end_time' => now()]);
                $t->calculateDuration();
            });

        // Create time entry so start/end can be computed when task is completed (only if none running for this task)
        $existingRunning = Time::withoutGlobalScope('team')
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->exists();

        if (! $existingRunning)
        {
            Time::withoutGlobalScope('team')->create([
                'team_id' => $task->team_id,
                'user_id' => $user->id,
                'task_id' => $task->id,
                'start_time' => now(),
                'description' => $task->title,
                'is_billable' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Tarea asignada y puesta en progreso.'),
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'responsible' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'status' => [
                    'id' => $inProgressStatus->id,
                    'name' => $inProgressStatus->name,
                ],
            ],
        ]);
    }

    /**
     * Marca la tarea como finalizada (DONE) usando context_key (proyecto + usuario).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function taskCompleteByContextKey(Request $request)
    {
        $validated = $request->validate([
            'context_key' => 'required|string',
            'task_id' => 'required|integer|min:1',
        ]);

        $decoded = Project::decodeContextKey($validated['context_key']);
        if (! $decoded)
        {
            return response()->json([
                'success' => false,
                'message' => __('Clave de contexto inválida o corrupta.'),
            ], 422);
        }

        $project = Project::findByKey($decoded['project_key']);
        if (! $project || ! $project->board_id)
        {
            return response()->json([
                'success' => false,
                'message' => __('Proyecto no encontrado o sin tablero.'),
            ], 404);
        }

        $user = \App\Models\User::withoutGlobalScopes()->find($decoded['user_id']);
        if (! $user)
        {
            return response()->json([
                'success' => false,
                'message' => __('Usuario no encontrado.'),
            ], 404);
        }

        $isMember = $user->teams()->where('team_id', $project->team_id)->exists();
        if (! $isMember)
        {
            return response()->json([
                'success' => false,
                'message' => __('El usuario no pertenece al equipo del proyecto.'),
            ], 403);
        }

        $doneStatus = TaskStatus::where('name', 'DONE')->first();
        if (! $doneStatus)
        {
            return response()->json([
                'success' => false,
                'message' => __('Estado "Completado" no configurado.'),
            ], 500);
        }

        $task = Task::withoutGlobalScopes()
            ->where('id', $validated['task_id'])
            ->where('board_id', $project->board_id)
            ->first();

        if (! $task)
        {
            return response()->json([
                'success' => false,
                'message' => __('Tarea no encontrada o no pertenece a este proyecto.'),
            ], 404);
        }

        $task->update(['status_id' => $doneStatus->id]);

        // Close running time entry for this task and user so actual hours are computed
        $runningTime = Time::withoutGlobalScope('team')
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->first();

        if ($runningTime)
        {
            $runningTime->update(['end_time' => now()]);
            $runningTime->calculateDuration();
        }

        return response()->json([
            'success' => true,
            'message' => __('Tarea marcada como finalizada.'),
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => [
                    'id' => $doneStatus->id,
                    'name' => $doneStatus->name,
                ],
            ],
        ]);
    }

    /**
     * Counts of pending tasks for the current team: all vs assigned to the user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        $pendingQuery = Task::query()->whereHas('status', function ($query)
        {
            $query->whereNotIn('name', ['DONE', 'CANCELLED']);
        });

        $total = (clone $pendingQuery)->count();
        $mine = (clone $pendingQuery)->where('responsible_id', $user->id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'mine' => $mine,
            ],
        ]);
    }

    /**
     * Lista las tareas asignadas al usuario autenticado.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Query base: tareas asignadas al usuario (admins can filter by board/project without own-only)
        $query = Task::with(['status', 'category', 'project', 'responsible']);

        if (! $user->hasRole('admin') || (! $request->filled('board_id') && ! $request->filled('project_id')))
        {
            $query->where('responsible_id', $user->id);
        }

        // Filtros opcionales
        if ($request->has('status_id'))
        {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('board_id'))
        {
            $query->where('board_id', $request->integer('board_id'));
        }

        if ($request->filled('project_id'))
        {
            $project = Project::find($request->integer('project_id'));
            if ($project?->board_id)
            {
                $query->where('board_id', $project->board_id);
            } else
            {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->has('pending_only') && $request->pending_only)
        {
            $query->whereHas('status', function ($q)
            {
                $q->whereNotIn('name', ['DONE', 'CANCELLED']);
            });
        }

        // Ordenamiento
        $tasks = $query->defaultOrder()->get();

        // Transformar a formato API
        $data = $tasks->map(function ($task) use ($user)
        {
            // Buscar tiempo activo para esta tarea
            $activeTime = \App\Models\Time::where('task_id', $task->id)
                ->where('user_id', $user->id)
                ->whereNull('end_time')
                ->first();

            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'start_date' => $task->start_date?->format('Y-m-d'),
                'due_date' => $task->due_date?->format('Y-m-d'),
                'estimated_hours' => $task->estimated_hours,
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
                'category' => [
                    'id' => $task->category?->id,
                    'name' => $task->category?->name,
                ],
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ] : null,
                'responsible' => [
                    'id' => $task->responsible?->id,
                    'name' => $task->responsible?->name,
                    'email' => $task->responsible?->email,
                ],
                'active_time' => $activeTime ? [
                    'id' => $activeTime->id,
                    'started_at' => $activeTime->start_time->toIso8601String(),
                    'elapsed_seconds' => $activeTime->start_time->diffInSeconds(now()),
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * Muestra el detalle de una tarea específica.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $task = Task::with(['status', 'category', 'project', 'responsible'])
            ->findOrFail($id);

        // Validar que el usuario tenga acceso a esta tarea
        // (El global scope ya filtra por team_id, pero verificamos responsible)
        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para ver esta tarea.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'start_date' => $task->start_date?->format('Y-m-d'),
                'due_date' => $task->due_date?->format('Y-m-d'),
                'estimated_hours' => $task->estimated_hours,
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
                'category' => [
                    'id' => $task->category?->id,
                    'name' => $task->category?->name,
                ],
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ] : null,
                'responsible' => [
                    'id' => $task->responsible?->id,
                    'name' => $task->responsible?->name,
                    'email' => $task->responsible?->email,
                ],
                'board' => $task->board ? [
                    'id' => $task->board->id,
                    'name' => $task->board->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Crea una nueva tarea y opcionalmente inicia el timer.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_timer' => 'nullable|boolean',
            'board_id' => 'nullable|integer|exists:task_boards,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'status_id' => 'nullable|integer|exists:task_statuses,id',
            'responsible_id' => 'nullable|integer|exists:users,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        $boardId = $validated['board_id'] ?? null;
        if (! $boardId && ! empty($validated['project_id']))
        {
            $project = Project::find($validated['project_id']);
            if ($project && $user->can('view', $project))
            {
                $boardId = $project->board_id;
            }
        }

        // Obtener el estado inicial (TO_DO por defecto, o IN_PROGRESS si se inicia el timer)
        $defaultStatus = TaskStatus::where('name', $validated['start_timer'] ?? false ? 'IN_PROGRESS' : 'TO_DO')->first();
        $statusId = $validated['status_id'] ?? ($defaultStatus?->id ?? 1);

        $nextOrder = $boardId
            ? ((int) Task::where('board_id', $boardId)->max('order') + 1)
            : 0;

        // Crear la tarea
        $task = Task::create([
            'team_id' => $user->currentTeam->id,
            'board_id' => $boardId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'responsible_id' => $validated['responsible_id'] ?? $user->id,
            'status_id' => $statusId,
            'estimated_hours' => $validated['estimated_hours'] ?? null,
            'order' => $nextOrder,
            'start_date' => $validated['start_date'] ?? now()->toDateString(),
            'due_date' => $validated['due_date'] ?? now()->addDays(7)->toDateString(),
        ]);

        // Si se solicita, iniciar el timer automáticamente
        $timeId = null;
        if ($validated['start_timer'] ?? false)
        {
            // Stop any other running timer and persist duration
            Time::where('user_id', $user->id)
                ->whereNull('end_time')
                ->get()
                ->each(function (Time $t)
                {
                    $t->update(['end_time' => now()]);
                    $t->calculateDuration();
                });

            // Crear registro de tiempo
            $time = Time::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'start_time' => now(),
            ]);

            $timeId = $time->id;
        }

        $task->load('status');

        return response()->json([
            'success' => true,
            'message' => __('Tarea creada correctamente.'),
            'data' => [
                'task_id' => $task->id,
                'title' => $task->title,
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
                'time_id' => $timeId,
                'timer_started' => $validated['start_timer'] ?? false,
            ],
        ], 201);
    }

    /**
     * Inicia el registro de tiempo para una tarea.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        // Validar permisos
        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para iniciar esta tarea.'),
            ], 403);
        }

        // Si el timer de esta tarea ya está corriendo, devolverlo para que el cliente lo muestre.
        $activeTime = Time::where('task_id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->first();

        if ($activeTime)
        {
            $this->moveTaskToInProgress($task);

            return response()->json([
                'success' => true,
                'message' => __('El timer ya está corriendo para esta tarea.'),
                'data' => $this->timerPayload($task, $activeTime),
            ]);
        }

        // Stop any other running timer for this user and persist duration
        Time::where('user_id', $request->user()->id)
            ->where('task_id', '!=', $id)
            ->whereNull('end_time')
            ->get()
            ->each(function (Time $t)
            {
                $t->update(['end_time' => now()]);
                $t->calculateDuration();
            });

        // Crear nuevo registro de tiempo
        $time = Time::create([
            'task_id' => $id,
            'user_id' => $request->user()->id,
            'team_id' => $request->user()->currentTeam->id,
            'start_time' => now(),
        ]);

        $this->moveTaskToInProgress($task);

        return response()->json([
            'success' => true,
            'message' => __('Tarea iniciada correctamente.'),
            'data' => $this->timerPayload($task, $time),
        ]);
    }

    /**
     * Detiene el registro de tiempo activo para una tarea.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function stop(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        // Validar permisos
        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para detener esta tarea.'),
            ], 403);
        }

        // Buscar tiempo activo
        $activeTime = Time::where('task_id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->first();

        if (! $activeTime)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay un registro de tiempo activo para esta tarea.'),
            ], 400);
        }

        // Stop the timer and persist duration so "Actual" hours are computed
        $activeTime->update(['end_time' => now()]);
        $activeTime->calculateDuration();

        $duration = (int) $activeTime->duration_seconds;

        $task->load('status');

        return response()->json([
            'success' => true,
            'message' => __('Tarea detenida correctamente.'),
            'data' => [
                'time_id' => $activeTime->id,
                'task_id' => $task->id,
                'started_at' => $activeTime->start_time->toIso8601String(),
                'ended_at' => $activeTime->end_time->toIso8601String(),
                'duration_seconds' => $duration,
                'duration_formatted' => gmdate('H:i:s', $duration),
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
            ],
        ]);
    }

    private function moveTaskToInProgress(Task $task): void
    {
        $inProgressStatus = TaskStatus::where('name', 'IN_PROGRESS')->first();
        if ($inProgressStatus && $task->status_id !== $inProgressStatus->id)
        {
            $task->update(['status_id' => $inProgressStatus->id]);
        }

        $task->load('status');
    }

    /**
     * @return array<string, mixed>
     */
    private function timerPayload(Task $task, Time $time): array
    {
        return [
            'time_id' => $time->id,
            'task_id' => $task->id,
            'started_at' => $time->start_time->toIso8601String(),
            'status' => [
                'id' => $task->status?->id,
                'name' => $task->status?->name,
                'translated_name' => $task->status?->translated_name,
            ],
        ];
    }

    /**
     * Obtiene el tiempo total invertido en una tarea.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function time(Request $request, $id)
    {
        $task = Task::with(['times' => function ($query)
        {
            $query->whereNotNull('end_time')->orderBy('start_time', 'desc');
        }, 'project', 'status'])->findOrFail($id);

        // Validar permisos
        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para ver el tiempo de esta tarea.'),
            ], 403);
        }

        // Calcular tiempo total
        $totalSeconds = $task->times->sum(function ($time)
        {
            return $time->start_time->diffInSeconds($time->end_time);
        });

        // Tiempo activo (si existe)
        $activeTime = $task->times()->where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->first();

        $entries = $task->times->map(function ($time)
        {
            $duration = $time->start_time->diffInSeconds($time->end_time);

            return [
                'id' => $time->id,
                'user' => [
                    'id' => $time->user->id,
                    'name' => $time->user->name,
                ],
                'started_at' => $time->start_time->toIso8601String(),
                'ended_at' => $time->end_time->toIso8601String(),
                'duration_seconds' => $duration,
                'duration_formatted' => gmdate('H:i:s', $duration),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => [
                        'id' => $task->status?->id,
                        'name' => $task->status?->name,
                        'translated_name' => $task->status?->translated_name,
                    ],
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                    ] : null,
                ],
                'total_seconds' => $totalSeconds,
                'total_formatted' => gmdate('H:i:s', $totalSeconds),
                'total_hours' => round($totalSeconds / 3600, 2),
                'active_time' => $activeTime ? [
                    'id' => $activeTime->id,
                    'started_at' => $activeTime->start_time->toIso8601String(),
                ] : null,
                'entries' => $entries,
            ],
        ]);
    }

    /**
     * Actualiza el estado de una tarea.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        // Validar permisos
        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para cambiar el estado de esta tarea.'),
            ], 403);
        }

        $validated = $request->validate([
            'status_id' => 'required|exists:task_statuses,id',
        ]);

        $task->update([
            'status_id' => $validated['status_id'],
        ]);

        $task->load('status');

        return response()->json([
            'success' => true,
            'message' => __('Estado actualizado correctamente.'),
            'data' => [
                'task_id' => $task->id,
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
            ],
        ]);
    }

    /**
     * Update task fields (title, description, dates, responsible, status, order).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para editar esta tarea.'),
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status_id' => 'sometimes|required|integer|exists:task_statuses,id',
            'responsible_id' => 'sometimes|nullable|integer|exists:users,id',
            'estimated_hours' => 'sometimes|nullable|numeric|min:0',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'start_date' => 'sometimes|nullable|date',
            'due_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'order' => 'sometimes|nullable|integer|min:0',
        ]);

        $task->update($validated);
        $task->load(['status', 'category', 'project', 'responsible']);

        return response()->json([
            'success' => true,
            'message' => __('Tarea actualizada correctamente.'),
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'order' => $task->order,
                'estimated_hours' => $task->estimated_hours,
                'start_date' => $task->start_date?->format('Y-m-d'),
                'due_date' => $task->due_date?->format('Y-m-d'),
                'category_id' => $task->category_id,
                'category' => $task->category ? [
                    'id' => $task->category->id,
                    'name' => $task->category->name,
                ] : null,
                'status' => [
                    'id' => $task->status?->id,
                    'name' => $task->status?->name,
                    'translated_name' => $task->status?->translated_name,
                ],
                'responsible' => [
                    'id' => $task->responsible?->id,
                    'name' => $task->responsible?->name,
                    'email' => $task->responsible?->email,
                ],
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ] : null,
                'attachment' => $task->attachmentUrl(),
            ],
        ]);
    }

    /**
     * Replace the task image. Same rules as the kanban: one image, 10 MB max.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAttachment(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $denied = $this->denyUnlessCanManageTask($request, $task);
        if ($denied)
        {
            return $denied;
        }

        $request->validate([
            'attachment' => 'required|file|image|max:10240',
        ]);

        $task->clearMediaCollection('attachments');
        $task->addMediaFromRequest('attachment')->toMediaCollection('attachments');
        $task->refresh();

        return response()->json([
            'success' => true,
            'message' => __('Adjunto guardado correctamente.'),
            'data' => [
                'id' => $task->id,
                'attachment' => $task->attachmentUrl(),
            ],
        ]);
    }

    /**
     * Remove the task image.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyAttachment(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $denied = $this->denyUnlessCanManageTask($request, $task);
        if ($denied)
        {
            return $denied;
        }

        $task->clearMediaCollection('attachments');

        return response()->json([
            'success' => true,
            'message' => __('Adjunto eliminado correctamente.'),
            'data' => [
                'id' => $task->id,
                'attachment' => null,
            ],
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function denyUnlessCanManageTask(Request $request, Task $task)
    {
        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para editar esta tarea.'),
            ], 403);
        }

        return null;
    }

    /**
     * Time entries logged against a task.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activities($id)
    {
        $task = Task::findOrFail($id);

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

            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);

            return [
                'id' => $time->id,
                'user_name' => $time->user?->name ?? 'Sistema',
                'description' => $time->description ?: null,
                'duration_formatted' => $hours > 0 ? sprintf('%dh %dm', $hours, $minutes) : sprintf('%dm', $minutes),
                'start_time' => $time->start_time?->toIso8601String(),
                'end_time' => $time->end_time?->toIso8601String(),
                'is_running' => $time->isRunning(),
            ];
        });

        $totalSeconds = max(0, (int) $totalSeconds);
        $totalHours = intdiv($totalSeconds, 3600);
        $totalMinutes = intdiv($totalSeconds % 3600, 60);

        return response()->json([
            'success' => true,
            'data' => [
                'total_seconds' => $totalSeconds,
                'total_formatted' => $totalHours > 0
                    ? sprintf('%dh %dmin', $totalHours, $totalMinutes)
                    : ($totalMinutes > 0 ? sprintf('%dmin', $totalMinutes) : '0min'),
                'times' => $entries->values()->all(),
            ],
        ]);
    }

    /**
     * Communication history for a task, including client replies.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function communications($id)
    {
        $task = Task::findOrFail($id);

        $communications = TaskCommunication::where('task_id', $task->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TaskCommunication $communication) => $this->formatCommunication($communication));

        return response()->json([
            'success' => true,
            'data' => $communications,
        ]);
    }

    /**
     * Send a task consultation to the responsible and/or the client.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeCommunication(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'in:responsible,client',
            'message' => 'required|string',
            'subject' => 'nullable|string|max:255',
        ]);

        $recipients = array_values(array_unique($validated['recipients']));
        $subject = $validated['subject'] ?? 'Consulta sobre tarea';

        $communication = TaskCommunication::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'recipients' => $recipients,
            'method' => 'email',
            'subject' => $subject,
            'message' => $validated['message'],
            'response_token' => in_array('client', $recipients, true) ? Str::random(64) : null,
            'sent_at' => now(),
        ]);

        if (app()->environment('local', 'testing'))
        {
            SendTaskCommunication::dispatchSync($communication);
        } else
        {
            SendTaskCommunication::dispatch($communication)->onQueue('task-communications');
        }

        $communication->load('user');

        return response()->json([
            'success' => true,
            'message' => $this->communicationStatusMessage($recipients),
            'data' => $this->formatCommunication($communication),
        ]);
    }

    /**
     * @param  array<int, string>  $recipients
     */
    private function communicationStatusMessage(array $recipients): string
    {
        $queued = ! app()->environment('local', 'testing');
        $messages = [];

        if (in_array('responsible', $recipients, true))
        {
            $messages[] = $queued ? __('Email al responsable en cola') : __('Email enviado al responsable');
        }

        if (in_array('client', $recipients, true))
        {
            $messages[] = $queued ? __('Email al cliente en cola') : __('Email enviado al cliente');
        }

        return implode(' y ', $messages);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCommunication(TaskCommunication $communication): array
    {
        $recipients = $communication->recipients ?? [];
        $labels = [];

        if (in_array('responsible', $recipients, true))
        {
            $labels[] = 'Responsable';
        }

        if (in_array('client', $recipients, true))
        {
            $labels[] = 'Cliente';
        }

        return [
            'id' => $communication->id,
            'method' => $communication->method,
            'subject' => $communication->subject,
            'message' => $communication->message,
            'recipients' => $recipients,
            'recipients_display' => implode(', ', $labels),
            'sender_name' => $communication->user?->name ?? 'Sistema',
            'created_at' => $communication->created_at?->toIso8601String(),
            'has_response' => filled($communication->response),
            'response' => $communication->response,
            'response_at' => $communication->response_at?->toIso8601String(),
        ];
    }

    /**
     * Public landing for the client consultation link. Task list only: no times or activity.
     */
    public function showClientConsultation(string $token)
    {
        $communication = $this->findClientConsultation($token);
        if (! $communication)
        {
            return response()->json([
                'success' => false,
                'message' => __('Consulta no encontrada.'),
            ], 404);
        }

        if (! $communication->client_visited_at)
        {
            $communication->update(['client_visited_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->presentClientConsultation($communication),
        ]);
    }

    /**
     * Store the client reply from the public consultation link.
     */
    public function storeClientConsultation(Request $request, string $token)
    {
        $communication = $this->findClientConsultation($token);
        if (! $communication)
        {
            return response()->json([
                'success' => false,
                'message' => __('Consulta no encontrada.'),
            ], 404);
        }

        if ($communication->response)
        {
            return response()->json([
                'success' => false,
                'message' => __('Ya se ha respondido a esta comunicación previamente.'),
            ], 422);
        }

        $validated = $request->validate([
            'response' => 'required|string',
            'action' => 'required|in:respond_todo,mark_complete',
        ]);

        $now = now();
        $task = $communication->task;

        $communication->update([
            'response' => $validated['response'],
            'response_at' => $now,
            'client_responded_at' => $communication->client_visited_at ? $now : null,
        ]);

        $statusToDo = TaskStatus::where('name', 'TO_DO')->value('id');
        $statusDone = TaskStatus::where('name', 'DONE')->value('id');

        if ($validated['action'] === 'respond_todo' && $statusToDo)
        {
            $task->update(['status_id' => $statusToDo]);
        }

        if ($validated['action'] === 'mark_complete' && $statusDone)
        {
            $task->update(['status_id' => $statusDone]);
        }

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

        $communication->refresh();

        return response()->json([
            'success' => true,
            'message' => __('Tu respuesta ha sido enviada correctamente.'),
            'data' => $this->presentClientConsultation($communication),
        ]);
    }

    private function findClientConsultation(string $token): ?TaskCommunication
    {
        if (strlen($token) !== 64)
        {
            return null;
        }

        return TaskCommunication::with(['task.project.enterprise', 'task.status', 'user'])
            ->where('response_token', $token)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentClientConsultation(TaskCommunication $communication): array
    {
        $task = $communication->task;
        $project = $task?->project;
        $tasks = collect();

        if ($project?->board_id)
        {
            $tasks = Task::withoutGlobalScopes()
                ->where('board_id', $project->board_id)
                ->with('status')
                ->orderBy('order')
                ->get()
                ->map(function (Task $item) use ($task)
                {
                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'status' => $item->status?->name,
                        'status_label' => $this->taskStatusLabel($item->status?->name),
                        'is_current' => $task && (int) $item->id === (int) $task->id,
                    ];
                })
                ->values();
        }

        return [
            'enterprise' => $project?->enterprise?->name,
            'project' => $project ? [
                'id' => $project->id,
                'name' => $project->name,
            ] : null,
            'task' => $task ? [
                'id' => $task->id,
                'title' => $task->title,
            ] : null,
            'sender_name' => $communication->user?->name ?? 'Sistema',
            'message' => $communication->message,
            'sent_at' => $communication->created_at?->toIso8601String(),
            'has_response' => filled($communication->response),
            'response' => $communication->response,
            'response_at' => $communication->response_at?->toIso8601String(),
            'tasks' => $tasks,
        ];
    }

    private function taskStatusLabel(?string $name): string
    {
        return match ($name)
        {
            'TO_DO' => 'Por hacer',
            'IN_PROGRESS' => 'En progreso',
            'REVIEW' => 'En revisión',
            'DONE' => 'Completado',
            default => '—',
        };
    }

    /**
     * Soft-delete a task.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para eliminar esta tarea.'),
            ], 403);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => __('Tarea eliminada correctamente.'),
        ]);
    }
}
