<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CursorCloudAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProjectCloudAgentController extends Controller
{
    public function show(Request $request, string $id, CursorCloudAgentService $agents): JsonResponse
    {
        $project = $this->projectForUser($request, $id);
        if ($project instanceof JsonResponse)
        {
            return $project;
        }

        return response()->json([
            'success' => true,
            'data' => $agents->status($project),
        ]);
    }

    public function store(Request $request, string $id, CursorCloudAgentService $agents): JsonResponse
    {
        $project = $this->projectForUser($request, $id);
        if ($project instanceof JsonResponse)
        {
            return $project;
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:200000'],
            'repository_url' => ['nullable', 'url', 'max:500'],
            'task_ids' => ['nullable', 'array', 'max:100'],
            'task_ids.*' => ['integer'],
        ]);

        $repository = $agents->normalizeRepositoryUrl((string) ($validated['repository_url'] ?? data_get($project->data, 'cursor_repository') ?? ''));

        if ($repository === '' || filter_var($repository, FILTER_VALIDATE_URL) === false)
        {
            return response()->json([
                'success' => false,
                'error' => 'repository_required',
                'message' => 'Indica el repositorio del proyecto.',
            ], 422);
        }

        $data = is_array($project->data) ? $project->data : [];
        $data['cursor_repository'] = $repository;
        $project->update(['data' => $data]);

        try
        {
            $agent = $agents->launch($project, $validated['prompt'], $repository);
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        $tracked = $agents->beginTasks($project, $request->user(), $validated['task_ids'] ?? []);
        $data = is_array($project->data) ? $project->data : [];
        $data['cursor_repository'] = $repository;
        $data['cursor_agent'] = [
            'id' => $agent['id'],
            'url' => $agent['url'],
            'branch' => $agent['branch'],
            'phase' => 'processing',
            'task_ids' => $tracked['task_ids'],
            'time_ids' => $tracked['time_ids'],
            'settled' => false,
        ];
        $project->update(['data' => $data]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $agent['id'],
                'url' => $agent['url'],
                'branch' => $agent['branch'],
                'phase' => 'processing',
                'repository_url' => $repository,
            ],
        ]);
    }

    private function projectForUser(Request $request, string $id): Project|JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $project = Project::query()->where('id', $id)->first();

        if (! $project)
        {
            return response()->json([
                'success' => false,
                'error' => 'Proyecto no encontrado',
            ], 404);
        }

        $this->authorize('view', $project);

        return $project;
    }
}
