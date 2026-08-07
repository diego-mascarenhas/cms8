<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CaptureProjectFunnelLeadRequest;
use App\Http\Requests\Api\ChatProjectFunnelRequest;
use App\Http\Requests\Api\GuideProjectFunnelRequest;
use App\Http\Requests\Api\QuoteProjectFunnelRequest;
use App\Http\Requests\Api\SubmitProjectFunnelRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Services\ProjectBudgetSpecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectFunnelController extends Controller
{
    public function __construct(private ProjectBudgetSpecService $budgetSpecService) {}

    /**
     * Capture the lead as soon as the visitor completes step 1 (name + email).
     */
    public function lead(CaptureProjectFunnelLeadRequest $request): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        $name = trim((string) $request->validated('name'));
        $surname = trim((string) $request->validated('surname'));
        $email = strtolower(trim((string) $request->validated('email')));

        try
        {
            $contact = $this->upsertLeadContact($team, $name, $surname, $email);
        } catch (\Throwable $e)
        {
            Log::error('Project funnel lead capture failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('Could not save your contact details. Please try again.'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Lead saved.'),
            'data' => [
                'contact_id' => $contact->id,
            ],
        ], 201);
    }

    /**
     * Conversational fundamentación: bot asks what is needed and ticks checklist items.
     */
    public function chat(ChatProjectFunnelRequest $request): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        try
        {
            $turn = $this->budgetSpecService->chatTurn(
                is_array($request->validated('messages')) ? $request->validated('messages') : [],
                $request->validated('project_name'),
                $request->validated('lead_name'),
            );
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $turn,
        ]);
    }

    /**
     * Guided tech fundamentación: evaluate brief against checklist and suggest gaps.
     */
    public function guide(GuideProjectFunnelRequest $request): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        try
        {
            $guide = $this->budgetSpecService->guideBrief((string) $request->validated('brief'));
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $guide,
        ]);
    }

    /**
     * Checklist definitions for the guided Necesidad step (no AI).
     */
    public function requirements(): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'requirements' => array_map(
                    fn (array $r): array => [
                        'key' => $r['key'],
                        'name' => $r['name'],
                        'hint' => $r['hint'],
                        'met' => false,
                        'feedback' => '',
                    ],
                    $this->budgetSpecService->techGuideRequirements(),
                ),
            ],
        ]);
    }

    /**
     * Public AI quote for the client funnel (tasks + times only, no prices).
     * Persists enterprise + BUDGET project before calling the AI so progress is not lost.
     */
    public function quote(QuoteProjectFunnelRequest $request): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        $name = trim((string) $request->validated('name'));
        $surname = trim((string) $request->validated('surname'));
        $email = strtolower(trim((string) $request->validated('email')));
        $brief = (string) $request->validated('brief');
        $projectName = trim((string) ($request->validated('project_name') ?: ($name.' '.$surname.' — proyecto')));

        try
        {
            [$contact, $enterprise, $project] = DB::transaction(function () use ($team, $name, $surname, $email, $brief, $projectName)
            {
                $contact = $this->upsertLeadContact($team, $name, $surname, $email);
                $enterprise = $this->ensureEnterpriseForLead($team, $contact, $name, $surname, $email);
                $project = $this->createDraftBudgetProject($team, $enterprise, $contact, $projectName, $brief);

                return [$contact, $enterprise, $project];
            });
        } catch (\Throwable $e)
        {
            Log::error('Project funnel draft save failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('Could not save your request. Please try again.'),
            ], 500);
        }

        try
        {
            $spec = $this->budgetSpecService->generate($brief, $team);
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'project_id' => $project->id,
                    'enterprise_id' => $enterprise->id,
                    'contact_id' => $contact->id,
                ],
            ], 422);
        }

        $this->applySpecToProject($project, $contact, $brief, $spec);

        $quoteToken = Crypt::encryptString(json_encode([
            'team_id' => $team->id,
            'brief' => $brief,
            'project_name' => $projectName,
            'project_id' => $project->id,
            'enterprise_id' => $enterprise->id,
            'contact_id' => $contact->id,
            'spec' => $spec,
            'created_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        $safe = $this->budgetSpecService->toClientSafe($spec);

        return response()->json([
            'success' => true,
            'quote_token' => $quoteToken,
            'data' => array_merge($safe, [
                'project_id' => $project->id,
                'enterprise_id' => $enterprise->id,
                'contact_id' => $contact->id,
            ]),
        ]);
    }

    /**
     * Persist project budget from the public funnel.
     * Lead should already exist from step 1; prices stay server-side.
     */
    public function submit(SubmitProjectFunnelRequest $request): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        try
        {
            $payload = $this->decryptQuoteToken((string) $request->validated('quote_token'));
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ((int) ($payload['team_id'] ?? 0) !== (int) $team->id)
        {
            return response()->json([
                'success' => false,
                'message' => __('Invalid quote token.'),
            ], 422);
        }

        $spec = is_array($payload['spec'] ?? null) ? $payload['spec'] : [];
        $clientTasks = $request->validated('suggested_tasks') ?? ($this->budgetSpecService->toClientSafe($spec)['suggested_tasks'] ?? []);
        $spec = $this->budgetSpecService->mergeClientTaskEdits($spec, is_array($clientTasks) ? $clientTasks : []);

        $name = trim((string) $request->validated('name'));
        $surname = trim((string) $request->validated('surname'));
        $email = strtolower(trim((string) $request->validated('email')));
        $brief = (string) $request->validated('brief');
        $projectName = trim((string) ($request->validated('project_name')
            ?: ($payload['project_name'] ?? '')
            ?: ($name.' '.$surname.' — proyecto')));

        $existingProjectId = (int) ($payload['project_id'] ?? 0);

        try
        {
            $result = DB::transaction(function () use ($team, $name, $surname, $email, $brief, $projectName, $spec, $existingProjectId)
            {
                $contact = $this->upsertLeadContact($team, $name, $surname, $email);
                $enterprise = $this->ensureEnterpriseForLead($team, $contact, $name, $surname, $email);

                $includedTasks = collect($spec['suggested_tasks'] ?? [])
                    ->filter(fn ($t) => is_array($t) && ($t['included'] ?? true))
                    ->values()
                    ->all();

                $price = collect($includedTasks)
                    ->sum(fn ($t) => is_numeric($t['unit_price'] ?? null) ? (float) $t['unit_price'] : 0);

                $project = null;
                if ($existingProjectId > 0)
                {
                    $project = Project::withoutGlobalScopes()
                        ->where('team_id', $team->id)
                        ->where('id', $existingProjectId)
                        ->first();
                }

                if (! $project)
                {
                    $project = Project::withoutGlobalScopes()->create([
                        'team_id' => $team->id,
                        'enterprise_id' => $enterprise->id,
                        'name' => $projectName,
                        'real_name' => $projectName,
                        'description' => $spec['ai_interpretation'] ?? $brief,
                        'data' => [],
                        'price' => null,
                        'responsible_id' => $team->user_id,
                        'status_id' => 1, // BUDGET
                    ]);
                }

                $projectData = (array) ($project->data ?? []);
                $funnel = is_array($projectData['funnel'] ?? null) ? $projectData['funnel'] : [];
                $projectData = array_merge($projectData, [
                    'budget_given' => $brief,
                    'ai_interpretation' => $spec['ai_interpretation'] ?? '',
                    'dimension' => $spec['dimension'] ?? '',
                    'estimated_times' => $spec['estimated_times'] ?? '',
                    'resources' => $spec['resources'] ?? '',
                    'suggested_tasks' => $includedTasks,
                    'budget_preview_token' => $projectData['budget_preview_token'] ?? Str::random(48),
                    'funnel' => array_merge($funnel, [
                        'source' => 'projects_funnel',
                        'contact_id' => $contact->id,
                        'submitted_at' => now()->toIso8601String(),
                    ]),
                ]);

                $project->fill([
                    'enterprise_id' => $enterprise->id,
                    'name' => $projectName,
                    'real_name' => $projectName,
                    'description' => $spec['ai_interpretation'] ?? $brief,
                    'data' => $projectData,
                    'price' => $price > 0 ? $price : null,
                    'status_id' => 1, // BUDGET
                ])->save();

                if (! $project->board_id)
                {
                    $board = TaskBoard::withoutGlobalScopes()->create([
                        'team_id' => $team->id,
                        'name' => "Project: {$project->name}",
                        'description' => "Task board for project: {$project->name}",
                        'is_default' => false,
                        'order' => 0,
                    ]);
                    $project->update(['board_id' => $board->id]);
                }

                return [
                    'contact_id' => $contact->id,
                    'enterprise_id' => $enterprise->id,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'tasks_count' => count($includedTasks),
                    'total_hours' => collect($includedTasks)->sum(fn ($t) => (float) ($t['estimated_hours'] ?? 0)),
                ];
            });
        } catch (\Throwable $e)
        {
            Log::error('Project funnel submit failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('Could not save your request. Please try again.'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Thanks! We received your scope and will get back to you shortly.'),
            'data' => $result,
        ], 201);
    }

    private function ensureEnterpriseForLead(
        Team $team,
        Contact $contact,
        string $name,
        string $surname,
        string $email,
    ): Enterprise {
        $enterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $enterprise)
        {
            $enterprise = Enterprise::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => trim($name.' '.$surname),
                'email' => $email,
                'type_id' => 1,
                'status_id' => 1,
                'creator_id' => $team->user_id,
                'responsible_id' => $team->user_id,
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

        return $enterprise;
    }

    private function createDraftBudgetProject(
        Team $team,
        Enterprise $enterprise,
        Contact $contact,
        string $projectName,
        string $brief,
    ): Project {
        return Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'name' => $projectName,
            'real_name' => $projectName,
            'description' => $brief,
            'data' => [
                'budget_given' => $brief,
                'ai_interpretation' => '',
                'dimension' => '',
                'estimated_times' => '',
                'resources' => '',
                'suggested_tasks' => [],
                'budget_preview_token' => Str::random(48),
                'funnel' => [
                    'source' => 'projects_funnel',
                    'contact_id' => $contact->id,
                    'drafted_at' => now()->toIso8601String(),
                ],
            ],
            'price' => null,
            'responsible_id' => $team->user_id,
            'status_id' => 1, // BUDGET
        ]);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function applySpecToProject(Project $project, Contact $contact, string $brief, array $spec): void
    {
        $data = (array) ($project->data ?? []);
        $funnel = is_array($data['funnel'] ?? null) ? $data['funnel'] : [];

        $project->fill([
            'description' => (string) ($spec['ai_interpretation'] ?? $brief),
            'data' => array_merge($data, [
                'budget_given' => $brief,
                'ai_interpretation' => $spec['ai_interpretation'] ?? '',
                'dimension' => $spec['dimension'] ?? '',
                'estimated_times' => $spec['estimated_times'] ?? '',
                'resources' => $spec['resources'] ?? '',
                'suggested_tasks' => is_array($spec['suggested_tasks'] ?? null) ? $spec['suggested_tasks'] : [],
                'funnel' => array_merge($funnel, [
                    'source' => 'projects_funnel',
                    'contact_id' => $contact->id,
                    'quoted_at' => now()->toIso8601String(),
                ]),
            ]),
        ])->save();
    }

    private function upsertLeadContact(Team $team, string $name, string $surname, string $email): Contact
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $contact)
        {
            $contact = Contact::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'status_id' => $this->resolveLeadContactStatusId(),
                'creator_id' => $team->user_id,
                'responsible_id' => $team->user_id,
                'data' => (object) [
                    'source' => 'projects_funnel',
                    'captured_at' => now()->toIso8601String(),
                ],
            ]);

            $defaultCategoryId = config('custom.default_contact_category_id');
            if ($defaultCategoryId)
            {
                $exists = Category::withoutGlobalScopes()
                    ->where('id', (int) $defaultCategoryId)
                    ->where('team_id', $team->id)
                    ->exists();
                if ($exists)
                {
                    $contact->categories()->syncWithoutDetaching([(int) $defaultCategoryId]);
                }
            }

            return $contact;
        }

        $data = (array) ($contact->data ?? []);
        if (empty($data['source']))
        {
            $data['source'] = 'projects_funnel';
        }
        if (empty($data['captured_at']))
        {
            $data['captured_at'] = now()->toIso8601String();
        }

        $contact->fill([
            'name' => $name,
            'surname' => $surname,
            'data' => (object) $data,
        ])->save();

        return $contact;
    }

    private function resolveFunnelTeam(): ?Team
    {
        $teamId = (int) config('projects.funnel_team_id');
        if ($teamId <= 0)
        {
            return null;
        }

        return Team::query()->find($teamId);
    }

    private function resolveLeadContactStatusId(): ?int
    {
        $id = ContactStatus::query()->where('name', 'Lead')->value('id')
            ?? ContactStatus::query()->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function funnelNotConfiguredResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Quote funnel is not configured. Set PROJECTS_FUNNEL_TEAM_ID in Humano.'),
        ], 503);
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptQuoteToken(string $token): array
    {
        try
        {
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable)
        {
            throw new RuntimeException(__('Invalid or expired quote. Please generate a new estimate.'));
        }

        if (! is_array($decoded) || ! is_array($decoded['spec'] ?? null))
        {
            throw new RuntimeException(__('Invalid or expired quote. Please generate a new estimate.'));
        }

        return $decoded;
    }
}
