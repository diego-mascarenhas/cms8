<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CaptureProjectFunnelLeadRequest;
use App\Http\Requests\Api\ChatProjectFunnelRequest;
use App\Http\Requests\Api\GuideProjectFunnelRequest;
use App\Http\Requests\Api\QuoteProjectFunnelRequest;
use App\Http\Requests\Api\SubmitProjectFunnelRequest;
use App\Jobs\GenerateProjectFunnelQuoteJob;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Services\ProjectBudgetSpecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Strategic growth tips shown while the AI quote is generating.
     */
    public function strategyTips(): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        $steps = collect(config('strategy.steps', []))
            ->map(fn (array $step): array => [
                'number' => (int) ($step['number'] ?? 0),
                'title' => (string) ($step['title'] ?? ''),
                'points' => array_values(array_map('strval', $step['points'] ?? [])),
                'tip' => (string) ($step['tip'] ?? ''),
                'group' => (string) ($step['group'] ?? ''),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'title' => (string) config('strategy.title', 'Strategic Growth Framework'),
                'steps' => $steps,
            ],
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
     * Persists enterprise + BUDGET project first, then queues AI generation (poll via quoteStatus).
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
        $businessName = trim((string) ($request->validated('business_name') ?? ''));
        $projectName = trim((string) ($request->validated('project_name')
            ?: ($businessName !== '' ? $businessName : ($name.' '.$surname.' — proyecto'))));

        try
        {
            [$contact, $enterprise, $project] = DB::transaction(function () use ($team, $name, $surname, $email, $brief, $projectName, $businessName)
            {
                $contact = $this->upsertLeadContact($team, $name, $surname, $email);
                $enterprise = $this->ensureEnterpriseForLead(
                    $team,
                    $contact,
                    $name,
                    $surname,
                    $email,
                    $businessName !== '' ? $businessName : null,
                );
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

        GenerateProjectFunnelQuoteJob::dispatch($project->id, $team->id, $contact->id, $brief);

        $project->refresh();

        $pollToken = $this->encryptPollToken([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'enterprise_id' => $enterprise->id,
            'contact_id' => $contact->id,
            'brief' => $brief,
            'project_name' => $projectName,
        ]);

        return $this->quoteStatusResponse($project, $pollToken, $brief, $projectName, $contact->id, $enterprise->id);
    }

    /**
     * Poll AI quote generation status for a previously started funnel quote.
     */
    public function quoteStatus(Request $request): JsonResponse
    {
        $team = $this->resolveFunnelTeam();
        if (! $team)
        {
            return $this->funnelNotConfiguredResponse();
        }

        $pollToken = trim((string) $request->query('poll_token', $request->input('poll_token', '')));
        if ($pollToken === '')
        {
            return response()->json([
                'success' => false,
                'message' => __('Invalid or expired quote. Please generate a new estimate.'),
            ], 422);
        }

        try
        {
            $payload = $this->decryptPollToken($pollToken);
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

        $project = Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('id', (int) ($payload['project_id'] ?? 0))
            ->first();

        if (! $project)
        {
            return response()->json([
                'success' => false,
                'message' => __('Invalid or expired quote. Please generate a new estimate.'),
            ], 422);
        }

        return $this->quoteStatusResponse(
            $project,
            $pollToken,
            (string) ($payload['brief'] ?? ''),
            (string) ($payload['project_name'] ?? $project->name),
            (int) ($payload['contact_id'] ?? 0),
            (int) ($payload['enterprise_id'] ?? 0),
        );
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
        $businessName = trim((string) ($request->validated('business_name') ?? ''));
        $projectName = trim((string) ($request->validated('project_name')
            ?: ($payload['project_name'] ?? '')
            ?: ($businessName !== '' ? $businessName : ($name.' '.$surname.' — proyecto'))));

        $existingProjectId = (int) ($payload['project_id'] ?? 0);

        try
        {
            $result = DB::transaction(function () use ($team, $name, $surname, $email, $brief, $projectName, $businessName, $spec, $existingProjectId)
            {
                $contact = $this->upsertLeadContact($team, $name, $surname, $email);
                $enterprise = $this->ensureEnterpriseForLead(
                    $team,
                    $contact,
                    $name,
                    $surname,
                    $email,
                    $businessName !== '' ? $businessName : null,
                );

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
                    'token_consumption' => is_array($spec['token_consumption'] ?? null)
                        ? $spec['token_consumption']
                        : $this->budgetSpecService->buildTokenConsumption($includedTasks),
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
        ?string $businessName = null,
    ): Enterprise {
        $personName = trim($name.' '.$surname);
        $enterpriseName = ($businessName !== null && trim($businessName) !== '')
            ? trim($businessName)
            : $personName;

        $enterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $enterprise)
        {
            $enterprise = Enterprise::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => $enterpriseName,
                'email' => $email,
                'type_id' => 1,
                'status_id' => 1,
                'creator_id' => $team->user_id,
                'responsible_id' => $team->user_id,
            ]);
        } elseif (
            $businessName !== null
            && trim($businessName) !== ''
            && in_array(trim((string) $enterprise->name), [$personName, $name, trim($name)], true)
        ) {
            // Upgrade placeholder person-name enterprise when the chat later provides a business name.
            $enterprise->forceFill(['name' => trim($businessName)])->save();
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
                'token_consumption' => [
                    'notes' => '',
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'total_tokens' => 0,
                    'cost_euros' => 0,
                    'savings_percent' => 57,
                    'billable_euros' => 0,
                    'currency' => 'EUR',
                ],
                'suggested_tasks' => [],
                'budget_preview_token' => Str::random(48),
                'funnel' => [
                    'source' => 'projects_funnel',
                    'contact_id' => $contact->id,
                    'drafted_at' => now()->toIso8601String(),
                    'quote_status' => 'queued',
                    'quote_error' => null,
                ],
            ],
            'price' => null,
            'responsible_id' => $team->user_id,
            'status_id' => 1, // BUDGET
        ]);
    }

    /**
     * @param  array{team_id: int, project_id: int, enterprise_id: int, contact_id: int, brief: string, project_name: string}  $payload
     */
    private function encryptPollToken(array $payload): string
    {
        return Crypt::encryptString(json_encode([
            ...$payload,
            'created_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptPollToken(string $token): array
    {
        try
        {
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable)
        {
            throw new RuntimeException(__('Invalid or expired quote. Please generate a new estimate.'));
        }

        if (! is_array($decoded) || empty($decoded['project_id']))
        {
            throw new RuntimeException(__('Invalid or expired quote. Please generate a new estimate.'));
        }

        return $decoded;
    }

    private function quoteStatusResponse(
        Project $project,
        string $pollToken,
        string $brief,
        string $projectName,
        int $contactId,
        int $enterpriseId,
    ): JsonResponse {
        $data = (array) ($project->data ?? []);
        $funnel = is_array($data['funnel'] ?? null) ? $data['funnel'] : [];
        $status = (string) ($funnel['quote_status'] ?? 'queued');

        if ($status === 'failed')
        {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => (string) ($funnel['quote_error'] ?? __('Could not generate the estimate. Please try again.')),
                'poll_token' => $pollToken,
                'data' => [
                    'project_id' => $project->id,
                    'enterprise_id' => $enterpriseId,
                    'contact_id' => $contactId,
                ],
            ], 422);
        }

        if ($status === 'ready' && is_array($data['funnel_spec'] ?? null))
        {
            $spec = $data['funnel_spec'];
            $quoteToken = Crypt::encryptString(json_encode([
                'team_id' => $project->team_id,
                'brief' => $brief !== '' ? $brief : (string) ($data['budget_given'] ?? ''),
                'project_name' => $projectName,
                'project_id' => $project->id,
                'enterprise_id' => $enterpriseId,
                'contact_id' => $contactId,
                'spec' => $spec,
                'created_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR));

            $safe = $this->budgetSpecService->toClientSafe($spec);

            return response()->json([
                'success' => true,
                'status' => 'ready',
                'quote_token' => $quoteToken,
                'poll_token' => $pollToken,
                'data' => array_merge($safe, [
                    'project_id' => $project->id,
                    'enterprise_id' => $enterpriseId,
                    'contact_id' => $contactId,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 'processing',
            'poll_token' => $pollToken,
            'data' => [
                'project_id' => $project->id,
                'enterprise_id' => $enterpriseId,
                'contact_id' => $contactId,
            ],
        ]);
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
