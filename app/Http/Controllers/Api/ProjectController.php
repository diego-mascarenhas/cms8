<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthorizeProjectBudgetRequest;
use App\Http\Requests\Api\RegenerateProjectBudgetRequest;
use App\Http\Requests\Api\StoreProjectApiRequest;
use App\Http\Requests\Api\StoreProjectFromBriefRequest;
use App\Http\Requests\Api\UpdateProjectApiRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Country;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Models\User;
use App\Services\ProjectBudgetQuoteMailService;
use App\Services\ProjectBudgetSpecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

            if ($request->filled('status_ids'))
            {
                $statusIds = collect(preg_split('/[|,]/', (string) $request->get('status_ids')) ?: [])
                    ->map(fn ($id) => (int) trim((string) $id))
                    ->filter(fn (int $id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if ($statusIds !== [])
                {
                    $query->whereIn('status_id', $statusIds);
                }
            } elseif ($request->filled('status_id'))
            {
                $query->where('status_id', $request->integer('status_id'));
            }

            $query->orderByDesc('id');

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
        $enterpriseError = $this->assertEnterpriseInTeam((int) $data['enterprise_id'], (int) $user->currentTeam->id);
        if ($enterpriseError !== null)
        {
            return $enterpriseError;
        }

        $budgetService = app(ProjectBudgetSpecService::class);
        $project = $this->persistCreatedProject($user, $data, $budgetService);

        return response()->json($this->projectBudgetResponse($project, $budgetService, __('Project created successfully.')), 201);
    }

    /**
     * Create a budget from an existing (or new) client and pasted brief.
     */
    public function storeFromBrief(StoreProjectFromBriefRequest $request): JsonResponse
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
        $this->authorize('createBudget', Project::class);

        $validated = $request->validated();
        $enterprise = $this->resolveBriefEnterprise($user, $validated);
        if ($enterprise instanceof JsonResponse)
        {
            return $enterprise;
        }

        $brief = trim((string) $validated['brief']);
        $timeout = max(60, (int) config('ai.budget_spec_timeout', 180));
        set_time_limit($timeout + 30);

        $budgetService = app(ProjectBudgetSpecService::class);

        try
        {
            $spec = $budgetService->generate($brief, $user->currentTeam, $user);
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $name = trim((string) ($validated['name'] ?? ''));
        if ($name === '')
        {
            $name = trim((string) $enterprise->name);
        }
        if ($name === '')
        {
            $name = __('Budget');
        }

        $project = $this->persistCreatedProject($user, [
            'name' => $name,
            'enterprise_id' => $enterprise->id,
            'description' => (string) ($spec['ai_interpretation'] ?? $brief),
            'data' => [
                'budget_given' => $brief,
                'ai_interpretation' => $spec['ai_interpretation'] ?? '',
                'dimension' => $spec['dimension'] ?? '',
                'estimated_times' => $spec['estimated_times'] ?? '',
                'resources' => $spec['resources'] ?? '',
                'token_consumption' => is_array($spec['token_consumption'] ?? null)
                    ? $spec['token_consumption']
                    : null,
                'suggested_tasks' => is_array($spec['suggested_tasks'] ?? null)
                    ? $spec['suggested_tasks']
                    : [],
            ],
        ], $budgetService);

        $totals = $budgetService->computeQuoteTotals($project);
        if (($totals['grand_total'] ?? 0) > 0)
        {
            $project->update(['price' => $totals['grand_total']]);
            $project->refresh()->load(['client', 'responsible', 'status', 'board']);
        }

        return response()->json($this->projectBudgetResponse($project, $budgetService, __('Project created successfully.')), 201);
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

            return response()->json(array_merge([
                'success' => true,
                'data' => array_merge($project->toArray(), [
                    'quote_contact' => $project->quoteRecipientSummary(),
                    'tasks' => $tasks,
                    'tasks_count' => count($tasks),
                    'total_time_seconds' => $totalSeconds,
                    'total_time_formatted' => gmdate('H:i:s', $totalSeconds),
                    'total_time_hours' => round($totalSeconds / 3600, 2),
                ]),
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'own_only' : 'permission_based'),
            ], $this->budgetPublicUrls($project)));
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

        $validated = $request->validated();

        if ($project->isBudgetContentLocked())
        {
            $contentKeys = array_values(array_diff(array_keys($validated), ['status_id']));

            if ($contentKeys !== [])
            {
                return response()->json([
                    'success' => false,
                    'error' => 'This approved budget can no longer be edited. Only status changes are allowed.',
                ], 422);
            }

            if (! array_key_exists('status_id', $validated))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'This approved budget can no longer be edited. Only status changes are allowed.',
                ], 422);
            }

            $newStatusId = (int) $validated['status_id'];
            if (! $project->canTransitionToStatus($newStatusId))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'That status is not allowed after the budget has been approved.',
                ], 422);
            }

            $project->update(['status_id' => $newStatusId]);
            $project->load(['client', 'responsible', 'status', 'board']);

            return response()->json($this->projectBudgetResponse(
                $project,
                app(ProjectBudgetSpecService::class),
                __('Project status updated successfully.'),
            ));
        }

        if (array_key_exists('status_id', $validated) && ! $user->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can change the project status.',
            ], 403);
        }

        if (array_key_exists('enterprise_id', $validated))
        {
            $enterpriseError = $this->assertEnterpriseInTeam((int) $validated['enterprise_id'], (int) $user->currentTeam->id);
            if ($enterpriseError !== null)
            {
                return $enterpriseError;
            }
        }

        $budgetService = app(ProjectBudgetSpecService::class);
        if (array_key_exists('data', $validated))
        {
            $validated['data'] = $budgetService->hydrateProjectBudgetData(
                is_array($validated['data']) ? $validated['data'] : null,
            );
        }

        $project->update($validated);
        $project->load(['client', 'responsible', 'status', 'board']);

        return response()->json($this->projectBudgetResponse($project, $budgetService, __('Project updated successfully.')));
    }

    /**
     * Mark the quote as authorized and email the public preview to the enterprise contact.
     */
    public function authorizeBudget(
        AuthorizeProjectBudgetRequest $request,
        string $id,
        ProjectBudgetQuoteMailService $mailService,
        ProjectBudgetSpecService $budgetService,
    ): JsonResponse {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $project = Project::with(['enterprise.contacts', 'team', 'status', 'client', 'responsible'])->findOrFail($id);

        if ((int) $project->status_id === ProjectStatus::STATUS_BUDGET)
        {
            $project->status_id = ProjectStatus::STATUS_BUDGETED;
            $project->save();
        }

        try
        {
            $project = $mailService->authorizeAndSend($project, $user);
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        $project->load(['client', 'responsible', 'status', 'board']);

        return response()->json($this->projectBudgetResponse(
            $project,
            $budgetService,
            __('Quote authorized and emailed to the enterprise contact.'),
        ));
    }

    /**
     * Generate a new estimate from extra notes without replacing the original brief.
     */
    public function regenerateBudget(
        RegenerateProjectBudgetRequest $request,
        string $id,
        ProjectBudgetSpecService $budgetService,
    ): JsonResponse {
        $user = $request->user();
        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $project = Project::with(['enterprise', 'team', 'status', 'client', 'responsible'])->findOrFail($id);
        $this->authorize('update', $project);
        if (data_get($project->data, 'budget_client_response.status') === 'accepted' || $project->isBudgetApproved())
        {
            return response()->json([
                'success' => false,
                'error' => __('This quote was already answered.'),
            ], 422);
        }

        try
        {
            $spec = $budgetService->regenerateWithAddedContext($project, (string) $request->validated('note'), $user);
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        $includedTasks = is_array($spec['suggested_tasks'] ?? null) ? $spec['suggested_tasks'] : [];
        $data = (array) ($project->data ?? []);
        $originalBrief = trim((string) ($data['budget_given'] ?? ''));
        $data = array_merge($data, [
            'budget_given' => $originalBrief !== '' ? $originalBrief : (string) ($spec['budget_given'] ?? ''),
            'ai_interpretation' => $spec['ai_interpretation'] ?? ($data['ai_interpretation'] ?? ''),
            'dimension' => $spec['dimension'] ?? ($data['dimension'] ?? ''),
            'estimated_times' => $spec['estimated_times'] ?? ($data['estimated_times'] ?? ''),
            'resources' => $spec['resources'] ?? ($data['resources'] ?? ''),
            'token_consumption' => $spec['token_consumption'] ?? ($data['token_consumption'] ?? null),
            'suggested_tasks' => $includedTasks,
            'estimate_notes' => $spec['estimate_notes'] ?? ($data['estimate_notes'] ?? []),
            'budget_preview_token' => $data['budget_preview_token'] ?? \Illuminate\Support\Str::random(48),
        ]);
        if (($data['budget_client_response']['status'] ?? null) === 'reformulation_requested')
        {
            unset($data['budget_client_response']);
        }

        $price = collect($includedTasks)->sum(fn ($task) => (float) ($task['unit_price'] ?? 0));
        $project->fill([
            'description' => $spec['ai_interpretation'] ?? $project->description,
            'data' => $data,
            'price' => $price > 0 ? $price : $project->price,
            'status_id' => ProjectStatus::STATUS_BUDGETED,
        ])->save();
        $project->load(['client', 'responsible', 'status', 'board']);

        return response()->json($this->projectBudgetResponse(
            $project,
            $budgetService,
            __('New estimate generated. The original brief was kept and your note was added to the context.'),
        ));
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
     * Summary cards for project list (same groups as web backend).
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        if (! $user->can('viewAny', Project::class))
        {
            return response()->json([
                'success' => false,
                'error' => 'No tienes permisos para ver proyectos',
            ], 403);
        }

        $query = Project::query();
        $filterCallback = \App\Policies\ProjectPolicy::getQueryFilter($user);
        $query = $filterCallback($query);

        $statusCounts = $query
            ->selectRaw('status_id, COUNT(*) as count')
            ->groupBy('status_id')
            ->pluck('count', 'status_id');

        $totalProjects = (int) $statusCounts->sum();

        $cards = [
            [
                'key' => 'budget',
                'label_key' => 'BUDGET',
                'label' => __('project_status.BUDGET'),
                'status_ids' => [1],
                'tone' => 'secondary',
                'icon' => 'pencil-plus',
            ],
            [
                'key' => 'budgeted',
                'label_key' => 'BUDGETED',
                'label' => __('project_status.BUDGETED'),
                'status_ids' => [2],
                'tone' => 'warning',
                'icon' => 'file-description',
            ],
            [
                'key' => 'in_progress',
                'label_key' => 'IN_PROGRESS',
                'label' => __('project_status.IN_PROGRESS'),
                'status_ids' => ProjectStatus::inProgressStatusIds(),
                'tone' => 'primary',
                'icon' => 'player-play',
            ],
            [
                'key' => 'to_invoice',
                'label_key' => 'TO_INVOICE',
                'label' => __('project_status.TO_INVOICE'),
                'status_ids' => [10, 11],
                'tone' => 'info',
                'icon' => 'receipt',
            ],
        ];

        $cards = array_map(function (array $card) use ($statusCounts)
        {
            $count = 0;
            foreach ($card['status_ids'] as $statusId)
            {
                $count += (int) ($statusCounts[$statusId] ?? 0);
            }

            $card['count'] = $count;

            return $card;
        }, $cards);

        // Percentages are relative to the projects shown in these cards,
        // not every project in the team (other statuses would dilute the panel).
        $panelTotal = array_sum(array_column($cards, 'count'));

        $cards = array_map(function (array $card) use ($panelTotal)
        {
            $card['percentage'] = $panelTotal > 0
                ? round(($card['count'] / $panelTotal) * 100, 2)
                : 0;

            return $card;
        }, $cards);

        return response()->json([
            'success' => true,
            'data' => [
                'total_projects' => $totalProjects,
                'panel_total' => $panelTotal,
                'cards' => $cards,
            ],
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
            'responsible_id' => $task->responsible_id,
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCreatedProject(User $user, array $data, ProjectBudgetSpecService $budgetService): Project
    {
        $data['team_id'] = $user->currentTeam->id;
        $data['real_name'] = $data['real_name'] ?? $data['name'];
        $data['responsible_id'] = $data['responsible_id'] ?? $user->id;
        $data['status_id'] = $data['status_id'] ?? ProjectStatus::STATUS_BUDGET;
        $data['data'] = $budgetService->hydrateProjectBudgetData($data['data'] ?? null);

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

        return $project;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveBriefEnterprise(User $user, array $validated): Enterprise|JsonResponse
    {
        $enterpriseId = isset($validated['enterprise_id']) ? (int) $validated['enterprise_id'] : 0;
        if ($enterpriseId > 0)
        {
            $enterpriseError = $this->assertEnterpriseInTeam($enterpriseId, (int) $user->currentTeam->id);
            if ($enterpriseError !== null)
            {
                return $enterpriseError;
            }

            return Enterprise::withoutGlobalScopes()->findOrFail($enterpriseId);
        }

        $this->authorize('create', Enterprise::class);
        $this->authorize('create', Contact::class);

        $businessName = trim((string) ($validated['business_name'] ?? $validated['client_name'] ?? ''));
        $contactName = trim((string) ($validated['contact_name'] ?? ''));
        $surname = trim((string) ($validated['surname'] ?? ''));
        $email = strtolower(trim((string) ($validated['email'] ?? '')));
        $phone = trim((string) ($validated['phone'] ?? ''));
        $country = trim((string) ($validated['country'] ?? ''));

        if ($businessName === '')
        {
            $businessName = trim($contactName.' '.$surname);
        }

        if ($businessName === '')
        {
            return response()->json([
                'success' => false,
                'error' => __('The client is required.'),
            ], 422);
        }

        $team = $user->currentTeam;
        $contact = $this->upsertBriefContact($user, [
            'name' => $contactName !== '' ? $contactName : $businessName,
            'surname' => $surname,
            'email' => $email,
            'phone' => $phone,
            'country' => $country,
        ]);

        $enterprise = $email !== ''
            ? Enterprise::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first()
            : null;

        if (! $enterprise)
        {
            $enterprise = Enterprise::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => $businessName,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'country' => $country !== '' ? $country : null,
                'type_id' => 1,
                'status_id' => 1,
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
            ]);
        }

        if (! $contact->enterprises()->where('enterprises.id', $enterprise->id)->exists())
        {
            $contact->enterprises()->attach($enterprise->id, ['position' => 'Contact']);
        }

        if (! $contact->current_enterprise_id)
        {
            $contact->forceFill(['current_enterprise_id' => $enterprise->id])->save();
        }

        $categoryIds = Category::onlyExistingIds($validated['category_ids'] ?? []);
        if ($categoryIds !== [])
        {
            $categoryIds = Category::query()
                ->whereIn('id', $categoryIds)
                ->where(function ($query) use ($team)
                {
                    $query->whereNull('team_id')->orWhere('team_id', $team->id);
                })
                ->pluck('id')
                ->all();
            $contact->categories()->sync($categoryIds);
        }

        return $enterprise;
    }

    /**
     * @param  array{name: string, surname: string, email: string, phone: string, country: string}  $payload
     */
    private function upsertBriefContact(User $user, array $payload): Contact
    {
        $team = $user->currentTeam;
        $email = $payload['email'];

        $contact = $email !== ''
            ? Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first()
            : null;

        $countryId = $this->resolveContactCountryId($payload['country']);

        $attributes = [
            'name' => $payload['name'],
            'surname' => $payload['surname'] !== '' ? $payload['surname'] : null,
            'phone' => $payload['phone'] !== '' ? $payload['phone'] : null,
        ];
        if ($countryId !== null)
        {
            $attributes['country'] = $countryId;
        }

        if ($contact)
        {
            $contact->fill($attributes)->save();

            return $contact;
        }

        return Contact::withoutGlobalScopes()->create([
            ...$attributes,
            'team_id' => $team->id,
            'email' => $email !== '' ? $email : null,
            'status_id' => ContactStatus::query()->where('name', 'Lead')->value('id')
                ?? ContactStatus::query()->value('id'),
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) [
                'source' => 'estimator_app',
                'captured_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function resolveContactCountryId(string $country): ?int
    {
        $code = strtolower(trim($country));
        if ($code === '')
        {
            return null;
        }

        $id = Country::query()->whereRaw('LOWER(code) = ?', [$code])->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function assertEnterpriseInTeam(int $enterpriseId, int $teamId): ?JsonResponse
    {
        $exists = Enterprise::withoutGlobalScopes()
            ->where('id', $enterpriseId)
            ->where('team_id', $teamId)
            ->exists();

        if ($exists)
        {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => 'The selected client does not belong to the current team.',
        ], 422);
    }

    /**
     * @return array{preview_url: string|null, download_url: string|null}
     */
    private function budgetPublicUrls(Project $project): array
    {
        $token = data_get($project->data, 'budget_preview_token');

        if (! is_string($token) || $token === '')
        {
            return [
                'preview_url' => null,
                'download_url' => null,
            ];
        }

        return \App\Support\BudgetPreviewUrl::pair($token, $project);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectBudgetResponse(Project $project, ProjectBudgetSpecService $budgetService, string $message): array
    {
        $payload = $project->toArray();
        $payload['quote_contact'] = $project->quoteRecipientSummary();

        return array_merge([
            'success' => true,
            'data' => $payload,
            'message' => $message,
            'totals' => $budgetService->computeQuoteTotals($project),
        ], $this->budgetPublicUrls($project));
    }
}
