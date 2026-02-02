<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
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
     * Inicia el registro de tiempo para una tarea.
     *
     * @param  \Illuminate\Http\Request  $request
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

        // Crear nuevo registro de tiempo
        $time = \App\Models\Time::create([
            'task_id' => $id,
            'user_id' => $request->user()->id,
            'team_id' => $request->user()->currentTeam->id,
            'start_time' => now(),
        ]);

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
     * @param  \Illuminate\Http\Request  $request
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
        $activeTime = \App\Models\Time::where('task_id', $id)
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

        // Detener el tiempo
        $activeTime->update([
            'end_time' => now(),
        ]);

        // Calcular duración
        $duration = $activeTime->start_time->diffInSeconds($activeTime->end_time);

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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function time(Request $request, $id)
    {
        $task = Task::with(['times' => function ($query)
        {
            $query->whereNotNull('end_time')->orderBy('start_time', 'desc');
        }, 'project'])->findOrFail($id);

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
}
