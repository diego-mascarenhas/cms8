<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTemplateApiRequest;
use App\Http\Requests\Api\UpdateTemplateApiRequest;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    use ChecksTeamModule;

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'templates'))
        {
            return $denied;
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Template::query()
            ->where('team_id', $team->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($search !== '')
        {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $paginator = $query->paginate($perPage);
        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        $items = $paginator->getCollection()
            ->map(fn (Template $template) => $template->toApiArray())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreTemplateApiRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'templates'))
        {
            return $denied;
        }

        $validated = $request->validated();

        $template = new Template([
            'name' => $validated['name'],
            'status_id' => $request->boolean('status_id', true),
            'team_id' => $team->id,
            'gjs_data' => [],
        ]);

        $template->mergeGjsData([
            'html' => $validated['html'] ?? null,
            'css' => $validated['css'] ?? null,
            'editor_json' => $validated['editor_json'] ?? null,
        ]);

        $template->save();

        return response()->json([
            'success' => true,
            'data' => $template->fresh()->toApiArray(),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'templates'))
        {
            return $denied;
        }

        $template = Template::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $template)
        {
            return response()->json([
                'success' => false,
                'message' => __('Template not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template->toApiArray(),
        ]);
    }

    public function update(UpdateTemplateApiRequest $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'templates'))
        {
            return $denied;
        }

        $template = Template::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $template)
        {
            return response()->json([
                'success' => false,
                'message' => __('Template not found'),
            ], 404);
        }

        $validated = $request->validated();

        if (array_key_exists('name', $validated))
        {
            $template->name = $validated['name'];
        }

        if ($request->has('status_id'))
        {
            $template->status_id = $request->boolean('status_id');
        }

        $template->mergeGjsData([
            'html' => array_key_exists('html', $validated) ? $validated['html'] : null,
            'css' => array_key_exists('css', $validated) ? $validated['css'] : null,
            'editor_json' => array_key_exists('editor_json', $validated) ? $validated['editor_json'] : null,
        ]);

        $template->save();

        return response()->json([
            'success' => true,
            'data' => $template->fresh()->toApiArray(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'templates'))
        {
            return $denied;
        }

        $template = Template::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $template)
        {
            return response()->json([
                'success' => false,
                'message' => __('Template not found'),
            ], 404);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => __('Template deleted'),
        ]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'templates'))
        {
            return $denied;
        }

        $source = Template::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $source)
        {
            return response()->json([
                'success' => false,
                'message' => __('Template not found'),
            ], 404);
        }

        $gjsData = is_array($source->gjs_data)
            ? (json_decode(json_encode($source->gjs_data), true) ?? [])
            : [];

        $copy = Template::create([
            'name' => Str::limit($source->name.' (copy)', 75, ''),
            'status_id' => true,
            'team_id' => $team->id,
            'gjs_data' => $gjsData,
        ]);

        return response()->json([
            'success' => true,
            'data' => $copy->fresh()->toApiArray(),
        ], 201);
    }
}
