<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Time;
use Illuminate\Http\Request;

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
     * Lista las tareas asignadas al usuario autenticado.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Query base: tareas asignadas al usuario
        $query = Task::where('responsible_id', $user->id)
            ->with(['status', 'category', 'project', 'responsible']);

        // Filtros opcionales
        if ($request->has('status_id'))
        {
            $query->where('status_id', $request->status_id);
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
        ]);

        $user = $request->user();

        // Obtener el estado inicial (TO_DO por defecto, o IN_PROGRESS si se inicia el timer)
        $defaultStatus = TaskStatus::where('name', $validated['start_timer'] ?? false ? 'IN_PROGRESS' : 'TO_DO')->first();

        // Crear la tarea
        $task = Task::create([
            'team_id' => $user->currentTeam->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'responsible_id' => $user->id,
            'status_id' => $defaultStatus?->id ?? 1,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
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

        // Verificar si ya hay un tiempo activo para esta tarea por este usuario
        $activeTime = \App\Models\Time::where('task_id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->first();

        if ($activeTime)
        {
            return response()->json([
                'success' => false,
                'message' => __('Ya tienes un registro de tiempo activo para esta tarea.'),
            ], 400);
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

        // Cambiar estado de la tarea a IN_PROGRESS automáticamente
        $inProgressStatus = TaskStatus::where('name', 'IN_PROGRESS')->first();
        if ($inProgressStatus && $task->status_id !== $inProgressStatus->id)
        {
            $task->update(['status_id' => $inProgressStatus->id]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Tarea iniciada correctamente.'),
            'data' => [
                'time_id' => $time->id,
                'task_id' => $task->id,
                'started_at' => $time->start_time->toIso8601String(),
            ],
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
            ],
        ]);
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
}
