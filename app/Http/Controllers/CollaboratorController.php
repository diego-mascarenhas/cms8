<?php

namespace App\Http\Controllers;

use App\DataTables\CollaboratorDataTable;
use App\Models\Contact;
use App\Models\ContactLanguageVariant;
use App\Models\ContactValoration;
use App\Models\Language;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    public function index(CollaboratorDataTable $dataTable)
    {
        return $dataTable->render('collaborator.index');
    }

    public function create()
    {
        return view('collaborator.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'language' => 'required|string|exists:languages,code',
            'profile' => 'nullable|string',
            'language_pairs' => 'nullable|array',
            'is_native' => 'nullable|array',
            'fare_ids' => 'nullable|array',
        ]);

        // Add creator_id and team_id automatically
        $validated['creator_id'] = auth()->user()->id;
        $validated['team_id'] = auth()->user()->currentTeam->id;

        $contact = Contact::create([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'language' => $validated['language'],
            'profile' => $validated['profile'] ?? null,
            'creator_id' => $validated['creator_id'],
            'team_id' => $validated['team_id'],
        ]);

        // Process language pairs if they exist
        if ($request->has('language_pairs') && is_array($request->language_pairs) && count($request->language_pairs) > 0) {
            $processedPairs = []; // Para evitar duplicados

            foreach ($request->language_pairs as $index => $pair) {
                if (empty($pair)) {
                    continue;
                }

                [$sourceLanguage, $targetLanguage] = explode('|', $pair);

                // Evitar duplicados en la misma solicitud
                $pairKey = $sourceLanguage . '-' . $targetLanguage;
                if (in_array($pairKey, $processedPairs)) {
                    continue;
                }

                $processedPairs[] = $pairKey;

                $isNative = isset($request->is_native[$index]) ? (bool) $request->is_native[$index] : false;

                try {
                    ContactLanguageVariant::create([
                        'contact_id' => $contact->id,
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                        'is_certified' => $isNative,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Si es un error de duplicado, simplemente lo ignoramos
                    if ($e->errorInfo[1] == 1062) {
                        continue;
                    }
                    throw $e; // Si es otro tipo de error, lo lanzamos
                }
            }
        }

        // Process fares/services if they exist
        if ($request->has('fare_ids') && is_array($request->fare_ids) && count($request->fare_ids) > 0) {
            // Filter out empty values
            $fareIds = array_filter($request->fare_ids, function ($value) {
                return ! empty($value);
            });

            // Sync fares with the contact
            if (! empty($fareIds)) {
                $contact->fares()->sync($fareIds);
            }
        }

        return redirect()->route('collaborator.show', $contact->id)
            ->with('success', __('Collaborator created successfully.'));
    }

    public function show($id)
    {
        $collaborator = Contact::with([
            'softwares.type',
            'user.roles',
            'valoration',
            'fares.type',
            'topics',
            'country',
            'language',
            'status',
            'weeklyAvailability',
            'portfolios' => function ($query) {
                $query->orderBy('year', 'desc');
            },
            'projects' => function ($query) {
                $query->with(['responsible', 'enterprise', 'status'])
                    ->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        return view('collaborator.show', compact('collaborator'));
    }

    public function edit($id)
    {
        $collaborator = Contact::with([
            'languageVariants.sourceLanguage',
            'languageVariants.targetLanguage',
            'fares',
        ])->findOrFail($id);

        // Force language loading
        $languagePairs = [];

        foreach ($collaborator->languageVariants as $variant) {
            $sourceLanguage = $variant->sourceLanguage;
            $targetLanguage = $variant->targetLanguage;

            $languagePairs[] = [
                'source_language' => $variant->source_language_code,
                'target_language' => $variant->target_language_code,
                'source_language_text' => $sourceLanguage ? $sourceLanguage->name : $variant->source_language_code,
                'target_language_text' => $targetLanguage ? $targetLanguage->name : $variant->target_language_code,
                'is_native' => $variant->is_certified,
            ];
        }

        $collaborator->languagePairs = $languagePairs;

        return view('collaborator.form', compact('collaborator'));
    }

    public function update(Request $request, $id)
    {
        $collaborator = Contact::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'language' => 'required|string|exists:languages,code',
            'profile' => 'nullable|string',
            'language_pairs' => 'nullable|array',
            'is_native' => 'nullable|array',
            'fare_ids' => 'nullable|array',
        ]);

        $collaborator->update([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'language' => $validated['language'],
            'profile' => $validated['profile'] ?? null,
        ]);

        // Process language pairs if they exist
        if ($request->has('language_pairs') && is_array($request->language_pairs) && count($request->language_pairs) > 0) {
            // Delete existing language pairs
            $collaborator->languageVariants()->delete();

            $processedPairs = []; // Para evitar duplicados

            // Add new language pairs
            foreach ($request->language_pairs as $index => $pair) {
                if (empty($pair)) {
                    continue;
                }

                [$sourceLanguage, $targetLanguage] = explode('|', $pair);

                // Evitar duplicados en la misma solicitud
                $pairKey = $sourceLanguage . '-' . $targetLanguage;
                if (in_array($pairKey, $processedPairs)) {
                    continue;
                }

                $processedPairs[] = $pairKey;

                $isNative = isset($request->is_native[$index]) ? (bool) $request->is_native[$index] : false;

                try {
                    ContactLanguageVariant::create([
                        'contact_id' => $collaborator->id,
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                        'is_certified' => $isNative,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // If it's a duplicate error, simply ignore it
                    if ($e->errorInfo[1] == 1062) {
                        continue;
                    }
                    throw $e; // If it's another type of error, throw it
                }
            }
        } else {
            // If there are no language pairs, delete all existing ones
            $collaborator->languageVariants()->delete();
        }

        // Process fares/services if they exist
        if ($request->has('fare_ids')) {
            // Get the fare IDs or use empty array if none provided
            $fareIds = $request->fare_ids ?? [];

            // Filter out empty values
            $fareIds = array_filter($fareIds, function ($value) {
                return ! empty($value);
            });

            // Sync fares with the collaborator - passing an empty array removes all associations
            $collaborator->fares()->sync($fareIds);
        }

        return redirect()->route('collaborator.show', $id)
            ->with('success', __('Collaborator updated successfully.'));
    }

    public function destroy($id)
    {
        $collaborator = Contact::findOrFail($id);
        $collaborator->delete();

        return redirect()->route('collaborator-list')
            ->with('success', __('Collaborator deleted successfully.'));
    }

    /**
     * Mark collaborator as watch (Ojo)
     */
    public function markAsWatch($id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        $teamId = auth()->user()->currentTeam->id;

        // Find the "Ojo" valoration (assuming it corresponds to "En espera" for watch status)
        $watchValoration = ContactValoration::where('team_id', $teamId)
            ->where('name', 'En espera')
            ->first();

        if ($watchValoration) {
            $collaborator->update(['valoration_id' => $watchValoration->id]);

            return response()->json([
                'success' => true,
                'message' => 'Colaborador marcado como ojo correctamente',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo encontrar la valoración de supervisión',
        ], 400);
    }

    /**
     * Send collaborator to blacklist
     */
    public function sendToBlacklist($id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        $teamId = auth()->user()->currentTeam->id;

        // Find the "Lista negra" valoration
        $blacklistValoration = ContactValoration::where('team_id', $teamId)
            ->where('name', 'Lista negra')
            ->first();

        if ($blacklistValoration) {
            $collaborator->update(['valoration_id' => $blacklistValoration->id]);

            return response()->json([
                'success' => true,
                'message' => 'Colaborador enviado a lista negra',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo encontrar la valoración de lista negra',
        ], 400);
    }

    /**
     * Send notification to collaborator
     */
    public function sendNotification(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        // Redirect to the new notification system
        return app(NotificationController::class)->quickSend($request, $id);
    }

    /**
     * Update collaborator software
     */
    public function updateSoftware(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);

        // Get software IDs, they can come as array, string or JSON
        $softwareIds = [];

        // If it's a JSON request
        if ($request->isJson()) {
            $data = $request->json()->all();
            $softwareIds = $data['software_ids'] ?? [];
        } else {
            // If it's a normal request
            $softwareIds = $request->input('software_ids', []);
        }

        // If it comes as empty string, convert to empty array
        if ($softwareIds === '') {
            $softwareIds = [];
        }

        // If it comes as a single ID as string, convert it to array
        if (! is_array($softwareIds) && ! empty($softwareIds)) {
            $softwareIds = [$softwareIds];
        }

        // Filter out empty or null values that may cause errors
        $softwareIds = array_filter($softwareIds, function ($value) {
            return ! empty($value) && $value !== '' && $value !== null;
        });

        // Sync software - use empty array explicitly if no IDs
        $collaborator->softwares()->sync(empty($softwareIds) ? [] : $softwareIds);

        // Load updated softwares with types
        $collaborator->load('softwares.type');

        // Format response data
        $softwares = $collaborator->softwares->map(function ($software) {
            return [
                'id' => $software->id,
                'name' => $software->name,
                'type_name' => $software->type ? $software->type->name : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Software actualizado correctamente',
            'softwares' => $softwares,
        ]);
    }

    /**
     * Update collaborator services
     */
    public function updateServices(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);

        // Get service IDs, they can come as array, string or JSON
        $fareIds = [];

        // If it's a JSON request
        if ($request->isJson()) {
            $data = $request->json()->all();
            $fareIds = $data['fare_ids'] ?? [];
        } else {
            // If it's a normal request
            $fareIds = $request->input('fare_ids', []);
        }

        // If it comes as empty string, convert to empty array
        if ($fareIds === '') {
            $fareIds = [];
        }

        // If it comes as a single ID as string, convert it to array
        if (! is_array($fareIds) && ! empty($fareIds)) {
            $fareIds = [$fareIds];
        }

        // Filtrar valores vacíos o nulos que puedan causar errores
        $fareIds = array_filter($fareIds, function ($value) {
            return ! empty($value) && $value !== '' && $value !== null;
        });

        // Sync fares
        $collaborator->fares()->sync(empty($fareIds) ? [] : $fareIds);

        // Load updated fares with types
        $collaborator->load('fares.type');

        // Format response data (use unique fares to avoid duplicates)
        $services = $collaborator->fares->unique('id')->map(function ($fare) {
            return [
                'id' => $fare->id,
                'name' => $fare->name,
                'type_name' => $fare->type ? $fare->type->name : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Servicios actualizados correctamente',
            'services' => $services,
        ]);
    }

    /**
     * Update collaborator topics
     */
    public function updateTopics(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);

        // Get topic IDs, they can come as array, string or JSON
        $topicIds = [];

        // If it's a JSON request
        if ($request->isJson()) {
            $data = $request->json()->all();
            $topicIds = $data['topic_ids'] ?? [];
        } else {
            // If it's a normal request
            $topicIds = $request->input('topic_ids', []);
        }

        // If it comes as empty string, convert to empty array
        if ($topicIds === '') {
            $topicIds = [];
        }

        // If it comes as a single ID as string, convert it to array
        if (! is_array($topicIds) && ! empty($topicIds)) {
            $topicIds = [$topicIds];
        }

        // Filter out empty or null values that may cause errors
        $topicIds = array_filter($topicIds, function ($value) {
            return ! empty($value) && $value !== '' && $value !== null;
        });

        // Sync topics - use empty array explicitly if no IDs
        $collaborator->topics()->sync(empty($topicIds) ? [] : $topicIds);

        // Load updated topics
        $collaborator->load('topics');

        // Format response data
        $topics = $collaborator->topics->map(function ($topic) {
            return [
                'id' => $topic->id,
                'name' => $topic->name,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Temáticas actualizadas correctamente',
            'topics' => $topics,
        ]);
    }

    /**
     * Update collaborator valoration
     */
    public function updateValoration(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $request->validate([
            'valoration_id' => 'required|exists:contact_valorations,id',
        ]);

        $collaborator = Contact::findOrFail($id);
        $collaborator->update(['valoration_id' => $request->valoration_id]);

        // Get the updated valoration details
        $valoration = $collaborator->valoration;

        return response()->json([
            'success' => true,
            'message' => 'Valoración actualizada correctamente',
            'valoration' => [
                'id' => $valoration->id,
                'name' => $valoration->name,
                'icon' => $valoration->icon,
            ],
        ]);
    }

    /**
     * Link an existing user to a collaborator
     */
    public function linkUser(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $collaborator = Contact::findOrFail($id);
        $user = \App\Models\User::findOrFail($request->user_id);

        // Check if user belongs to the same team
        if (! $user->teams->contains(auth()->user()->currentTeam->id)) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no pertenece al equipo actual',
            ], 422);
        }

        // Check if user is already linked to another contact
        $existingContact = Contact::where('user_id', $user->id)->first();
        if ($existingContact && $existingContact->id !== $collaborator->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este usuario ya está vinculado a otro contacto',
            ], 422);
        }

        $collaborator->update(['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario vinculado correctamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()->name ?? 'user',
            ],
        ]);
    }

    /**
     * Unlink user from collaborator
     */
    public function unlinkUser($id)
    {
        $collaborator = Contact::findOrFail($id);
        $collaborator->update(['user_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario desvinculado correctamente',
        ]);
    }

    /**
     * Create a new user and link to collaborator
     */
    public function createAndLinkUser(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|exists:roles,name',
            'password' => 'required|string|min:8',
        ]);

        $collaborator = Contact::findOrFail($id);

        try {
            // Create the user
            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone ? preg_replace('/[^0-9]/', '', $request->phone) : null,
                'password' => \Hash::make($request->password),
                'current_team_id' => auth()->user()->currentTeam->id,
                'email_verified_at' => null, // Force email verification
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Add user to current team
            $user->teams()->attach(auth()->user()->currentTeam->id);

            // Link user to collaborator
            $collaborator->update(['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado y vinculado correctamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $request->role,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new portfolio item
     */
    public function storePortfolio(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'notes' => 'nullable|string',
            'position' => 'nullable|string',
            'languages' => 'nullable|array',
        ]);

        $collaborator = Contact::findOrFail($id);

        $data = [];
        if ($request->position) {
            $data['position'] = $request->position;
        }
        if ($request->languages) {
            $data['languages'] = $request->languages;
        }

        $portfolio = $collaborator->portfolios()->create([
            'title' => $request->title,
            'description' => $request->description,
            'year' => $request->year,
            'notes' => $request->notes,
            'data' => $data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Portfolio agregado correctamente',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Update a portfolio item
     */
    public function updatePortfolio(Request $request, $id, $portfolioId)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'notes' => 'nullable|string',
            'position' => 'nullable|string',
            'languages' => 'nullable|array',
        ]);

        $collaborator = Contact::findOrFail($id);
        $portfolio = $collaborator->portfolios()->findOrFail($portfolioId);

        $data = [];
        if ($request->position) {
            $data['position'] = $request->position;
        }
        if ($request->languages) {
            $data['languages'] = $request->languages;
        }

        $portfolio->update([
            'title' => $request->title,
            'description' => $request->description,
            'year' => $request->year,
            'notes' => $request->notes,
            'data' => $data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Portfolio actualizado correctamente',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Delete a portfolio item
     */
    public function destroyPortfolio($id, $portfolioId)
    {
        if (! auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        $portfolio = $collaborator->portfolios()->findOrFail($portfolioId);

        $portfolio->delete();

        return response()->json([
            'success' => true,
            'message' => 'Portfolio eliminado correctamente',
        ]);
    }

    /**
     * Show notifications for a collaborator
     */
    public function notifications($id)
    {
        $collaborator = Contact::findOrFail($id);
        
        // Get notifications for this collaborator (contact)
        $notifications = \App\Models\Notification::with(['type', 'user'])
            ->where('contact_id', $id)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('collaborator.notifications', compact('collaborator', 'notifications'));
    }

    /**
     * Show collaborators dashboard
     */
    public function dashboard()
    {
        // Get total count of active collaborators
        $totalCollaborators = Contact::getTotalCollaborators();
        
        // Get new collaborators this month
        $newCollaboratorsThisMonth = Contact::getNewCollaboratorsThisMonth();
        
        // Get language combinations with less than 10 collaborators using the model method
        $languageCombinations = ContactLanguageVariant::getCombinationsWithFewCollaborators();
        
        // Get top languages by collaborator count
        $topLanguages = Language::getTopLanguages(5);
        
        // Get collaborators with incomplete data
        $incompleteCollaborators = Contact::getIncompleteCollaborators(20);

        return view('collaborator.dashboard', compact('totalCollaborators', 'newCollaboratorsThisMonth', 'languageCombinations', 'topLanguages', 'incompleteCollaborators'));
    }
}
