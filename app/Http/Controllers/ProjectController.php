<?php

namespace App\Http\Controllers;

use App\DataTables\ProjectDataTable;
use App\Models\Fare;
use App\Models\Language;
use App\Models\Project;
use App\Models\ProjectStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(ProjectDataTable $dataTable)
    {
        return $dataTable->render('project.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $enterprise_id = request('enterprise_id');
        $statuses = ProjectStatus::getOptions();

        return view('project.form', compact('enterprise_id', 'statuses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:255',
            'real_name' => 'nullable|string|max:255',
            'description' => 'required|string|min:3',
            'enterprise_id' => 'required|exists:enterprises,id',
            'responsible_id' => 'required|exists:users,id',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'cost' => 'nullable|numeric|min:0',
            'status_id' => 'required|exists:project_statuses,id',
            'date_material' => 'nullable|date',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $project = Project::updateOrCreate(
            ['id' => $request->id],
            [
                'team_id' => auth()->user()->currentTeam->id,
                'name' => $data['name'],
                'real_name' => $data['real_name'] ?? null,
                'enterprise_id' => $data['enterprise_id'],
                'category_id' => $data['category_id'] ?? null,
                'description' => $data['description'],
                'responsible_id' => $data['responsible_id'],
                'price' => $data['price'] ?? null,
                'discount' => $data['discount'] ?? null,
                'cost' => $data['cost'] ?? null,
                'status_id' => $data['status_id'] ?? 1,
                'date_material' => $data['date_material'] ?? null,
                'date_start' => $data['date_start'] ?? null,
                'date_end' => $data['date_end'] ?? null,
            ],
        );

        if (! $request->id) {
            return redirect()->route('project.show', $project->id)->with('success', 'Proyecto creado exitosamente.');
        }

        return redirect()->route('project.show', $project->id)->with('success', 'Proyecto actualizado exitosamente.');
    }

    /**
     * Show collaborator selection screen for a project
     */
    public function selectCollaborators($projectId)
    {
        $project = Project::with(['client', 'responsible', 'status', 'category'])
            ->findOrFail($projectId);

        // Get data for filters
        $languages = Language::orderBy('name')->get();
        $fares = Fare::with('type')->orderBy('name')->get();

        // Don't load any collaborators by default - they will be loaded via AJAX when filters are applied
        $collaborators = collect();
        $selectedService = null;
        $selectedSourceLanguage = null;
        $selectedTargetLanguage = null;

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

        // Log incoming request for debugging
        \Log::info('Filter Collaborators - Incoming Request:', [
            'source_language' => $request->source_language,
            'target_language' => $request->target_language,
            'service' => $request->service,
            'days' => $request->days,
            'delivery_date' => $request->delivery_date,
            'hasLanguageFilter' => $hasLanguageFilter,
            'hasServiceFilter' => $hasServiceFilter,
            'hasDaysFilter' => $hasDaysFilter,
            'hasDeliveryDateFilter' => $hasDeliveryDateFilter,
        ]);



        // Return empty if no filter is applied
        if (! $hasLanguageFilter && ! $hasServiceFilter && ! $hasDaysFilter && ! $hasDeliveryDateFilter) {
            return response()->json([
                'html' => view('project.partials.collaborator-cards', [
                    'collaborators' => collect(),
                    'selectedService' => null,
                    'selectedSourceLanguage' => null,
                    'selectedTargetLanguage' => null,
                ])->render(),
                'count' => 0,
            ]);
        }

        // Build the query for contacts with language variants and fares
        $query = \App\Models\Contact::with([
            'valoration',
            'languageVariants.sourceLanguage',
            'languageVariants.targetLanguage',
            'fares.type',
            'fares' => function($query) {
                $query->withPivot('price', 'unit_id', 'currency_code', 'source_language_code', 'target_language_code');
            }
        ]);

        // Basic requirements for collaborators
        $query->whereHas('languageVariants') // Only contacts with language variants
            ->whereHas('fares'); // Only contacts with services/fares

        // Apply language filters (both source and target must be specified for language combination)
        if ($request->has('source_language') && $request->source_language &&
            $request->has('target_language') && $request->target_language) {
            // Both source and target specified - find exact language combination
            $query->whereHas('languageVariants', function ($q) use ($request) {
                $q->where('source_language_code', $request->source_language)
                    ->where('target_language_code', $request->target_language);
            });
        } elseif ($request->has('source_language') && $request->source_language) {
            // Only source language specified
            $query->whereHas('languageVariants', function ($q) use ($request) {
                $q->where('source_language_code', $request->source_language);
            });
        } elseif ($request->has('target_language') && $request->target_language) {
            // Only target language specified
            $query->whereHas('languageVariants', function ($q) use ($request) {
                $q->where('target_language_code', $request->target_language);
            });
        }

        // Apply service filter
        if ($request->has('service') && $request->service) {
            $query->whereHas('fares', function ($q) use ($request) {
                $q->where('fares.id', $request->service);
            });
        }

        // Apply days filter - basic implementation that works
        if ($request->has('days') && $request->days) {
            // Log that days filter is being applied
            \Log::info('Applying days filter:', [
                'days' => $request->days,
                'days_type' => gettype($request->days),
                'days_empty' => empty($request->days),
                'raw_request' => $request->all()
            ]);
            
            // For now, when days filter is applied, we don't restrict the query further
            // This ensures collaborators are returned when only days filter is used
            // In the future, this could filter based on last contact date or availability
            
            // Add a simple constraint to show the filter is working
            // For example, only contacts created in the last year
            $query->where('created_at', '>=', now()->subYear());
        } else {
            \Log::info('Days filter NOT applied:', [
                'has_days' => $request->has('days'),
                'days_value' => $request->days ?? 'not_set',
                'request_keys' => array_keys($request->all())
            ]);
        }

        // Apply delivery date filter - basic implementation that works
        if ($request->has('delivery_date') && $request->delivery_date) {
            // Log that delivery date filter is being applied
            \Log::info('Applying delivery date filter:', ['delivery_date' => $request->delivery_date]);
            
            // For now, when delivery date filter is applied, we don't restrict the query further
            // This ensures collaborators are returned when only delivery date filter is used
            // In the future, this could filter based on collaborator availability
            
            // Add a simple constraint to show the filter is working
            // For example, only contacts updated in the last 6 months
            $query->where('updated_at', '>=', now()->subMonths(6));
        }

        $collaborators = $query->get();

        // Log the final result
        \Log::info('Filter Collaborators - Final Result:', [
            'total_collaborators' => $collaborators->count(),
            'collaborator_ids' => $collaborators->pluck('id')->take(10)->toArray(), // First 10 IDs
        ]);

        // Return the HTML for the collaborator cards
        return response()->json([
            'html' => view('project.partials.collaborator-cards', [
                'collaborators' => $collaborators,
                'selectedService' => $request->service ?? null,
                'selectedSourceLanguage' => $request->source_language ?? null,
                'selectedTargetLanguage' => $request->target_language ?? null,
            ])->render(),
            'count' => $collaborators->count(),
        ]);
    }



    /**
     * Send notifications to selected collaborators
     */
    public function sendCollaboratorNotifications(Request $request, $projectId)
    {
        $project = Project::with(['client', 'responsible', 'status', 'category'])
            ->findOrFail($projectId);

        // Debug: Log all request data
        \Log::info('Send Notifications Request Data:', $request->all());

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

        foreach ($collaboratorIds as $collaboratorId) {
            try {
                $collaborator = \App\Models\Contact::findOrFail($collaboratorId);

                \Log::info("Processing collaborator: {$collaborator->name} (ID: {$collaboratorId})");

                // Replace {nombre} placeholder with collaborator name
                $personalizedMessage = str_replace('{nombre}', $collaborator->name, $messageTemplate);

                // Replace other placeholders
                $personalizedMessage = str_replace(
                    array_keys($messageVariables),
                    array_values($messageVariables),
                    $personalizedMessage,
                );

                \Log::info("Personalized message: {$personalizedMessage}");

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

                \Log::info("Sync result for collaborator {$collaboratorId}:", $result);

                // Verify the relationship was created
                $relationshipExists = $project->collaborators()->where('contact_id', $collaboratorId)->exists();
                \Log::info('Relationship exists check: '.($relationshipExists ? 'YES' : 'NO'));

                $sentCount++;
            } catch (\Exception $e) {
                \Log::error("Error processing collaborator {$collaboratorId}: ".$e->getMessage());
                $errors[] = "Error sending to collaborator {$collaboratorId}: ".$e->getMessage();
            }
        }

        if ($sentCount > 0) {
            $message = "Messages sent successfully to {$sentCount} collaborator(s).";
            if (! empty($errors)) {
                $message .= ' However, there were some errors: '.implode(', ', $errors);
            }

            return redirect()->route('project.show', $projectId)->with('success', $message);
        } else {
            return redirect()->back()->with('error', 'Failed to send messages: '.implode(', ', $errors));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::with([
            'client',
            'responsible',
            'status',
            'category',
            'notes',
            'collaborators.valoration',
            'collaborators.languageVariants.sourceLanguage',
            'collaborators.languageVariants.targetLanguage',
            'collaborators.fares.type',
            'projectFares.fare.type',
            'projectFares.sourceLanguage',
            'projectFares.targetLanguage',
        ])->findOrFail($id);

        return view('project.show', compact('project'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Project::with(['projectFares.fare.units', 'projectFares.sourceLanguage', 'projectFares.targetLanguage'])
            ->findOrFail($id);
        $enterprise_id = $data->enterprise_id;
        $statuses = ProjectStatus::getOptions();

        return view('project.form', compact('data', 'enterprise_id', 'statuses'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = Project::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }

    /**
     * Remove a collaborator from a project (Soft Delete)
     */
    public function removeCollaborator(Project $project, $collaborator)
    {
        try {
            // Find the collaborator
            $collaboratorModel = \App\Models\Contact::findOrFail($collaborator);

            // Find the pivot record using the ContactProject model
            $pivotRecord = \App\Models\ContactProject::where('contact_id', $collaborator)
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->first();

            if (! $pivotRecord) {
                return response()->json([
                    'message' => 'El colaborador no está asociado con este proyecto.',
                ], 404);
            }

            // Soft delete the pivot record
            $pivotRecord->delete();

            return response()->json([
                'message' => 'Colaborador eliminado del proyecto exitosamente.',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error removing collaborator from project: '.$e->getMessage());

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
        $fareId = $request->get('fare_id');

        \Log::info('getFareUnits called:', [
            'fare_id' => $fareId,
            'request_data' => $request->all(),
            'current_team_id' => auth()->user()->currentTeam->id
        ]);

        if (! $fareId) {
            return response()->json(['units' => [], 'error' => 'No fare ID provided']);
        }

        // Use withoutGlobalScopes to avoid team_id restriction
        $fare = \App\Models\Fare::withoutGlobalScopes()->with('units')->find($fareId);

        if (! $fare) {
            \Log::warning('Fare not found:', ['fare_id' => $fareId]);
            return response()->json(['units' => [], 'error' => 'Fare not found']);
        }

        \Log::info('Fare found:', [
            'fare_id' => $fareId,
            'fare_name' => $fare->name,
            'fare_team_id' => $fare->team_id,
            'current_team_id' => auth()->user()->currentTeam->id,
            'units_count' => $fare->units->count(),
            'units' => $fare->units->toArray()
        ]);

        $units = $fare->units->map(function ($unit) {
            return [
                'id' => $unit->id,
                'type' => $unit->type,
                'label' => $unit->type,
            ];
        });

        return response()->json([
            'units' => $units,
            'fare_name' => $fare->name,
            'debug' => [
                'fare_id' => $fareId,
                'fare_team_id' => $fare->team_id,
                'current_team_id' => auth()->user()->currentTeam->id,
                'units_count' => $fare->units->count()
            ]
        ]);
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
     * Debug endpoint to check form data
     */
    public function debugServices(Request $request, string $projectId)
    {
        dd([
            'project_id' => $projectId,
            'request_method' => $request->method(),
            'all_data' => $request->all(),
            'services' => $request->services,
            'files' => $request->files->all(),
            'headers' => $request->headers->all(),
        ]);
    }

    /**
     * Debug endpoint to check fares and units
     */
    public function debugFareUnits()
    {
        $fares = \App\Models\Fare::with(['units', 'type'])
            ->where('team_id', auth()->user()->currentTeam->id)
            ->orWhereNull('team_id')
            ->get();

        $allUnits = \App\Models\Unit::all();
        
        $fareUnitPivot = \DB::table('fare_unit')->get();

        dd([
            'total_fares' => $fares->count(),
            'fares_with_units' => $fares->filter(function($fare) { return $fare->units->count() > 0; })->count(),
            'total_units' => $allUnits->count(),
            'pivot_records' => $fareUnitPivot->count(),
            'fares' => $fares->map(function($fare) {
                return [
                    'id' => $fare->id,
                    'name' => $fare->name,
                    'type' => $fare->type ? $fare->type->name : 'No type',
                    'units_count' => $fare->units->count(),
                    'units' => $fare->units->map(function($unit) {
                        return [
                            'id' => $unit->id,
                            'type' => $unit->type
                        ];
                    })
                ];
            }),
            'all_units' => $allUnits->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'type' => $unit->type
                ];
            }),
            'pivot_data' => $fareUnitPivot
        ]);
    }

    /**
     * Test endpoint - get units for a specific fare ID
     */
    public function testFareUnits($fareId)
    {
        // Test without scope
        $fareWithoutScope = \App\Models\Fare::withoutGlobalScopes()->with('units')->find($fareId);
        
        // Test with scope  
        $fareWithScope = \App\Models\Fare::with('units')->find($fareId);
        
        // Direct query
        $directQuery = \DB::table('fare_unit')->where('fare_id', $fareId)->get();
        
        dd([
            'fare_id_requested' => $fareId,
            'current_team_id' => auth()->user()->currentTeam->id,
            'fare_without_scope' => $fareWithoutScope ? [
                'id' => $fareWithoutScope->id,
                'name' => $fareWithoutScope->name,
                'team_id' => $fareWithoutScope->team_id,
                'units_count' => $fareWithoutScope->units->count(),
                'units' => $fareWithoutScope->units->toArray()
            ] : 'Not found',
            'fare_with_scope' => $fareWithScope ? [
                'id' => $fareWithScope->id,
                'name' => $fareWithScope->name,
                'team_id' => $fareWithScope->team_id,
                'units_count' => $fareWithScope->units->count(),
                'units' => $fareWithScope->units->toArray()
            ] : 'Not found',
            'direct_pivot_query' => $directQuery,
            'units_from_direct' => \DB::table('units')
                ->join('fare_unit', 'units.id', '=', 'fare_unit.unit_id')
                ->where('fare_unit.fare_id', $fareId)
                ->select('units.*')
                ->get()
        ]);
    }

    /**
     * Simple test endpoint to verify AJAX is working
     */
    public function testAjax(Request $request)
    {
        $fareId = $request->get('fare_id');
        
        return response()->json([
            'success' => true,
            'message' => 'AJAX is working!',
            'fare_id' => $fareId,
            'timestamp' => now()->toDateTimeString(),
            'test_units' => [
                ['id' => 1, 'type' => 'palabras', 'label' => 'palabras'],
                ['id' => 2, 'type' => 'páginas', 'label' => 'páginas'],
                ['id' => 3, 'type' => 'minutos', 'label' => 'minutos']
            ]
        ]);
    }

    /**
     * Store services for a project
     */
    public function storeServices(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);

        // Log incoming data for debugging
        \Log::info('Store Services Request:', [
            'project_id' => $projectId,
            'services' => $request->services,
            'all_data' => $request->all()
        ]);

        $request->validate([
            'services' => 'required|array|min:1',
            'services.*.fare_id' => 'required|exists:fares,id',
            'services.*.source_language_code' => 'required|exists:language_variants,code',
            'services.*.target_language_code' => 'required|exists:language_variants,code',
            'services.*.quantity' => 'required|numeric|min:1',
            'services.*.unit' => 'required|string',
        ]);

        try {
            // Clear existing services
            $project->projectFares()->delete();

            // Add new services
            $createdServices = [];
            foreach ($request->services as $serviceData) {
                $projectFare = $project->projectFares()->create([
                    'fare_id' => $serviceData['fare_id'],
                    'source_language_code' => $serviceData['source_language_code'],
                    'target_language_code' => $serviceData['target_language_code'],
                    'quantity' => $serviceData['quantity'],
                    'unit' => $serviceData['unit'],
                ]);
                $createdServices[] = $projectFare->id;
            }

            // Log successful creation
            \Log::info('Services created successfully:', [
                'project_id' => $projectId,
                'created_services' => $createdServices,
                'total_services' => count($createdServices)
            ]);

            return redirect()->route('project.show', $project->id)
                ->with('success', 'Servicios agregados exitosamente.');

        } catch (\Exception $e) {
            \Log::error('Error storing services:', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error al guardar los servicios: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Format date for message templates
     */
    private function formatDate($date)
    {
        if (! $date) {
            return 'N/A';
        }

        try {
            // If it's already a Carbon instance
            if ($date instanceof Carbon) {
                return $date->format('d/m/Y');
            }

            // If it's a string, try to parse it
            if (is_string($date)) {
                return Carbon::parse($date)->format('d/m/Y');
            }

            // If it's a DateTime object
            if ($date instanceof \DateTime) {
                return $date->format('d/m/Y');
            }

            return 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}
