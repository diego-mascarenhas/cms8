<?php

namespace App\Http\Controllers;

use App\DataTables\ProjectDataTable;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Language;
use App\Models\Fare;
use Illuminate\Http\Request;
use App\Models\Enterprise;
use App\Models\User;
use Carbon\Carbon;

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
            ]
        );

        // If it's a new project (not editing), redirect to collaborator selection
        if (!$request->id) {
            return redirect()->route('project.select-collaborators', $project->id)
                ->with('success', 'Project created successfully. Now select collaborators to notify.');
        }

        return redirect()->route('project.show', $project->id)->with('success', 'Project updated successfully.');
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
            
        return view('project.select-collaborators', compact('project', 'languages', 'fares', 'collaborators'));
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
        $hasServiceFilter = $request->has('servicio') && $request->servicio;
        
        // Return empty if no language combination or service filter is applied
        if (!$hasLanguageFilter && !$hasServiceFilter) {
            return response()->json([
                'html' => view('project.partials.collaborator-cards', ['collaborators' => collect()])->render(),
                'count' => 0
            ]);
        }
        
        // Build the query for contacts with language variants and fares
        $query = \App\Models\Contact::with([
            'valoration', 
            'languageVariants.sourceLanguage', 
            'languageVariants.targetLanguage', 
            'fares.type'
        ])->whereHas('languageVariants') // Only contacts with language variants
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
        if ($request->has('servicio') && $request->servicio) {
            $query->whereHas('fares', function ($q) use ($request) {
                $q->where('fares.id', $request->servicio);
            });
        }

        $collaborators = $query->get();

        // Return the HTML for the collaborator cards
        return response()->json([
            'html' => view('project.partials.collaborator-cards', compact('collaborators'))->render(),
            'count' => $collaborators->count()
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
                    $personalizedMessage
                );

                \Log::info("Personalized message: {$personalizedMessage}");

                // Create or update the contact_project relationship
                $result = $project->collaborators()->syncWithoutDetaching([
                    $collaboratorId => [
                        'message_sent' => $personalizedMessage,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                ]);

                \Log::info("Sync result for collaborator {$collaboratorId}:", $result);

                // Verify the relationship was created
                $relationshipExists = $project->collaborators()->where('contact_id', $collaboratorId)->exists();
                \Log::info("Relationship exists check: " . ($relationshipExists ? 'YES' : 'NO'));

                $sentCount++;
            } catch (\Exception $e) {
                \Log::error("Error processing collaborator {$collaboratorId}: " . $e->getMessage());
                $errors[] = "Error sending to collaborator {$collaboratorId}: " . $e->getMessage();
            }
        }

        if ($sentCount > 0) {
            $message = "Messages sent successfully to {$sentCount} collaborator(s).";
            if (!empty($errors)) {
                $message .= " However, there were some errors: " . implode(', ', $errors);
            }
            return redirect()->route('project.show', $projectId)->with('success', $message);
        } else {
            return redirect()->back()->with('error', 'Failed to send messages: ' . implode(', ', $errors));
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
            'collaborators.fares.type'
        ])->findOrFail($id);
            
        return view('project.show', compact('project'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Project::findOrFail($id);
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
            
            if (!$pivotRecord) {
                return response()->json([
                    'message' => 'El colaborador no está asociado con este proyecto.'
                ], 404);
            }
            
            // Soft delete the pivot record
            $pivotRecord->delete();
            
            return response()->json([
                'message' => 'Colaborador eliminado del proyecto exitosamente.'
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error removing collaborator from project: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Ha ocurrido un error al eliminar el colaborador.'
            ], 500);
        }
    }

    /**
     * Format date for message templates
     */
    private function formatDate($date)
    {
        if (!$date) {
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
