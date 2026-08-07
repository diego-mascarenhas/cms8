<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectApiRequest;
use App\Http\Requests\Api\UpdateProjectApiRequest;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Time;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    /**
     * Display a listing of the user's team projects.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado',
            ], 401);
        }

        if (! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        try
        {
            if (! $user->can('viewAny', Project::class))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver proyectos',
                ], 403);
            }

            $query = Project::with(['client', 'responsible', 'status', 'board']);

            $filterCallback = \App\Policies\ProjectPolicy::getQueryFilter($user);
            $query = $filterCallback($query);

            $search = $request->get('search');
            if (is_string($search) && trim($search) !== '')
            {
                $term = '%'.trim($search).'%';
                $query->where(function ($q) use ($term)
                {
                    $q->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            }

            if ($request->filled('status_id'))
            {
                $query->where('status_id', $request->integer('status_id'));
            }

            $query->orderBy('name');

            $projects = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $projects,
                'team' => [
                    'id' => $user->currentTeam->id,
                    'name' => $user->currentTeam->name,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()->name ?? 'user',
                ],
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'own_only' : 'permission_based'),
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error obteniendo proyectos vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectApiRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $this->authorize('create', Project::class);

        $data = $request->validated();
        $data['team_id'] = $user->currentTeam->id;
        $data['real_name'] = $data['real_name'] ?? $data['name'];

        $project = Project::create($data);

        $board = TaskBoard::create([
            'team_id' => $user->currentTeam->id,
            'name' => "Project: {$project->name}",
            'description' => "Task board for project: {$project->name}",
            'is_default' => false,
            'order' => 0,
        ]);

        $project->update(['board_id' => $board->id]);
        $project->load(['client', 'responsible', 'status', 'board']);

        return response()->json([
            'success' => true,
            'data' => $project,
            'message' => 'Project created successfully',
        ], 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        try
        {
            $project = Project::where('id', $id)
                ->with(['client', 'responsible', 'status', 'board'])
                ->firstOrFail();

            if (! $user->can('view', $project))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver este proyecto',
                ], 403);
            }

            $tasks = [];
            $totalSeconds = 0;

            if ($project->board_id)
            {
                $projectTasks = Task::where('board_id', $project->board_id)
                    ->with(['status', 'responsible'])
                    ->defaultOrder()
                    ->get();

                $tasks = $projectTasks->map(function ($task) use (&$totalSeconds)
                {
                    $taskTime = Time::where('task_id', $task->id)
                        ->whereNotNull('end_time')
                        ->get()
                        ->sum(function ($time)
                        {
                            return $time->start_time->diffInSeconds($time->end_time);
                        });

                    $totalSeconds += $taskTime;

                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'order' => $task->order,
                        'estimated_hours' => $task->estimated_hours,
                        'start_date' => $task->start_date?->format('Y-m-d'),
                        'due_date' => $task->due_date?->format('Y-m-d'),
                        'status' => [
                            'id' => $task->status?->id,
                            'name' => $task->status?->name,
                            'translated_name' => $task->status?->translated_name,
                            'color' => $task->status?->color,
                            'label_class' => $task->status?->label_class,
                        ],
                        'responsible' => [
                            'id' => $task->responsible?->id,
                            'name' => $task->responsible?->name,
                        ],
                        'time_seconds' => $taskTime,
                        'time_formatted' => gmdate('H:i:s', $taskTime),
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'data' => array_merge($project->toArray(), [
                    'tasks' => $tasks,
                    'tasks_count' => count($tasks),
                    'total_time_seconds' => $totalSeconds,
                    'total_time_formatted' => gmdate('H:i:s', $totalSeconds),
                    'total_time_hours' => round($totalSeconds / 3600, 2),
                ]),
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'own_only' : 'permission_based'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            return response()->json([
                'success' => false,
                'error' => 'Proyecto no encontrado',
            ], 404);
        } catch (\Exception $e)
        {
            Log::error('Error obteniendo proyecto vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'project_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectApiRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $project->update($request->validated());
        $project->load(['client', 'responsible', 'status', 'board']);

        return response()->json([
            'success' => true,
            'data' => $project,
            'message' => 'Project updated successfully',
        ]);
    }

    /**
     * Soft-delete the specified project.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $project = Project::findOrFail($id);
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully',
        ]);
    }

    /**
     * List project statuses for form selects.
     */
    public function statuses(): JsonResponse
    {
        $statuses = ProjectStatus::query()
            ->orderBy('id')
            ->get()
            ->map(function (ProjectStatus $status)
            {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'translated_name' => $status->translated_name,
                    'label_class' => $status->label_class,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Board payload: columns (task statuses) + tasks for the project.
     */
    public function board(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $project = Project::with('board')->findOrFail($id);
        $this->authorize('view', $project);

        if (! $project->board_id)
        {
            $board = TaskBoard::create([
                'team_id' => $user->currentTeam->id,
                'name' => "Project: {$project->name}",
                'description' => "Task board for project: {$project->name}",
                'is_default' => false,
                'order' => 0,
            ]);
            $project->update(['board_id' => $board->id]);
            $project->setRelation('board', $board);
        }

        $tasks = Task::where('board_id', $project->board_id)
            ->with(['status', 'responsible'])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $columns = TaskStatus::orderBy('order')
            ->get()
            ->map(function (TaskStatus $status) use ($tasks)
            {
                $columnTasks = $tasks
                    ->where('status_id', $status->id)
                    ->values()
                    ->map(fn (Task $task) => $this->mapBoardTask($task));

                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'translated_name' => $status->translated_name,
                    'color' => $status->color,
                    'label_class' => $status->label_class,
                    'order' => $status->order,
                    'tasks' => $columnTasks,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'board_id' => $project->board_id,
                ],
                'board' => [
                    'id' => $project->board?->id,
                    'name' => $project->board?->name,
                ],
                'columns' => $columns,
            ],
        ]);
    }

    /**
     * Move / reorder a task on the project board.
     */
    public function reorderBoard(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        if (! $project->board_id)
        {
            return response()->json([
                'success' => false,
                'error' => 'El proyecto no tiene tablero',
            ], 422);
        }

        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'status_id' => ['required', 'integer', 'exists:task_statuses,id'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $task = Task::where('id', $validated['task_id'])
            ->where('board_id', $project->board_id)
            ->first();

        if (! $task)
        {
            return response()->json([
                'success' => false,
                'error' => 'La tarea no pertenece a este proyecto',
            ], 404);
        }

        $order = $validated['order'] ?? ((int) Task::where('board_id', $project->board_id)
            ->where('status_id', $validated['status_id'])
            ->max('order') + 1);

        $task->update([
            'status_id' => $validated['status_id'],
            'order' => $order,
        ]);

        $task->load(['status', 'responsible']);

        return response()->json([
            'success' => true,
            'data' => $this->mapBoardTask($task),
            'message' => 'Task reordered successfully',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBoardTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'order' => $task->order,
            'estimated_hours' => $task->estimated_hours,
            'start_date' => $task->start_date?->format('Y-m-d'),
            'due_date' => $task->due_date?->format('Y-m-d'),
            'status_id' => $task->status_id,
            'status' => [
                'id' => $task->status?->id,
                'name' => $task->status?->name,
                'translated_name' => $task->status?->translated_name,
                'color' => $task->status?->color,
                'label_class' => $task->status?->label_class,
            ],
            'responsible' => $task->responsible ? [
                'id' => $task->responsible->id,
                'name' => $task->responsible->name,
                'email' => $task->responsible->email,
            ] : null,
        ];
    }
}
