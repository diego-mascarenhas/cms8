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
    public function store(Request $request, string $id, CursorCloudAgentService $agents): JsonResponse
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

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:200000'],
            'repository_url' => ['nullable', 'url', 'max:500'],
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

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $agent['id'],
                'url' => $agent['url'],
                'branch' => $agent['branch'],
                'repository_url' => $repository,
            ],
        ]);
    }
}
