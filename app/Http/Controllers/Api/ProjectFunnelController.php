<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DnsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CaptureProjectFunnelLeadRequest;
use App\Http\Requests\Api\ChatProjectFunnelRequest;
use App\Http\Requests\Api\GuideProjectFunnelRequest;
use App\Http\Requests\Api\QuoteProjectFunnelRequest;
use App\Http\Requests\Api\SubmitProjectFunnelRequest;
use App\Http\Requests\Api\UpdateProjectFunnelChatPromptRequest;
use App\Http\Requests\Api\UpdateProjectFunnelSenderRequest;
use App\Http\Requests\Api\UpdateProjectFunnelTokenPricingRequest;
use App\Jobs\GenerateProjectFunnelQuoteJob;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Services\ProjectBudgetQuoteMailService;
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
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $validated = $request->validated();
        $name = trim((string) $validated['name']);
        $surname = trim((string) ($validated['surname'] ?? ''));
        $email = strtolower(trim((string) $validated['email']));
        $intake = $this->intakeFromValidated($validated);

        try
        {
            $contact = $this->upsertLeadContact($team, $name, $surname, $email, $intake);
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
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        try
        {
            $turn = $this->budgetSpecService->chatTurn(
                is_array($request->validated('messages')) ? $request->validated('messages') : [],
                $request->validated('project_name'),
                $request->validated('lead_name'),
                $team,
                $request->validated('context'),
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
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        try
        {
            $guide = $this->budgetSpecService->guideBrief((string) $request->validated('brief'), $team);
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
     * Editable Necesidad prompt for the public funnel team (internal login).
     */
    public function showChatPrompt(Request $request): JsonResponse
    {
        $team = $this->authorizeFunnelEditor($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $prompt = $this->budgetSpecService->ensureBudgetChatPrompt($team);

        return response()->json([
            'success' => true,
            'data' => [
                'section_key' => $prompt->section_key,
                'section_label' => $prompt->section_label,
                'prompt_instruction' => $prompt->prompt_instruction,
                'helper_text' => $prompt->helper_text,
            ],
        ]);
    }

    /**
     * Persist the Necesidad prompt used by the public estimator chat.
     */
    public function updateChatPrompt(UpdateProjectFunnelChatPromptRequest $request): JsonResponse
    {
        $team = $this->authorizeFunnelEditor($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $prompt = $this->budgetSpecService->ensureBudgetChatPrompt($team);
        $prompt->forceFill([
            'prompt_instruction' => (string) $request->validated('prompt_instruction'),
            'is_active' => true,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => __('Chat prompt saved.'),
            'data' => [
                'section_key' => $prompt->section_key,
                'section_label' => $prompt->section_label,
                'prompt_instruction' => $prompt->prompt_instruction,
                'helper_text' => $prompt->helper_text,
            ],
        ]);
    }

    public function showSender(Request $request): JsonResponse
    {
        $team = $this->authorizeFunnelEditor($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        return response()->json([
            'success' => true,
            'data' => $this->funnelSenderPayload($team, $request),
        ]);
    }

    public function updateSender(UpdateProjectFunnelSenderRequest $request): JsonResponse
    {
        $team = $this->authorizeFunnelEditor($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if (! $request->user()?->can('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('You are not allowed to configure the quote sender.'),
            ], 403);
        }

        $validated = $request->validated();

        $team->setSetting('mail_from_name', $validated['mail_from_name'], [
            'group' => 'email',
            'type' => 'text',
            'is_encrypted' => false,
        ]);
        $team->setSetting('mail_from_address', $validated['mail_from_address'], [
            'group' => 'email',
            'type' => 'email',
            'is_encrypted' => false,
        ]);

        $team->unsetRelation('settings');
        $team->load('settings');

        return response()->json([
            'success' => true,
            'message' => __('Quote sender saved.'),
            'data' => $this->funnelSenderPayload($team, $request),
        ]);
    }

    public function showTokenPricing(Request $request, ProjectBudgetSpecService $budgetSpec): JsonResponse
    {
        $team = $this->authorizeFunnelEditor($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => $this->funnelTokenPricingPayload($team, $request, $budgetSpec),
        ]);
    }

    public function updateTokenPricing(UpdateProjectFunnelTokenPricingRequest $request, ProjectBudgetSpecService $budgetSpec): JsonResponse
    {
        $team = $this->authorizeFunnelEditor($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if (! $request->user()?->can('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('You are not allowed to configure token pricing.'),
            ], 403);
        }

        $validated = $request->validated();

        $team->setSetting(ProjectBudgetSpecService::SETTING_TOKEN_INPUT_RATE, (string) $validated['input_rate'], [
            'group' => 'estimator',
            'type' => 'number',
            'is_encrypted' => false,
        ]);
        $team->setSetting(ProjectBudgetSpecService::SETTING_TOKEN_OUTPUT_RATE, (string) $validated['output_rate'], [
            'group' => 'estimator',
            'type' => 'number',
            'is_encrypted' => false,
        ]);
        $team->setSetting(
            ProjectBudgetSpecService::SETTING_TOKEN_DISCRIMINATE,
            $validated['discriminate'] ? '1' : '0',
            [
                'group' => 'estimator',
                'type' => 'boolean',
                'is_encrypted' => false,
            ],
        );
        $team->setSetting(
            ProjectBudgetSpecService::SETTING_TOKEN_INCLUDE,
            $validated['include'] ? '1' : '0',
            [
                'group' => 'estimator',
                'type' => 'boolean',
                'is_encrypted' => false,
            ],
        );

        $team->unsetRelation('settings');
        $team->load('settings');

        return response()->json([
            'success' => true,
            'message' => __('Token pricing saved.'),
            'data' => $this->funnelTokenPricingPayload($team, $request, $budgetSpec),
        ]);
    }

    /**
     * Strategic growth tips shown while the AI quote is generating.
     */
    public function strategyTips(Request $request): JsonResponse
    {
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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
    public function requirements(Request $request): JsonResponse
    {
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $validated = $request->validated();
        $name = trim((string) $validated['name']);
        $surname = trim((string) ($validated['surname'] ?? ''));
        $email = strtolower(trim((string) $validated['email']));
        $brief = (string) $validated['brief'];
        $intake = $this->intakeFromValidated($validated);
        $businessName = trim((string) ($intake['business_name'] ?? ''));
        $projectName = trim((string) ($intake['project_name']
            ?: ($businessName !== '' ? $businessName : ($name.' '.$surname.' — proyecto'))));

        try
        {
            [$contact, $enterprise, $project] = DB::transaction(function () use ($team, $name, $surname, $email, $brief, $projectName, $businessName, $intake)
            {
                $contact = $this->upsertLeadContact($team, $name, $surname, $email, $intake);
                $enterprise = $this->ensureEnterpriseForLead(
                    $team,
                    $contact,
                    $name,
                    $surname,
                    $email,
                    $businessName !== '' ? $businessName : null,
                    $intake,
                );
                $project = $this->createDraftBudgetProject($team, $enterprise, $contact, $projectName, $brief, $intake);

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
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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
    public function submit(SubmitProjectFunnelRequest $request, ProjectBudgetQuoteMailService $quoteMail): JsonResponse
    {
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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

        $validated = $request->validated();
        $name = trim((string) $validated['name']);
        $surname = trim((string) ($validated['surname'] ?? ''));
        $email = strtolower(trim((string) $validated['email']));
        $brief = (string) $validated['brief'];
        $intake = $this->intakeFromValidated($validated);
        $businessName = trim((string) ($intake['business_name'] ?? ''));
        $projectName = trim((string) ($intake['project_name']
            ?: ($payload['project_name'] ?? '')
            ?: ($businessName !== '' ? $businessName : ($name.' '.$surname.' — proyecto'))));

        $existingProjectId = (int) ($payload['project_id'] ?? 0);

        try
        {
            $result = DB::transaction(function () use ($team, $name, $surname, $email, $brief, $projectName, $businessName, $spec, $existingProjectId, $intake)
            {
                $contact = $this->upsertLeadContact($team, $name, $surname, $email, $intake);
                $enterprise = $this->ensureEnterpriseForLead(
                    $team,
                    $contact,
                    $name,
                    $surname,
                    $email,
                    $businessName !== '' ? $businessName : null,
                    $intake,
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
                        'intake' => $this->mergeIntake(
                            is_array($funnel['intake'] ?? null) ? $funnel['intake'] : [],
                            $intake,
                        ),
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

                $previewToken = (string) ($projectData['budget_preview_token'] ?? '');

                return [
                    'contact_id' => $contact->id,
                    'enterprise_id' => $enterprise->id,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'tasks_count' => count($includedTasks),
                    'total_hours' => collect($includedTasks)->sum(fn ($t) => (float) ($t['estimated_hours'] ?? 0)),
                    ...$this->publicBudgetUrls($previewToken, $project),
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

        $project = Project::withoutGlobalScopes()
            ->with(['enterprise.contacts', 'team.settings', 'team.owner'])
            ->find($result['project_id'] ?? null);

        $emailed = $project instanceof Project
            ? $quoteMail->trySendAfterFunnelSubmit($project)
            : false;

        $result['emailed'] = $emailed;

        return response()->json([
            'success' => true,
            'message' => $emailed
                ? __('Thanks! We sent the quote details to your email.')
                : __('Thanks! We received your scope and will get back to you shortly.'),
            'data' => $result,
        ], 201);
    }

    /**
     * @param  array<string, string>  $intake
     */
    private function ensureEnterpriseForLead(
        Team $team,
        Contact $contact,
        string $name,
        string $surname,
        string $email,
        ?string $businessName = null,
        array $intake = [],
    ): Enterprise {
        $personName = trim($name.' '.$surname);
        $enterpriseName = ($businessName !== null && trim($businessName) !== '')
            ? trim($businessName)
            : $personName;

        $enterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $phone = trim((string) ($intake['phone'] ?? ''));
        $location = trim((string) ($intake['location'] ?? ''));

        if (! $enterprise)
        {
            $enterprise = Enterprise::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => $enterpriseName,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'locality' => $location !== '' ? $location : null,
                'type_id' => 1,
                'status_id' => 1,
                'creator_id' => $team->user_id,
                'responsible_id' => $team->user_id,
            ]);
        } else
        {
            $updates = [];
            if (
                $businessName !== null
                && trim($businessName) !== ''
                && in_array(trim((string) $enterprise->name), [$personName, $name, trim($name)], true)
            ) {
                $updates['name'] = trim($businessName);
            }
            if ($phone !== '' && trim((string) $enterprise->phone) === '')
            {
                $updates['phone'] = $phone;
            }
            if ($location !== '' && trim((string) $enterprise->locality) === '')
            {
                $updates['locality'] = $location;
            }
            if ($updates !== [])
            {
                $enterprise->forceFill($updates)->save();
            }
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

    /**
     * @param  array<string, string>  $intake
     */
    private function createDraftBudgetProject(
        Team $team,
        Enterprise $enterprise,
        Contact $contact,
        string $projectName,
        string $brief,
        array $intake = [],
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
                    'intake' => $intake,
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

    /**
     * @param  array<string, string>  $intake
     */
    private function upsertLeadContact(Team $team, string $name, string $surname, string $email, array $intake = []): Contact
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $phone = trim((string) ($intake['phone'] ?? ''));

        if (! $contact)
        {
            $contact = Contact::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'status_id' => $this->resolveLeadContactStatusId(),
                'creator_id' => $team->user_id,
                'responsible_id' => $team->user_id,
                'data' => (object) [
                    'source' => 'projects_funnel',
                    'captured_at' => now()->toIso8601String(),
                    'intake' => $intake,
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
        $data['intake'] = $this->mergeIntake(
            is_array($data['intake'] ?? null) ? $data['intake'] : [],
            $intake,
        );

        $updates = [
            'name' => $name,
            'surname' => $surname !== '' ? $surname : $contact->surname,
            'data' => (object) $data,
        ];
        if ($phone !== '')
        {
            $updates['phone'] = $phone;
        }

        $contact->fill($updates)->save();

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function intakeFromValidated(array $validated): array
    {
        $keys = [
            'phone',
            'business_name',
            'project_name',
            'approx_users',
            'integrations',
            'needed_by',
            'location',
            'scope',
        ];

        $intake = [];
        foreach ($keys as $key)
        {
            $value = trim((string) ($validated[$key] ?? ''));
            if ($value !== '')
            {
                $intake[$key] = $value;
            }
        }

        return $intake;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, string>  $incoming
     * @return array<string, string>
     */
    private function mergeIntake(array $current, array $incoming): array
    {
        $merged = [];
        foreach (array_merge($current, $incoming) as $key => $value)
        {
            if (! is_string($key))
            {
                continue;
            }
            $text = trim((string) $value);
            if ($text !== '')
            {
                $merged[$key] = $text;
            }
        }

        return $merged;
    }

    private function funnelTeam(Request $request): Team|JsonResponse
    {
        $headerToken = trim((string) $request->header('X-Team-Token', ''));
        if ($headerToken !== '')
        {
            return Team::findByPlainApiToken($headerToken) ?? $this->invalidTeamTokenResponse();
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '')
        {
            $fromBearer = Team::findByPlainApiToken($bearer);
            if ($fromBearer)
            {
                return $fromBearer;
            }
        }

        $user = $request->user();
        if ($user?->currentTeam instanceof Team)
        {
            return $user->currentTeam;
        }

        $teamId = (int) config('projects.funnel_team_id');
        if ($teamId > 0)
        {
            $fallback = Team::query()->find($teamId);
            if ($fallback)
            {
                return $fallback;
            }
        }

        return $this->funnelNotConfiguredResponse();
    }

    private function authorizeFunnelEditor(Request $request): Team|JsonResponse
    {
        $team = $this->funnelTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $user = $request->user();
        if (! $user || ! $user->belongsToTeam($team))
        {
            return response()->json([
                'success' => false,
                'message' => __('You are not allowed to edit the quote funnel prompt.'),
            ], 403);
        }

        return $team;
    }

    /**
     * @return array{
     *     from_name: string,
     *     from_address: string,
     *     configured: bool,
     *     can_update: bool,
     *     can_send: bool,
     *     required_include: string,
     *     example_txt: string,
     *     spf: array<string, mixed>|null
     * }
     */
    private function funnelSenderPayload(Team $team, Request $request): array
    {
        $sender = $team->getTeamEmailSender();
        $configured = $team->hasTeamEmailSenderConfigured();
        $spf = $configured
            ? DnsHelper::checkEmailDomainConfiguration($sender['from_address'])
            : null;

        return [
            'from_name' => $sender['from_name'],
            'from_address' => $sender['from_address'],
            'configured' => $configured,
            'can_update' => (bool) $request->user()?->can('update', $team),
            'can_send' => $configured && DnsHelper::canSendBroadcastFromUi($spf, true),
            'required_include' => DnsHelper::REVISION_ALPHA_SPF_INCLUDE,
            'example_txt' => DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT,
            'spf' => $spf,
        ];
    }

    /**
     * @return array{
     *     input_rate: float,
     *     output_rate: float,
     *     discriminate: bool,
     *     include: bool,
     *     can_update: bool
     * }
     */
    private function funnelTokenPricingPayload(Team $team, Request $request, ProjectBudgetSpecService $budgetSpec): array
    {
        return array_merge($budgetSpec->tokenPricingPayload($team), [
            'can_update' => (bool) $request->user()?->can('update', $team),
        ]);
    }

    public function showPublicBudget(string $token, ProjectBudgetQuoteMailService $mailService): JsonResponse
    {
        $project = $this->findProjectByBudgetPreviewToken($token);
        $mailService->markPreviewVisited($project);

        return response()->json([
            'success' => true,
            'data' => $this->budgetSpecService->publicPreview($project->fresh(['enterprise', 'team'])),
        ]);
    }

    public function acceptPublicBudget(\App\Http\Requests\AcceptProjectBudgetPreviewRequest $request, string $token): JsonResponse
    {
        $project = $this->findProjectByBudgetPreviewToken($token);
        $existing = data_get($project->data, 'budget_client_response.status');
        if ($project->isBudgetApproved() || in_array($existing, ['accepted', 'reformulation_requested'], true))
        {
            return response()->json([
                'success' => false,
                'message' => __('This quote was already answered.'),
            ], 422);
        }

        $data = $project->data ?? [];
        $data['budget_client_response'] = [
            'status' => 'accepted',
            'accepted_by_name' => $request->validated('accepted_by_name'),
            'accept_debit' => $request->boolean('accept_debit'),
            'message' => null,
            'responded_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
        ];
        $project->data = $data;
        $project->status_id = \App\Models\ProjectStatus::STATUS_APPROVED;
        $project->save();

        return response()->json([
            'success' => true,
            'message' => __('Thank you. The quote was accepted. The project will not start until 30% of the payment is received.'),
            'data' => $this->budgetSpecService->publicPreview($project->fresh(['enterprise', 'team'])),
        ]);
    }

    public function reformulatePublicBudget(\App\Http\Requests\ReformulateProjectBudgetPreviewRequest $request, string $token): JsonResponse
    {
        $project = $this->findProjectByBudgetPreviewToken($token);
        $existing = data_get($project->data, 'budget_client_response.status');
        if ($project->isBudgetApproved() || in_array($existing, ['accepted', 'reformulation_requested'], true))
        {
            return response()->json([
                'success' => false,
                'message' => __('This quote was already answered.'),
            ], 422);
        }

        $data = $project->data ?? [];
        $data['budget_client_response'] = [
            'status' => 'reformulation_requested',
            'accepted_by_name' => $request->validated('name'),
            'message' => $request->validated('message'),
            'responded_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
        ];
        $project->data = $data;
        $project->status_id = \App\Models\ProjectStatus::STATUS_WAITING_FOR_RESPONSE;
        $project->save();

        return response()->json([
            'success' => true,
            'message' => __('Thanks. We received your reformulation request and will review it shortly.'),
            'data' => $this->budgetSpecService->publicPreview($project->fresh(['enterprise', 'team'])),
        ]);
    }

    private function findProjectByBudgetPreviewToken(string $token): Project
    {
        return Project::withoutGlobalScopes()
            ->with(['enterprise', 'team'])
            ->where('data->budget_preview_token', $token)
            ->firstOrFail();
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
            'message' => __('Quote funnel needs a team token from the frontend.'),
        ], 503);
    }

    private function invalidTeamTokenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Invalid team token.'),
        ], 401);
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

    /**
     * @return array{preview_url: string|null, download_url: string|null}
     */
    private function publicBudgetUrls(string $token, ?Project $project = null): array
    {
        if ($token === '')
        {
            return [
                'preview_url' => null,
                'download_url' => null,
            ];
        }

        return \App\Support\BudgetPreviewUrl::pair($token, $project);
    }
}
