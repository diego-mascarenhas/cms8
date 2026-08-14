<?php

namespace App\Http\Controllers;

use App\DataTables\ProjectDataTable;
use App\Http\Requests\AcceptProjectBudgetPreviewRequest;
use App\Http\Requests\ReformulateProjectBudgetPreviewRequest;
use App\Http\Requests\StoreProjectDepositInvoiceRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactProject;
use App\Models\Enterprise;
use App\Models\Fare;
use App\Models\Language;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Services\Finance\ProjectDepositInvoiceService;
use App\Services\ProjectBudgetQuoteMailService;
use App\Services\ProjectBudgetSpecService;
use App\Support\AssignableTeamUsers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;

class ProjectController extends Controller
{
    public function __construct()
    {
        // Note: Manual authorization in each method due to non-standard route parameter names
        // Laravel's authorizeResource() expects {project} parameter, but routes use {id}
    }

    public function index(ProjectDataTable $dataTable)
    {
        $this->authorize('viewAny', Project::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $stats = Project::getProjectStats((int) auth()->user()->current_team_id);

        return $dataTable->render('project.index', $stats);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Project::class);

        $statuses = ProjectStatus::getOptions();
        $data = new Project;
        $enterpriseId = (int) request('enterprise_id');

        if ($enterpriseId > 0 && Enterprise::query()
            ->where('id', $enterpriseId)
            ->where('team_id', auth()->user()->current_team_id)
            ->exists())
        {
            $data->enterprise_id = $enterpriseId;
        }

        $enterprise_id = $data->enterprise_id;

        return view('project.form', compact('enterprise_id', 'statuses', 'data'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request)
    {
        $projectId = $request->input('id');
        $projectId = (is_numeric($projectId) && (int) $projectId > 0)
            ? (int) $projectId
            : null;

        $existing = $projectId
            ? Project::withoutGlobalScopes()->find($projectId)
            : null;

        if ($existing)
        {
            $this->authorize('update', $existing);

            if ($existing->isBudgetContentLocked())
            {
                return redirect()
                    ->route('project.show', $existing->id)
                    ->with('error', __('This approved budget can no longer be edited. You can only change its status.'));
            }
        } else
        {
            $this->authorize('create', Project::class);
        }

        $data = $request->validated();

        if (isset($data['data']))
        {
            $data['data'] = app(ProjectBudgetSpecService::class)->hydrateProjectBudgetData(
                is_array($data['data']) ? $data['data'] : null,
            );
        }

        $discount = array_key_exists('discount', $data)
            ? (($data['discount'] === '' || $data['discount'] === null) ? null : $data['discount'])
            : $existing?->discount;

        $previousStatusId = $existing ? (int) $existing->status_id : null;

        $attributes = [
            'team_id' => auth()->user()->currentTeam->id,
            'name' => $data['name'],
            'real_name' => $data['real_name'] ?? null,
            'enterprise_id' => $data['enterprise_id'],
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
            'data' => $data['data'] ?? null,
            'responsible_id' => $data['responsible_id'],
            'price' => $data['price'] ?? $existing?->price,
            'discount' => $discount,
            'cost' => $data['cost'] ?? $existing?->cost,
            'status_id' => $data['status_id'] ?? 1,
            'date_material' => $data['date_material'] ?? null,
            'date_start' => $data['date_start'] ?? null,
            'date_end' => $data['date_end'] ?? null,
        ];

        if ($existing)
        {
            $existing->update($attributes);
            $project = $existing->fresh();
        } else
        {
            $project = Project::create($attributes);
        }

        // Auto-create TaskBoard for new projects
        if (! $projectId && ! $project->board_id)
        {
            $board = TaskBoard::create([
                'team_id' => auth()->user()->currentTeam->id,
                'name' => "Project: {$project->name}",
                'description' => "Task board for project: {$project->name}",
                'is_default' => false,
                'order' => 0,
            ]);

            $project->update(['board_id' => $board->id]);
        }

        if (! $projectId)
        {
            return redirect()->route('project.show', $project->id)->with('success', 'Proyecto creado exitosamente.');
        }

        $becameAuthorized = $previousStatusId !== ProjectStatus::STATUS_AUTHORIZED
            && (int) $project->status_id === ProjectStatus::STATUS_AUTHORIZED;

        if ($becameAuthorized && auth()->user()?->hasRole('admin'))
        {
            try
            {
                app(ProjectBudgetQuoteMailService::class)->sendQuoteEmail($project, auth()->user());

                return redirect()
                    ->route('project.show', $project->id)
                    ->with('success', __('Quote authorized and emailed to the enterprise contact.'));
            } catch (RuntimeException $e)
            {
                return redirect()
                    ->route('project.show', $project->id)
                    ->with('error', $e->getMessage())
                    ->with('success', __('Project updated successfully.'));
            }
        }

        return redirect()->route('project.show', $project->id)->with('success', 'Proyecto actualizado exitosamente.');
    }

    /**
     * Generate budget spec (dimension, times, resources, token consumption) from budget text via Laravel AI.
     * Used when creating/editing a project presupuesto.
     */
    public function generateBudgetSpec(Request $request, ProjectBudgetSpecService $budgetSpecService): \Illuminate\Http\JsonResponse
    {
        $this->authorize('access-billing-modules');
        $this->authorize('create', Project::class);

        $request->validate([
            'budget_given' => 'required|string|max:16000',
        ]);

        $timeout = max(60, (int) config('ai.budget_spec_timeout', 180));
        set_time_limit($timeout + 30);

        try
        {
            $spec = $budgetSpecService->generate(
                (string) $request->input('budget_given'),
                auth()->user()?->currentTeam,
                auth()->user(),
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
            ...$spec,
        ]);
    }

    /**
     * Public budget preview by token (no auth). Shows module, level, value table.
     */
    public function budgetPreview(string $token)
    {
        $project = $this->findProjectByBudgetPreviewToken($token);

        $suggestedTasks = is_array($project->data['suggested_tasks'] ?? null) ? $project->data['suggested_tasks'] : [];

        $this->syncProjectStatusFromBudgetResponse($project);

        return view('project.budget-preview', [
            'project' => $project,
            'suggestedTasks' => $suggestedTasks,
            'budgetToken' => $token,
            'clientResponse' => is_array(data_get($project->data, 'budget_client_response'))
                ? data_get($project->data, 'budget_client_response')
                : null,
        ]);
    }

    /**
     * Public: client accepts the budget quote.
     */
    public function acceptBudgetPreview(AcceptProjectBudgetPreviewRequest $request, string $token)
    {
        $project = $this->findProjectByBudgetPreviewToken($token);
        $existing = data_get($project->data, 'budget_client_response.status');
        if ($project->isBudgetApproved() || in_array($existing, ['accepted', 'reformulation_requested'], true))
        {
            return redirect()
                ->route('project.budget-preview', $token)
                ->with('budget_response_error', __('This quote was already answered.'));
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
        $project->status_id = ProjectStatus::STATUS_APPROVED;
        $project->save();

        return redirect()
            ->route('project.budget-preview', $token)
            ->with('budget_response_success', __('Thank you. The quote was accepted. The project will not start until 30% of the payment is received.'));
    }

    /**
     * Public: client requests a reformulation of the budget quote.
     */
    public function reformulateBudgetPreview(ReformulateProjectBudgetPreviewRequest $request, string $token)
    {
        $project = $this->findProjectByBudgetPreviewToken($token);
        $existing = data_get($project->data, 'budget_client_response.status');
        if ($project->isBudgetApproved() || in_array($existing, ['accepted', 'reformulation_requested'], true))
        {
            return redirect()
                ->route('project.budget-preview', $token)
                ->with('budget_response_error', __('This quote was already answered.'));
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
        $project->status_id = ProjectStatus::STATUS_WAITING_FOR_RESPONSE;
        $project->save();

        return redirect()
            ->route('project.budget-preview', $token)
            ->with('budget_response_success', __('Thanks. We received your reformulation request and will review it shortly.'));
    }

    /**
     * Admin shortcut: mark budgeted quote as authorized and email the public preview.
     */
    public function authorizeBudgetQuote(string $id, ProjectBudgetQuoteMailService $mailService)
    {
        $project = Project::with(['enterprise.contacts', 'team', 'status'])->findOrFail($id);
        $this->authorize('update', $project);

        if (! auth()->user()?->hasRole('admin'))
        {
            abort(403);
        }

        try
        {
            $mailService->authorizeAndSend($project, auth()->user());
        } catch (RuntimeException $e)
        {
            return redirect()
                ->route('project.show', $project->id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('project.show', $project->id)
            ->with('success', __('Quote authorized and emailed to the enterprise contact.'));
    }

    /**
     * Public 1x1 GIF open tracking for budget quote emails.
     */
    public function trackBudgetEmailOpen(string $token, ProjectBudgetQuoteMailService $mailService)
    {
        $mailService->markOpened($token);

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Public click tracking for budget quote emails; redirects to the preview.
     */
    public function trackBudgetEmailClick(string $token, ProjectBudgetQuoteMailService $mailService)
    {
        $previewUrl = $mailService->markClicked($token);
        if ($previewUrl === null)
        {
            abort(404);
        }

        return redirect()->away($previewUrl);
    }

    private function findProjectByBudgetPreviewToken(string $token): Project
    {
        return Project::withoutGlobalScopes()
            ->with('enterprise')
            ->where('data->budget_preview_token', $token)
            ->firstOrFail();
    }

    /**
     * Align project workflow status with the client quote response when needed.
     */
    private function syncProjectStatusFromBudgetResponse(Project $project): void
    {
        $targetStatusId = match (data_get($project->data, 'budget_client_response.status'))
        {
            'accepted' => ProjectStatus::STATUS_APPROVED,
            'reformulation_requested' => ProjectStatus::STATUS_WAITING_FOR_RESPONSE,
            default => null,
        };

        if ($targetStatusId === null || (int) $project->status_id === $targetStatusId)
        {
            return;
        }

        $project->status_id = $targetStatusId;
        $project->save();
    }

    /**
     * Show collaborator selection screen for a project
     */
    public function selectCollaborators($projectId, Request $request)
    {
        $project = Project::with(['client', 'responsible', 'status', 'category'])
            ->findOrFail($projectId);

        // Get data for filters
        $languages = Language::orderBy('name')->get();
        $fares = Fare::with('type')->orderBy('name')->get();

        // Check if we have URL parameters for pre-filtering
        $selectedSourceLanguage = $request->get('source_language');
        $selectedTargetLanguage = $request->get('target_language');
        $selectedService = $request->get('service');

        // If we have filter parameters, load collaborators automatically
        $collaborators = collect();
        if ($selectedSourceLanguage && $selectedTargetLanguage && $selectedService)
        {
            // Build the query for contacts with language variants and fares
            $query = Contact::with([
                'valoration',
                'languageVariants.sourceLanguage',
                'languageVariants.targetLanguage',
                'fares.type',
                'fares' => function ($query)
                {
                    $query->withPivot('price', 'unit_id', 'currency_code', 'source_language_code', 'target_language_code');
                },
            ]);

            // Basic requirements for collaborators
            $query
                ->whereHas('languageVariants')  // Only contacts with language variants
                ->whereHas('fares');  // Only contacts with services/fares

            // Apply language filters - find collaborators with exact language combination
            $query->whereHas('languageVariants', function ($q) use ($selectedSourceLanguage, $selectedTargetLanguage)
            {
                $q
                    ->where('source_language_code', $selectedSourceLanguage)
                    ->where('target_language_code', $selectedTargetLanguage);
            });

            // Apply service filter
            $query->whereHas('fares', function ($q) use ($selectedService)
            {
                $q->where('fares.id', $selectedService);
            });

            $collaborators = $query->orderByRaw('valoration_id IS NULL, valoration_id ASC')->get();
        }

        return view('project.select-collaborators', compact('project', 'languages', 'fares', 'collaborators', 'selectedService', 'selectedSourceLanguage', 'selectedTargetLanguage'));
    }

    /**
     * Filter collaborators via AJAX (similar to CollaboratorDataTable filtering)
     */
    public function filterCollaborators(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);

        // Check if any filter is applied
        $hasLanguageFilter = ($request->has('source_language') && $request->source_language) ||
            ($request->has('target_language') && $request->target_language);
        $hasServiceFilter = $request->has('service') && $request->service;
        $hasDaysFilter = $request->has('days') && $request->days;
        $hasDeliveryDateFilter = $request->has('delivery_date') && $request->delivery_date;

        // Return empty if no filter is applied
        if (! $hasLanguageFilter && ! $hasServiceFilter && ! $hasDaysFilter && ! $hasDeliveryDateFilter)
        {
            return response()->json([
                'html' => view('project.partials.collaborator-cards', [
                    'collaborators' => collect(),
                    'project' => $project,
                    'selectedService' => null,
                    'selectedSourceLanguage' => null,
                    'selectedTargetLanguage' => null,
                ])->render(),
                'count' => 0,
            ]);
        }

        // Build the query for contacts with language variants and fares
        $query = Contact::with([
            'valoration',
            'languageVariants.sourceLanguage',
            'languageVariants.targetLanguage',
            'fares.type',
            'fares' => function ($query)
            {
                $query->withPivot('price', 'unit_id', 'currency_code', 'source_language_code', 'target_language_code');
            },
        ]);

        // Basic requirements for collaborators
        $query
            ->whereHas('languageVariants')  // Only contacts with language variants
            ->whereHas('fares')  // Only contacts with services/fares
            ->excludeRemovedFromProject($projectId);

        // Apply language filters - each filter searches in its respective field
        if ($request->has('source_language') &&
                $request->source_language &&
                $request->has('target_language') &&
                $request->target_language)
        {
            // Both source and target specified - find collaborators that match BOTH criteria
            $query->whereHas('languageVariants', function ($q) use ($request)
            {
                $q
                    ->where('source_language_code', $request->source_language)
                    ->where('target_language_code', $request->target_language);
            });
        } elseif ($request->has('source_language') && $request->source_language)
        {
            // Only source language specified - search only in source_language_code
            $query->whereHas('languageVariants', function ($q) use ($request)
            {
                $q->where('source_language_code', $request->source_language);
            });
        } elseif ($request->has('target_language') && $request->target_language)
        {
            // Only target language specified - search only in target_language_code
            $query->whereHas('languageVariants', function ($q) use ($request)
            {
                $q->where('target_language_code', $request->target_language);
            });
        }

        // Apply service filter
        if ($request->has('service') && $request->service)
        {
            $query->whereHas('fares', function ($q) use ($request)
            {
                $q->where('fares.id', $request->service);
            });
        }

        // Apply availability filter (days and delivery date) - similar to CollaboratorDataTable
        if (($request->has('days') && $request->days) && ($request->has('delivery_date') && $request->delivery_date))
        {
            $availableCollaboratorIds = $this->getAvailableCollaboratorIds($request->days, $request->delivery_date);

            if (! empty($availableCollaboratorIds))
            {
                $query->whereIn('id', $availableCollaboratorIds);
            } else
            {
                // If no collaborators are available, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        $collaborators = $query->orderByRaw('valoration_id IS NULL, valoration_id ASC')->get();

        // Return the HTML for the collaborator cards
        return response()->json([
            'html' => view('project.partials.collaborator-cards', [
                'collaborators' => $collaborators,
                'project' => $project,
                'selectedService' => $request->service ?? null,
                'selectedSourceLanguage' => $request->source_language ?? null,
                'selectedTargetLanguage' => $request->target_language ?? null,
                'filterDays' => $request->days ?? null,
                'filterDeliveryDate' => $request->delivery_date ?? null,
            ])->render(),
            'count' => $collaborators->count(),
        ]);
    }

    /**
     * Get collaborator IDs that have enough available days in the given period
     */
    private function getAvailableCollaboratorIds($requiredDays, $deliveryDate)
    {
        $availableIds = [];
        $startDate = now()->addDay()->format('Y-m-d');

        // Parse delivery date
        $endDate = null;

        // First check if it's a predefined option
        switch ($deliveryDate)
        {
            case 'today':
                $endDate = now()->format('Y-m-d');
                break;
            case '1_week':
                $endDate = now()->addWeek()->format('Y-m-d');
                break;
            case '15_days':
                $endDate = now()->addDays(15)->format('Y-m-d');
                break;
            case '1_month':
                $endDate = now()->addMonth()->format('Y-m-d');
                break;
            case '3_months':
                $endDate = now()->addMonths(3)->format('Y-m-d');
                break;
            default:
                // If it's a custom date, try to parse it
                try
                {
                    // Handle Spanish date format (d/m/Y) or ISO format (Y-m-d)
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $deliveryDate))
                    {
                        // Spanish format: d/m/Y
                        $endDate = Carbon::createFromFormat('d/m/Y', $deliveryDate)->format('Y-m-d');
                    } else
                    {
                        // ISO format: Y-m-d
                        $endDate = Carbon::parse($deliveryDate)->format('Y-m-d');
                    }

                    // Check if delivery date is in the past
                    if (Carbon::parse($endDate)->isPast())
                    {
                        return [];
                    }
                } catch (\Exception $e)
                {
                    return [];
                }
        }

        // Get all collaborators with their weekly availability and absences
        $collaborators = Contact::with(['weeklyAvailability', 'absences' => function ($q) use ($startDate, $endDate)
        {
            $q->whereBetween('absence_date', [$startDate, $endDate]);
        }])->get();

        foreach ($collaborators as $collaborator)
        {
            $availableDays = $this->calculateAvailableDays($collaborator, $startDate, $endDate);

            if ($availableDays >= $requiredDays)
            {
                $availableIds[] = $collaborator->id;
            }
        }

        return $availableIds;
    }

    /**
     * Calculate available days for a collaborator in a given period
     */
    private function calculateAvailableDays($collaborator, $startDate, $endDate)
    {
        $weeklyAvailability = $collaborator->weeklyAvailability;

        // If no weekly availability is set, assume all days are available
        if (! $weeklyAvailability)
        {
            $weeklyPattern = [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => true,
                'sunday' => true,
            ];
        } else
        {
            $weeklyPattern = [
                'monday' => $weeklyAvailability->monday,
                'tuesday' => $weeklyAvailability->tuesday,
                'wednesday' => $weeklyAvailability->wednesday,
                'thursday' => $weeklyAvailability->thursday,
                'friday' => $weeklyAvailability->friday,
                'saturday' => $weeklyAvailability->saturday,
                'sunday' => $weeklyAvailability->sunday,
            ];
        }

        // Get specific absence dates
        $absenceDates = $collaborator->absences->pluck('absence_date')->map(function ($date)
        {
            return $date->format('Y-m-d');
        })->toArray();

        $availableDays = 0;
        $currentDate = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);

        while ($currentDate->lte($endDateCarbon))
        {
            $dayOfWeek = strtolower($currentDate->format('l'));
            $dateString = $currentDate->format('Y-m-d');

            // Check if this day is available according to weekly pattern
            $isWeeklyAvailable = $weeklyPattern[$dayOfWeek] ?? false;

            // Check if this specific date is not in absences
            $isNotAbsent = ! in_array($dateString, $absenceDates);

            // Day is available if both conditions are met
            if ($isWeeklyAvailable && $isNotAbsent)
            {
                $availableDays++;
            }

            $currentDate->addDay();
        }

        return $availableDays;
    }

    /**
     * Send notifications to selected collaborators
     */
    public function sendCollaboratorNotifications(Request $request, $projectId)
    {
        $project = Project::with(['client', 'responsible', 'status', 'category'])
            ->findOrFail($projectId);

        $request->validate([
            'collaborator_ids' => 'required|array|min:1',
            'collaborator_ids.*' => 'exists:contacts,id',
        ]);

        $collaboratorIds = $request->collaborator_ids;

        // Use default message template if not provided
        $messageTemplate = $request->message_template ?? 'Hola, {nombre}: Te contactamos desde bbo porque tenemos un nuevo proyecto. Hay que hacer {servicio}, de un {nombre_proyecto}, de {idioma_source} a {idioma_target}. La fecha de entrega ideal es {fecha_entrega_materiales}. ¿Puedes confirmarnos tu tarifa y cuándo lo podrías tener? ¡Gracias!';

        // Process message placeholders
        $messageVariables = [
            '{nombre_proyecto}' => $project->real_name ?? $project->name,
            '{servicio}' => $request->input('selected_service', 'N/A'),
            '{idioma_source}' => $request->input('source_language_name', 'N/A'),
            '{idioma_target}' => $request->input('target_language_name', 'N/A'),
            '{fecha_entrega_materiales}' => $this->formatDate($project->date_material),
        ];

        $sentCount = 0;
        $errors = [];

        foreach ($collaboratorIds as $collaboratorId)
        {
            try
            {
                $collaborator = Contact::findOrFail($collaboratorId);

                // Replace {nombre} placeholder with collaborator name
                $personalizedMessage = str_replace('{nombre}', $collaborator->name, $messageTemplate);

                // Replace other placeholders
                $personalizedMessage = str_replace(
                    array_keys($messageVariables),
                    array_values($messageVariables),
                    $personalizedMessage,
                );

                // Create or update the contact_project relationship
                $result = $project->collaborators()->syncWithoutDetaching([
                    $collaboratorId => [
                        'message_sent' => $personalizedMessage,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);

                // Verify the relationship was created
                $relationshipExists = $project->collaborators()->where('contact_id', $collaboratorId)->exists();

                $sentCount++;
            } catch (\Exception $e)
            {
                $errors[] = "Error sending to collaborator {$collaboratorId}: ".$e->getMessage();
            }
        }

        if ($sentCount > 0)
        {
            $message = "Messages sent successfully to {$sentCount} collaborator(s).";
            if (! empty($errors))
            {
                $message .= ' However, there were some errors: '.implode(', ', $errors);
            }

            return redirect()->route('project.show', $projectId)->with('success', $message);
        } else
        {
            return redirect()->back()->with('error', 'Failed to send messages: '.implode(', ', $errors));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        $project = Project::with([
            'client.contacts',
            'responsible',
            'status',
            'category',
            'notes',
            'allCollaborators.valoration',
            'allCollaborators.languageVariants.sourceLanguage',
            'allCollaborators.languageVariants.targetLanguage',
            'allCollaborators.fares.type',
            'projectFares.fare.type',
            'projectFares.sourceLanguage',
            'projectFares.targetLanguage',
        ])->findOrFail($id);

        $this->syncProjectStatusFromBudgetResponse($project);
        $project->load('status');

        // Collaborators can only view their assigned projects
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->hasRole('collaborator') && $project->responsible_id !== $currentUser->id)
        {
            abort(403);
        }

        // Get time tracking data and task breakdown for this project through board tasks
        $timeEntries = collect();
        $totalHours = 0;
        $projectTasks = collect();
        $actualHoursByTaskId = collect();
        $runningTimer = Time::getRunningTimer();

        if ($project->board_id)
        {
            $projectTasks = Task::where('board_id', $project->board_id)
                ->with(['status', 'responsible'])
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            $taskIds = $projectTasks->pluck('id');

            if ($taskIds->isNotEmpty())
            {
                $timeEntries = Time::whereIn('task_id', $taskIds)
                    ->with(['user:id,name', 'task:id,title'])
                    ->orderBy('start_time', 'desc')
                    ->limit(10)
                    ->get();

                $totalHours = Time::whereIn('task_id', $taskIds)
                    ->whereNotNull('end_time')
                    ->sum('duration_seconds') / 3600;

                $actualHoursByTaskId = Time::whereIn('task_id', $taskIds)
                    ->selectRaw('task_id, SUM(duration_seconds) as total_seconds')
                    ->groupBy('task_id')
                    ->get()
                    ->mapWithKeys(fn ($row) => [$row->task_id => round($row->total_seconds / 3600, 1)]);
            }
        }

        $suggestedTasks = is_array($project->data['suggested_tasks'] ?? null) ? $project->data['suggested_tasks'] : [];
        $boardTasksByTitle = $projectTasks->keyBy('title');
        $suggestedTasks = collect($suggestedTasks)->map(function ($t) use ($boardTasksByTitle)
        {
            $title = $t['title'] ?? '';
            $boardTask = $boardTasksByTitle->get($title);
            if ($boardTask && $boardTask->responsible_id)
            {
                $t['responsible_id'] = $boardTask->responsible_id;
            }

            return $t;
        })->all();

        $suggestedTitles = collect($suggestedTasks)->pluck('title')->filter()->values()->all();
        foreach ($projectTasks as $boardTask)
        {
            if (! in_array($boardTask->title, $suggestedTitles, true))
            {
                $suggestedTasks[] = [
                    'title' => $boardTask->title,
                    'category_name' => $boardTask->category?->name ?? '—',
                    'estimated_hours' => $boardTask->estimated_hours,
                    'resource_level' => '—',
                    'responsible_id' => $boardTask->responsible_id,
                    'on_board' => true,
                ];
            }
        }

        $team = auth()->user()->currentTeam;
        $teamUsers = $team
            ? AssignableTeamUsers::optionsForTeam($team)
            : collect();

        $depositInvoicePreview = null;
        if (
            $project->isBudgetApproved()
            && auth()->user()->can('access-billing-modules')
        ) {
            $project->loadMissing(['client.enterpriseBillingAddresses.taxStatusType']);
            $depositInvoicePreview = app(ProjectDepositInvoiceService::class)->preview($project);
        }

        return view('project.show', compact(
            'project',
            'timeEntries',
            'totalHours',
            'projectTasks',
            'actualHoursByTaskId',
            'suggestedTasks',
            'teamUsers',
            'runningTimer',
            'depositInvoicePreview',
        ));
    }

    /**
     * Create a task on the project board from a suggested task (title, category, hours, responsible).
     */
    public function addSuggestedTask(Request $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $request->validate([
            'title' => 'required|string|max:500',
            'category_name' => 'nullable|string|max:255',
            'estimated_hours' => 'nullable|numeric|min:0',
            'responsible_id' => 'required|exists:users,id',
        ]);

        if (! $project->board_id)
        {
            $board = TaskBoard::create([
                'team_id' => auth()->user()->currentTeam->id,
                'name' => "Project: {$project->name}",
                'description' => "Task board for project: {$project->name}",
                'is_default' => false,
                'order' => 0,
            ]);
            $project->update(['board_id' => $board->id]);
        }

        $categoryId = null;
        if ($request->filled('category_name'))
        {
            $tasksModule = Module::where('key', 'tasks')->first();
            $teamId = auth()->user()->currentTeam->id ?? null;
            if ($tasksModule)
            {
                $category = Category::where('module_id', $tasksModule->id)
                    ->where('name', $request->category_name)
                    ->where(function ($q) use ($teamId)
                    {
                        $q->whereNull('team_id');
                        if ($teamId)
                        {
                            $q->orWhere('team_id', $teamId);
                        }
                    })
                    ->first();
                $categoryId = $category?->id;
            }
        }

        $defaultStatusId = TaskStatus::orderBy('order')->value('id') ?? 1;
        $nextOrder = (int) Task::where('board_id', $project->board_id)->max('order') + 1;
        $today = now()->toDateString();

        Task::create([
            'team_id' => auth()->user()->currentTeam->id,
            'board_id' => $project->board_id,
            'title' => $request->title,
            'category_id' => $categoryId,
            'responsible_id' => $request->responsible_id,
            'estimated_hours' => $request->filled('estimated_hours') ? $request->estimated_hours : null,
            'status_id' => $defaultStatusId,
            'order' => $nextOrder,
            'start_date' => $today,
            'due_date' => $today,
        ]);

        return redirect()->route('project.show', $project->id)
            ->with('success', __('Task added to board.'));
    }

    /**
     * Manually register time worked on a project board task (collaborator + task).
     */
    public function storeTimeEntry(Request $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        if (! $project->board_id)
        {
            return redirect()
                ->route('project.show', $project->id)
                ->with('error', __('Add tasks to the project board before registering time.'));
        }

        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'user_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration_hours' => 'nullable|numeric|min:0.01|max:24',
        ]);

        $task = Task::query()
            ->where('id', $validated['task_id'])
            ->where('board_id', $project->board_id)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->firstOrFail();

        $userId = (int) auth()->id();
        if (auth()->user()->hasRole('admin') && ! empty($validated['user_id']))
        {
            $candidateId = (int) $validated['user_id'];
            $teamUserIds = auth()->user()->currentTeam->allUsers()->pluck('id')->map(fn ($id) => (int) $id);
            if (! $teamUserIds->contains($candidateId))
            {
                return redirect()
                    ->route('project.show', $project->id)
                    ->with('error', __('The selected collaborator does not belong to this team.'));
            }
            $userId = $candidateId;
        }

        $start = Carbon::parse($validated['start_time']);
        $end = null;
        if (! empty($validated['end_time']))
        {
            $end = Carbon::parse($validated['end_time']);
        } elseif (! empty($validated['duration_hours']))
        {
            $end = $start->copy()->addSeconds((int) round(((float) $validated['duration_hours']) * 3600));
        }

        if ($end === null)
        {
            return redirect()
                ->route('project.show', $project->id)
                ->withInput()
                ->with('error', __('Provide an end time or a duration in hours.'));
        }

        $time = Time::create([
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => $userId,
            'task_id' => $task->id,
            'description' => $validated['description'] ?? null,
            'start_time' => $start,
            'end_time' => $end,
            'is_billable' => true,
        ]);
        $time->calculateDuration();

        return redirect()
            ->route('project.show', $project->id)
            ->with('success', __('Time entry registered successfully.'));
    }

    /**
     * Issue a 30% deposit invoice (Stripe + local) for an approved project budget.
     */
    public function invoiceDeposit(StoreProjectDepositInvoiceRequest $request, string $id, ProjectDepositInvoiceService $depositInvoiceService)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);
        $this->authorize('access-billing-modules');

        $result = $depositInvoiceService->issue(
            $project,
            (string) $request->validated('description'),
            auth()->user()->currentTeam,
        );

        $message = ! empty($result['charged'])
            ? __('Deposit invoice created and charged. Project moved to in progress.')
            : __('Deposit invoice created and sent for payment. Project moved to in progress.');

        if (! empty($result['hosted_invoice_url']) && empty($result['charged']))
        {
            return redirect()
                ->route('project.show', $project->id)
                ->with('success', $message)
                ->with('deposit_invoice_url', $result['hosted_invoice_url']);
        }

        return redirect()
            ->route('project.show', $project->id)
            ->with('success', $message);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        if ($project->isBudgetContentLocked())
        {
            return redirect()
                ->route('project.show', $project->id)
                ->with('error', __('This approved budget can no longer be edited. You can only change its status.'));
        }

        $data = Project::with(['projectFares.fare.units', 'projectFares.sourceLanguage', 'projectFares.targetLanguage'])
            ->findOrFail($id);
        $enterprise_id = $data->enterprise_id;
        $statuses = ProjectStatus::getOptions();

        return view('project.form', compact('data', 'enterprise_id', 'statuses'));
    }

    /**
     * Status-only update for approved (locked) budgets.
     */
    public function updateStatus(Request $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        if (! $project->isBudgetContentLocked())
        {
            return redirect()
                ->route('project.edit', $project->id)
                ->with('error', __('Use the project edit form to change status before the budget is approved.'));
        }

        $allowed = $project->allowedStatusIdsWhenLocked();
        $validated = $request->validate([
            'status_id' => ['required', 'integer', 'in:'.implode(',', $allowed)],
        ]);

        $project->status_id = (int) $validated['status_id'];
        $project->save();

        return redirect()
            ->route('project.show', $project->id)
            ->with('success', __('Project status updated.'));
    }

    /**
     * Update project (blocked when budget is approved).
     */
    public function update(StoreProjectRequest $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        if ($project->isBudgetContentLocked())
        {
            return redirect()
                ->route('project.show', $project->id)
                ->with('error', __('This approved budget can no longer be edited. You can only change its status.'));
        }

        $validated = $request->validated();
        $budgetService = app(\App\Services\ProjectBudgetSpecService::class);

        if (array_key_exists('data', $validated))
        {
            $validated['data'] = $budgetService->hydrateProjectBudgetData(
                is_array($validated['data']) ? $validated['data'] : null,
            );
        }

        $project->update($validated);

        return redirect()
            ->route('project.show', $project->id)
            ->with('success', __('Project updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = Project::findOrFail($id);
        $this->authorize('delete', $model);

        if ($model->isBudgetContentLocked())
        {
            return response()->json([
                'message' => __('This approved budget can no longer be deleted.'),
            ], 422);
        }

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }

    /**
     * Remove a collaborator from a project (Soft Delete)
     */
    public function removeCollaborator(Project $project, $collaborator)
    {
        try
        {
            // Find the collaborator
            $collaboratorModel = Contact::findOrFail($collaborator);

            // Find the pivot record using the ContactProject model
            $pivotRecord = ContactProject::where('contact_id', $collaborator)
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->first();

            if (! $pivotRecord)
            {
                return response()->json([
                    'message' => 'El colaborador no está asociado con este proyecto.',
                ], 404);
            }

            // Soft delete the pivot record
            $pivotRecord->delete();

            return response()->json([
                'message' => 'Colaborador eliminado del proyecto exitosamente.',
            ], 200);
        } catch (\Exception $e)
        {
            return response()->json([
                'message' => 'Ha ocurrido un error al eliminar el colaborador.',
            ], 500);
        }
    }

    /**
     * Get service template for dynamic addition
     */
    public function getServiceTemplate(Request $request)
    {
        $index = $request->get('index', 0);

        return view('project.partials.service-row', [
            'index' => $index,
            'projectFare' => null,
        ])->render();
    }

    /**
     * Get units for a specific fare
     */
    public function getFareUnits(Request $request)
    {
        try
        {
            $fareId = $request->get('fare_id');

            if (! $fareId)
            {
                return response()->json([
                    'units' => [],
                    'success' => true,
                ], 200, [
                    'Content-Type' => 'application/json',
                ]);
            }

            // Use withoutGlobalScopes to avoid team_id restriction
            $fare = Fare::withoutGlobalScopes()->with('units')->find($fareId);

            if (! $fare)
            {
                return response()->json([
                    'units' => [],
                    'success' => true,
                ], 200, [
                    'Content-Type' => 'application/json',
                ]);
            }

            $units = $fare->units->map(function ($unit)
            {
                return [
                    'id' => $unit->id,
                    'type' => $unit->type,
                    'label' => $unit->type,
                ];
            });

            return response()->json([
                'units' => $units,
                'success' => true,
            ], 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'units' => [],
                'error' => 'Error loading units: '.$e->getMessage(),
                'success' => false,
            ], 200, [
                'Content-Type' => 'application/json',
            ]);
        }
    }

    /**
     * Show add services page for a project
     */
    public function addServices(string $projectId)
    {
        $project = Project::with(['client', 'responsible', 'status', 'category'])
            ->findOrFail($projectId);

        return view('project.add-services', compact('project'));
    }

    /**
     * Store services for a project
     */
    public function storeServices(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);

        $request->validate([
            'services' => 'required|array|min:1',
            'services.*.fare_id' => 'required|exists:fares,id',
            'services.*.source_language_code' => 'required|exists:language_variants,code',
            'services.*.target_language_code' => 'required|exists:language_variants,code',
            'services.*.quantity' => 'required|numeric|min:1',
            'services.*.unit' => 'required|string',
        ]);

        try
        {
            // Clear existing services
            $project->projectFares()->delete();

            // Add new services
            $createdServices = [];
            foreach ($request->services as $serviceData)
            {
                $projectFare = $project->projectFares()->create([
                    'fare_id' => $serviceData['fare_id'],
                    'source_language_code' => $serviceData['source_language_code'],
                    'target_language_code' => $serviceData['target_language_code'],
                    'quantity' => $serviceData['quantity'],
                    'unit' => $serviceData['unit'],
                ]);
                $createdServices[] = $projectFare->id;
            }

            return redirect()
                ->route('project.show', $project->id)
                ->with('success', 'Servicios agregados exitosamente.');
        } catch (\Exception $e)
        {
            return redirect()
                ->back()
                ->with('error', 'Error al guardar los servicios: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get services for a project (modal)
     */
    public function getServices(string $projectId)
    {
        $project = Project::findOrFail($projectId);

        $services = $project->projectFares()->with([
            'fare',
            'sourceLanguage',
            'targetLanguage',
        ])->get()->map(function ($projectFare)
        {
            return [
                'id' => $projectFare->id,
                'fare_id' => $projectFare->fare_id,
                'fare_name' => $projectFare->fare->name,
                'source_language_code' => $projectFare->source_language_code,
                'source_language_name' => $projectFare->sourceLanguage->name,
                'source_country_code' => $projectFare->sourceLanguage->country_code ?? '',
                'target_language_code' => $projectFare->target_language_code,
                'target_language_name' => $projectFare->targetLanguage->name,
                'target_country_code' => $projectFare->targetLanguage->country_code ?? '',
                'quantity' => $projectFare->quantity,
                'unit' => $projectFare->unit,
            ];
        });

        return response()->json([
            'success' => true,
            'services' => $services,
        ]);
    }

    /**
     * Store a single service for a project (modal)
     */
    public function storeService(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);

        $request->validate([
            'fare_id' => 'required|exists:fares,id',
            'source_language_code' => 'required|exists:language_variants,code',
            'target_language_code' => 'required|exists:language_variants,code',
            'quantity' => 'required|numeric|min:1',
            'unit' => 'required|string',
        ]);

        try
        {
            // Check for duplicates
            $existingService = $project
                ->projectFares()
                ->where('fare_id', $request->fare_id)
                ->where('source_language_code', $request->source_language_code)
                ->where('target_language_code', $request->target_language_code)
                ->exists();

            if ($existingService)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Este servicio ya está agregado con la misma combinación de idiomas.',
                ], 400);
            }

            $projectFare = $project->projectFares()->create([
                'fare_id' => $request->fare_id,
                'source_language_code' => $request->source_language_code,
                'target_language_code' => $request->target_language_code,
                'quantity' => $request->quantity,
                'unit' => $request->unit,
            ]);

            // Load relationships for response
            $projectFare->load(['fare', 'sourceLanguage', 'targetLanguage']);

            return response()->json([
                'success' => true,
                'service' => [
                    'id' => $projectFare->id,
                    'fare_id' => $projectFare->fare_id,
                    'fare_name' => $projectFare->fare->name,
                    'source_language_code' => $projectFare->source_language_code,
                    'source_language_name' => $projectFare->sourceLanguage->name,
                    'source_country_code' => $projectFare->sourceLanguage->country_code ?? '',
                    'target_language_code' => $projectFare->target_language_code,
                    'target_language_name' => $projectFare->targetLanguage->name,
                    'target_country_code' => $projectFare->targetLanguage->country_code ?? '',
                    'quantity' => $projectFare->quantity,
                    'unit' => $projectFare->unit,
                ],
                'message' => 'Servicio agregado exitosamente.',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el servicio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a single service for a project (modal)
     */
    public function updateService(Request $request, string $projectId, string $serviceId)
    {
        $project = Project::findOrFail($projectId);
        $projectFare = $project->projectFares()->findOrFail($serviceId);

        $request->validate([
            'fare_id' => 'required|exists:fares,id',
            'source_language_code' => 'required|exists:language_variants,code',
            'target_language_code' => 'required|exists:language_variants,code',
            'quantity' => 'required|numeric|min:1',
            'unit' => 'required|string',
        ]);

        try
        {
            // Check for duplicates (excluding current service)
            $existingService = $project
                ->projectFares()
                ->where('fare_id', $request->fare_id)
                ->where('source_language_code', $request->source_language_code)
                ->where('target_language_code', $request->target_language_code)
                ->where('id', '!=', $serviceId)
                ->exists();

            if ($existingService)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Este servicio ya está agregado con la misma combinación de idiomas.',
                ], 400);
            }

            $projectFare->update([
                'fare_id' => $request->fare_id,
                'source_language_code' => $request->source_language_code,
                'target_language_code' => $request->target_language_code,
                'quantity' => $request->quantity,
                'unit' => $request->unit,
            ]);

            // Load relationships for response
            $projectFare->load(['fare', 'sourceLanguage', 'targetLanguage']);

            return response()->json([
                'success' => true,
                'service' => [
                    'id' => $projectFare->id,
                    'fare_id' => $projectFare->fare_id,
                    'fare_name' => $projectFare->fare->name,
                    'source_language_code' => $projectFare->source_language_code,
                    'source_language_name' => $projectFare->sourceLanguage->name,
                    'source_country_code' => $projectFare->sourceLanguage->country_code ?? '',
                    'target_language_code' => $projectFare->target_language_code,
                    'target_language_name' => $projectFare->targetLanguage->name,
                    'target_country_code' => $projectFare->targetLanguage->country_code ?? '',
                    'quantity' => $projectFare->quantity,
                    'unit' => $projectFare->unit,
                ],
                'message' => 'Servicio actualizado exitosamente.',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el servicio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a single service for a project (modal)
     */
    public function deleteService(string $projectId, string $serviceId)
    {
        $project = Project::findOrFail($projectId);
        $projectFare = $project->projectFares()->findOrFail($serviceId);

        try
        {
            $projectFare->delete();

            return response()->json([
                'success' => true,
                'message' => 'Servicio eliminado exitosamente.',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el servicio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format date for message templates
     */
    private function formatDate($date)
    {
        if (! $date)
        {
            return 'N/A';
        }

        try
        {
            // If it's already a Carbon instance
            if ($date instanceof Carbon)
            {
                return $date->format('d/m/Y');
            }

            // If it's a string, try to parse it
            if (is_string($date))
            {
                return Carbon::parse($date)->format('d/m/Y');
            }

            // If it's a DateTime object
            if ($date instanceof \DateTime)
            {
                return $date->format('d/m/Y');
            }

            return 'N/A';
        } catch (\Exception $e)
        {
            return 'N/A';
        }
    }
}
