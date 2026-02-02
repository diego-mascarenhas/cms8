<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the user's team projects.
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        if (! $user)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado',
            ], 401);
        }

        // Check if user has a current team
        if (! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        try
        {
            // Check if user can view any projects
            if (! $user->can('viewAny', Project::class))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver proyectos',
                ], 403);
            }

            // Build base query with policy-based filtering
            $query = Project::with(['client', 'responsible']);

            // Apply role-based filtering using Policy
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
            \Log::error('Error obteniendo proyectos vía API', [
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
     * Display the specified project.
     */
    public function show(Request $request, string $id)
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
            // Find the project first
            $project = Project::where('id', $id)
                ->with(['client', 'responsible'])
                ->firstOrFail();

            // Check if user can view this specific project
            if (! $user->can('view', $project))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver este proyecto',
                ], 403);
            }

            // Get tasks associated with this project (via board_id)
            $tasks = [];
            $totalSeconds = 0;

            if ($project->board_id)
            {
                $projectTasks = \App\Models\Task::where('board_id', $project->board_id)
                    ->with(['status', 'responsible'])
                    ->get();

                $tasks = $projectTasks->map(function ($task) use (&$totalSeconds)
                {
                    // Calculate time for this task
                    $taskTime = \App\Models\Time::where('task_id', $task->id)
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
                        'status' => [
                            'id' => $task->status?->id,
                            'name' => $task->status?->name,
                            'translated_name' => $task->status?->translated_name,
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
            \Log::error('Error obteniendo proyecto vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'contact_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
