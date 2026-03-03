<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Time;
use Illuminate\Http\Request;

class TimeController extends Controller
{
    /**
     * Registra tiempo usando solo project_key (hash del proyecto). Sin token.
     * Para desarrolladores que solo tienen HUMANO_PROJECT_KEY en su .env.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeByProjectKey(Request $request)
    {
        $validated = $request->validate([
            'project_key'      => 'required|string|size:64',
            'description'      => 'required|string|max:500',
            'duration_seconds' => 'required|integer|min:1',
            'task_id'          => 'nullable|integer',
            'date'             => 'nullable|date',
            'is_billable'      => 'boolean',
        ]);

        $project = Project::findByKey($validated['project_key']);
        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => __('Proyecto no encontrado con la clave indicada.'),
            ], 404);
        }

        $taskId = null;
        if (! empty($validated['task_id'])) {
            $task = Task::withoutGlobalScopes()->where('board_id', $project->board_id)->find($validated['task_id']);
            if ($task) {
                $taskId = $task->id;
            }
        }
        if ($taskId === null && $project->board_id) {
            $firstTask = Task::withoutGlobalScopes()
                ->where('board_id', $project->board_id)
                ->orderBy('order')
                ->orderBy('id')
                ->first();
            $taskId = $firstTask?->id;
        }

        if ($taskId === null) {
            return response()->json([
                'success' => false,
                'message' => __('El proyecto no tiene tareas. Crea al menos una tarea en el tablero del proyecto.'),
            ], 422);
        }

        $task = Task::withoutGlobalScopes()->find($taskId);
        $teamId = $task->team_id;
        $userId = $project->responsible_id ?? $task->responsible_id;
        if (! $userId) {
            $userId = \App\Models\User::withoutGlobalScopes()->where('team_id', $teamId)->value('id');
        }
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => __('No hay usuario asignado al proyecto o al equipo.'),
            ], 422);
        }

        $date = isset($validated['date']) ? \Carbon\Carbon::parse($validated['date']) : now();
        $startTime = $date->copy()->startOfDay();
        $endTime = $startTime->copy()->addSeconds($validated['duration_seconds']);

        $time = Time::withoutGlobalScope('team')->create([
            'team_id'          => $teamId,
            'user_id'          => $userId,
            'task_id'          => $taskId,
            'description'      => $validated['description'],
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_seconds' => $validated['duration_seconds'],
            'is_billable'      => $validated['is_billable'] ?? true,
            'hourly_rate'      => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Tiempo registrado correctamente.'),
            'data'    => [
                'id'                 => $time->id,
                'description'        => $time->description,
                'duration_seconds'   => $time->duration_seconds,
                'duration_formatted' => $time->formatted_duration,
                'duration_hours'     => $time->duration_hours,
                'date'               => $startTime->toDateString(),
                'is_billable'        => $time->is_billable,
                'task_id'            => $time->task_id,
            ],
        ], 201);
    }

    /**
     * Lista el historial de fichajes del usuario autenticado.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Query base: fichajes del usuario
        $query = Time::where('user_id', $user->id)
            ->with(['task', 'task.project', 'task.status']);

        // Filtros opcionales
        if ($request->has('date_from'))
        {
            $query->whereDate('start_time', '>=', $request->date_from);
        }

        if ($request->has('date_to'))
        {
            $query->whereDate('start_time', '<=', $request->date_to);
        }

        if ($request->has('task_id'))
        {
            $query->where('task_id', $request->task_id);
        }

        // Solo fichajes completados por defecto
        if (! $request->has('include_running') || ! $request->include_running)
        {
            $query->whereNotNull('end_time');
        }

        // Ordenar por más reciente primero
        $times = $query
            ->orderBy('start_time', 'desc')
            ->limit($request->input('limit', 50))
            ->get();

        // Transformar a formato API
        $data = $times->map(function ($time)
        {
            return [
                'id' => $time->id,
                'task_id' => $time->task_id,
                'task' => $time->task ? [
                    'id' => $time->task->id,
                    'title' => $time->task->title,
                    'status' => $time->task->status?->translated_name,
                    'project' => $time->task->project ? [
                        'id' => $time->task->project->id,
                        'name' => $time->task->project->name,
                    ] : null,
                ] : null,
                'description' => $time->description,
                'start_time' => $time->start_time?->toISOString(),
                'end_time' => $time->end_time?->toISOString(),
                'duration_seconds' => $time->duration_seconds,
                'duration_formatted' => $time->formatted_duration,
                'duration_hours' => $time->duration_hours,
                'is_running' => $time->isRunning(),
                'is_billable' => $time->is_billable,
                'hourly_rate' => $time->hourly_rate,
                'earnings' => $time->earnings,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * Obtiene el timer activo actual del usuario.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function running(Request $request)
    {
        $runningTimer = Time::getRunningTimer($request->user()->id);

        if ($runningTimer)
        {
            $runningTimer->load('task', 'task.project', 'task.status');

            return response()->json([
                'success' => true,
                'running' => true,
                'data' => [
                    'id' => $runningTimer->id,
                    'task_id' => $runningTimer->task_id,
                    'task' => $runningTimer->task ? [
                        'id' => $runningTimer->task->id,
                        'title' => $runningTimer->task->title,
                        'status' => $runningTimer->task->status?->translated_name,
                        'project' => $runningTimer->task->project ? [
                            'id' => $runningTimer->task->project->id,
                            'name' => $runningTimer->task->project->name,
                        ] : null,
                    ] : null,
                    'description' => $runningTimer->description,
                    'start_time' => $runningTimer->start_time?->toISOString(),
                    'elapsed_seconds' => $runningTimer->start_time ? now()->diffInSeconds($runningTimer->start_time) : 0,
                    'is_billable' => $runningTimer->is_billable,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'running' => false,
            'data' => null,
        ]);
    }

    /**
     * Inicia un nuevo timer para una tarea.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'description' => 'nullable|string|max:255',
        ]);

        // Verificar que el usuario tiene acceso a la tarea
        $task = \App\Models\Task::findOrFail($validated['task_id']);
        if ($task->responsible_id !== $request->user()->id && ! $request->user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para fichar en esta tarea.'),
            ], 403);
        }

        // Idempotency: si el timer actual es para la misma tarea, solo retornarlo
        $runningTimer = Time::getRunningTimer($request->user()->id);
        if ($runningTimer && (int) $runningTimer->task_id === (int) $validated['task_id'])
        {
            $runningTimer->load('task', 'task.project');

            return response()->json([
                'success' => true,
                'message' => __('El timer ya está corriendo para esta tarea.'),
                'data' => [
                    'id' => $runningTimer->id,
                    'task_id' => $runningTimer->task_id,
                    'task' => $runningTimer->task ? [
                        'id' => $runningTimer->task->id,
                        'title' => $runningTimer->task->title,
                    ] : null,
                    'description' => $runningTimer->description,
                    'start_time' => $runningTimer->start_time?->toISOString(),
                    'elapsed_seconds' => now()->diffInSeconds($runningTimer->start_time),
                ],
                'previous_stopped' => false,
            ]);
        }

        // Si hay otro timer corriendo, detenerlo
        $previouslyStopped = false;
        if ($runningTimer)
        {
            $runningTimer->stop();
            $previouslyStopped = true;
        }

        // Crear nuevo timer
        $time = Time::create([
            'team_id' => $request->user()->currentTeam->id,
            'user_id' => $request->user()->id,
            'task_id' => $validated['task_id'],
            'description' => $validated['description'] ?? null,
            'start_time' => now(),
            'is_billable' => true,
        ]);

        $time->load('task', 'task.project');

        return response()->json([
            'success' => true,
            'message' => __('Timer iniciado correctamente.'),
            'data' => [
                'id' => $time->id,
                'task_id' => $time->task_id,
                'task' => $time->task ? [
                    'id' => $time->task->id,
                    'title' => $time->task->title,
                    'project' => $time->task->project ? [
                        'id' => $time->task->project->id,
                        'name' => $time->task->project->name,
                    ] : null,
                ] : null,
                'description' => $time->description,
                'start_time' => $time->start_time?->toISOString(),
                'is_billable' => $time->is_billable,
            ],
            'previous_stopped' => $previouslyStopped,
        ], 201);
    }

    /**
     * Registra una entrada de tiempo con duración conocida (sin timer start/stop).
     * Útil para registrar trabajo realizado desde herramientas externas (MCP, etc.).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description'      => 'required|string|max:500',
            'duration_seconds' => 'required|integer|min:1',
            'task_id'          => 'nullable|exists:tasks,id',
            'date'             => 'nullable|date',
            'is_billable'      => 'boolean',
            'hourly_rate'      => 'nullable|numeric|min:0',
        ]);

        $date      = isset($validated['date']) ? \Carbon\Carbon::parse($validated['date']) : now();
        $startTime = $date->copy()->startOfDay();
        $endTime   = $startTime->copy()->addSeconds($validated['duration_seconds']);

        $user = $request->user();
        $teamId = $user->currentTeam->id ?? null;
        if (isset($validated['task_id'])) {
            $task = \App\Models\Task::find($validated['task_id']);
            if ($task) {
                $teamId = $task->team_id;
            }
        }
        if (! $teamId) {
            return response()->json([
                'success' => false,
                'message' => __('Usuario sin equipo asignado. Asigna un equipo actual o envía task_id para registrar en el proyecto.'),
            ], 422);
        }

        $time = Time::create([
            'team_id'          => $teamId,
            'user_id'          => $user->id,
            'task_id'          => $validated['task_id'] ?? null,
            'description'      => $validated['description'],
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_seconds' => $validated['duration_seconds'],
            'is_billable'      => $validated['is_billable'] ?? true,
            'hourly_rate'      => $validated['hourly_rate'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tiempo registrado correctamente.',
            'data'    => [
                'id'                 => $time->id,
                'description'        => $time->description,
                'duration_seconds'   => $time->duration_seconds,
                'duration_formatted' => $time->formatted_duration,
                'duration_hours'     => $time->duration_hours,
                'date'               => $startTime->toDateString(),
                'is_billable'        => $time->is_billable,
            ],
        ], 201);
    }

    /**
     * Detiene un timer en ejecución.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function stop(Request $request, $id)
    {
        $time = Time::findOrFail($id);

        // Validar que el usuario sea dueño del timer
        if ($time->user_id !== $request->user()->id)
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes permiso para detener este timer.'),
            ], 403);
        }

        // Verificar que el timer esté corriendo
        if (! $time->isRunning())
        {
            return response()->json([
                'success' => false,
                'message' => __('El timer no está corriendo.'),
            ], 400);
        }

        // Detener el timer
        $time->stop();
        $time->load('task', 'task.project');

        return response()->json([
            'success' => true,
            'message' => __('Timer detenido correctamente.'),
            'data' => [
                'id' => $time->id,
                'task_id' => $time->task_id,
                'task' => $time->task ? [
                    'id' => $time->task->id,
                    'title' => $time->task->title,
                    'project' => $time->task->project ? [
                        'id' => $time->task->project->id,
                        'name' => $time->task->project->name,
                    ] : null,
                ] : null,
                'description' => $time->description,
                'start_time' => $time->start_time?->toISOString(),
                'end_time' => $time->end_time?->toISOString(),
                'duration_seconds' => $time->duration_seconds,
                'duration_formatted' => $time->formatted_duration,
                'duration_hours' => $time->duration_hours,
                'is_billable' => $time->is_billable,
                'earnings' => $time->earnings,
            ],
        ]);
    }
}
