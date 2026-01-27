<?php

namespace App\Http\Controllers;

use App\DataTables\CollaboratorDataTable;
use App\Models\Contact;
use App\Models\ContactLanguageVariant;
use App\Models\ContactValoration;
use App\Models\Fare;
use App\Models\Language;
use App\Models\Project;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity; // Added this import for the new method

class CollaboratorController extends Controller
{
    public function index(CollaboratorDataTable $dataTable)
    {
        // Use the Contact Policy for authorization
        $this->authorize('viewAny', Contact::class);

        // Get real statistics for the dashboard cards
        $dashboardStats = [
            'pendingAcceptance' => Contact::getPendingAcceptanceCount(),
            'totalCollaborators' => Contact::getTotalCollaborators(),
            'newThisWeek' => Contact::getNewCollaboratorsThisWeek(),
            'notUpdatedSixMonths' => Contact::getNotUpdatedInSixMonths(),
        ];

        return $dataTable->render('collaborator.index', compact('dashboardStats'));
    }

    public function create()
    {
        return view('collaborator.form');
    }

    public function store(Request $request)
    {
        $hasLanguageVariantsModule = auth()->user()->currentTeam->hasModule('language-variants');

        $validationRules = [
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'profile' => 'nullable|string',
            'language_pairs' => 'nullable|array',
            'is_native' => 'nullable|array',
            'fare_ids' => 'nullable|array',
        ];

        if ($hasLanguageVariantsModule)
        {
            $validationRules['language'] = 'required|string|exists:languages,code';
        } else
        {
            $validationRules['language'] = 'nullable|string|exists:languages,code';
        }

        $validated = $request->validate($validationRules);

        // Add creator_id and team_id automatically
        $validated['creator_id'] = auth()->user()->id;
        $validated['team_id'] = auth()->user()->currentTeam->id;

        // Prepare initial data with extras
        $initialData = (object) [
            'extras' => (object) [
                'nif_cif' => $request->nif_cif ?: null,
                'domicilio' => $request->domicilio ?: null,
                'poblacion' => $request->poblacion ?: null,
                'codigo_postal' => $request->codigo_postal ?: null,
            ],
        ];

        $contact = Contact::create([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'language' => $validated['language'] ?? null,
            'profile' => $validated['profile'] ?? null,
            'creator_id' => $validated['creator_id'],
            'team_id' => $validated['team_id'],
            'data' => $initialData,
        ]);

        // Process language pairs if they exist
        if ($request->has('language_pairs') && is_array($request->language_pairs) && count($request->language_pairs) > 0)
        {
            $processedPairs = []; // Para evitar duplicados

            foreach ($request->language_pairs as $index => $pair)
            {
                if (empty($pair))
                {
                    continue;
                }

                [$sourceLanguage, $targetLanguage] = explode('|', $pair);

                // Evitar duplicados en la misma solicitud
                $pairKey = $sourceLanguage.'-'.$targetLanguage;
                if (in_array($pairKey, $processedPairs))
                {
                    continue;
                }

                $processedPairs[] = $pairKey;

                $isNative = isset($request->is_native[$index]) ? (bool) $request->is_native[$index] : false;

                try
                {
                    ContactLanguageVariant::create([
                        'contact_id' => $contact->id,
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                        'is_certified' => $isNative,
                    ]);
                } catch (\Illuminate\Database\QueryException $e)
                {
                    // Si es un error de duplicado, simplemente lo ignoramos
                    if ($e->errorInfo[1] == 1062)
                    {
                        continue;
                    }
                    throw $e; // Si es otro tipo de error, lo lanzamos
                }
            }
        }

        // Process fares/services if they exist
        if ($request->has('fare_ids') && is_array($request->fare_ids) && count($request->fare_ids) > 0)
        {
            // Filter out empty values
            $fareIds = array_filter($request->fare_ids, function ($value)
            {
                return ! empty($value);
            });

            // Sync fares with the contact
            if (! empty($fareIds))
            {
                $contact->fares()->sync($fareIds);
            }
        }

        return redirect()->route('collaborator.show', $contact->id)
            ->with('success', __('Collaborator created successfully.'));
    }

    public function show($id)
    {
        $collaborator = Contact::with([
            'softwares.category',
            'user.roles',
            'valoration',
            'fares.type',
            'topics',
            'country',
            'language',
            'status',
            'weeklyAvailability',
            'portfolios' => function ($query)
            {
                $query->orderBy('year', 'desc');
            },
            'projects' => function ($query)
            {
                $query->with(['responsible', 'enterprise', 'status'])
                    ->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // Use the Contact Policy for authorization
        $this->authorize('view', $collaborator);

        return view('collaborator.show', compact('collaborator'));
    }

    public function edit($id)
    {
        $collaborator = Contact::with([
            'languageVariants.sourceLanguage',
            'languageVariants.targetLanguage',
            'fares',
        ])->findOrFail($id);

        // Use the Contact Policy for authorization
        $this->authorize('update', $collaborator);

        // Force language loading
        $languagePairs = [];

        foreach ($collaborator->languageVariants as $variant)
        {
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

        // Use the Contact Policy for authorization
        $this->authorize('update', $collaborator);

        $hasLanguageVariantsModule = auth()->user()->currentTeam->hasModule('language-variants');

        $validationRules = [
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'profile' => 'nullable|string',
            'language_pairs' => 'nullable|array',
            'is_native' => 'nullable|array',
            'fare_ids' => 'nullable|array',
        ];

        if ($hasLanguageVariantsModule)
        {
            $validationRules['language'] = 'required|string|exists:languages,code';
        } else
        {
            $validationRules['language'] = 'nullable|string|exists:languages,code';
        }

        $validated = $request->validate($validationRules);

        // Prepare extras data
        $currentData = $collaborator->data ?? (object) [];

        // Ensure extras object exists
        if (! isset($currentData->extras))
        {
            $currentData->extras = (object) [];
        }

        // Update extras fields if provided
        if ($request->has('nif_cif'))
        {
            $currentData->extras->nif_cif = $request->nif_cif ?: null;
        }
        if ($request->has('domicilio'))
        {
            $currentData->extras->domicilio = $request->domicilio ?: null;
        }
        if ($request->has('poblacion'))
        {
            $currentData->extras->poblacion = $request->poblacion ?: null;
        }
        if ($request->has('codigo_postal'))
        {
            $currentData->extras->codigo_postal = $request->codigo_postal ?: null;
        }

        $collaborator->update([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'language' => $validated['language'] ?? null,
            'profile' => $validated['profile'] ?? null,
            'data' => $currentData,
        ]);

        // Process language pairs if they exist
        if ($request->has('language_pairs') && is_array($request->language_pairs) && count($request->language_pairs) > 0)
        {
            // Delete existing language pairs
            $collaborator->languageVariants()->delete();

            $processedPairs = []; // Para evitar duplicados

            // Add new language pairs
            foreach ($request->language_pairs as $index => $pair)
            {
                if (empty($pair))
                {
                    continue;
                }

                [$sourceLanguage, $targetLanguage] = explode('|', $pair);

                // Evitar duplicados en la misma solicitud
                $pairKey = $sourceLanguage.'-'.$targetLanguage;
                if (in_array($pairKey, $processedPairs))
                {
                    continue;
                }

                $processedPairs[] = $pairKey;

                $isNative = isset($request->is_native[$index]) ? (bool) $request->is_native[$index] : false;

                try
                {
                    ContactLanguageVariant::create([
                        'contact_id' => $collaborator->id,
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                        'is_certified' => $isNative,
                    ]);
                } catch (\Illuminate\Database\QueryException $e)
                {
                    // If it's a duplicate error, simply ignore it
                    if ($e->errorInfo[1] == 1062)
                    {
                        continue;
                    }
                    throw $e; // If it's another type of error, throw it
                }
            }
        } else
        {
            // If there are no language pairs, delete all existing ones
            $collaborator->languageVariants()->delete();
        }

        // Process fares/services if they exist
        if ($request->has('fare_ids'))
        {
            // Get the fare IDs or use empty array if none provided
            $fareIds = $request->fare_ids ?? [];

            // Filter out empty values
            $fareIds = array_filter($fareIds, function ($value)
            {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        $teamId = auth()->user()->currentTeam->id;

        // Find the "Ojo" valoration (assuming it corresponds to "En espera" for watch status)
        $watchValoration = ContactValoration::where('team_id', $teamId)
            ->where('name', 'En espera')
            ->first();

        if ($watchValoration)
        {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        $teamId = auth()->user()->currentTeam->id;

        // Find the "Lista negra" valoration
        $blacklistValoration = ContactValoration::where('team_id', $teamId)
            ->where('name', 'Lista negra')
            ->first();

        if ($blacklistValoration)
        {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);

        // Get software IDs, they can come as array, string or JSON
        $softwareIds = [];

        // If it's a JSON request
        if ($request->isJson())
        {
            $data = $request->json()->all();
            $softwareIds = $data['software_ids'] ?? [];
        } else
        {
            // If it's a normal request
            $softwareIds = $request->input('software_ids', []);
        }

        // If it comes as empty string, convert to empty array
        if ($softwareIds === '')
        {
            $softwareIds = [];
        }

        // If it comes as a single ID as string, convert it to array
        if (! is_array($softwareIds) && ! empty($softwareIds))
        {
            $softwareIds = [$softwareIds];
        }

        // Filter out empty or null values that may cause errors
        $softwareIds = array_filter($softwareIds, function ($value)
        {
            return ! empty($value) && $value !== '' && $value !== null;
        });

        // Sync software - use empty array explicitly if no IDs
        $collaborator->softwares()->sync(empty($softwareIds) ? [] : $softwareIds);

        // Load updated softwares with categories
        $collaborator->load('softwares.category');

        // Format response data
        $softwares = $collaborator->softwares->map(function ($software)
        {
            return [
                'id' => $software->id,
                'name' => $software->name,
                'category_name' => $software->category ? $software->category->name : null,
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
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);

        // Get service IDs, they can come as array, string or JSON
        $fareIds = [];

        // If it's a JSON request
        if ($request->isJson())
        {
            $data = $request->json()->all();
            $fareIds = $data['fare_ids'] ?? [];
        } else
        {
            // If it's a normal request
            $fareIds = $request->input('fare_ids', []);
        }

        // If it comes as empty string, convert to empty array
        if ($fareIds === '')
        {
            $fareIds = [];
        }

        // If it comes as a single ID as string, convert it to array
        if (! is_array($fareIds) && ! empty($fareIds))
        {
            $fareIds = [$fareIds];
        }

        // Filtrar valores vacíos o nulos que puedan causar errores
        $fareIds = array_filter($fareIds, function ($value)
        {
            return ! empty($value) && $value !== '' && $value !== null;
        });

        // Sync fares
        $collaborator->fares()->sync(empty($fareIds) ? [] : $fareIds);

        // Load updated fares with types
        $collaborator->load('fares.type');

        // Format response data (use unique fares to avoid duplicates)
        $services = $collaborator->fares->unique('id')->map(function ($fare)
        {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);

        // Get topic IDs, they can come as array, string or JSON
        $topicIds = [];

        // If it's a JSON request
        if ($request->isJson())
        {
            $data = $request->json()->all();
            $topicIds = $data['topic_ids'] ?? [];
        } else
        {
            // If it's a normal request
            $topicIds = $request->input('topic_ids', []);
        }

        // If it comes as empty string, convert to empty array
        if ($topicIds === '')
        {
            $topicIds = [];
        }

        // If it comes as a single ID as string, convert it to array
        if (! is_array($topicIds) && ! empty($topicIds))
        {
            $topicIds = [$topicIds];
        }

        // Filter out empty or null values that may cause errors
        $topicIds = array_filter($topicIds, function ($value)
        {
            return ! empty($value) && $value !== '' && $value !== null;
        });

        // Sync topics - use empty array explicitly if no IDs
        $collaborator->topics()->sync(empty($topicIds) ? [] : $topicIds);

        // Load updated topics
        $collaborator->load('topics');

        // Format response data
        $topics = $collaborator->topics->map(function ($topic)
        {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
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
        if (! $user->teams->contains(auth()->user()->currentTeam->id))
        {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no pertenece al equipo actual',
            ], 422);
        }

        // Check if user is already linked to another contact
        $existingContact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
            ->where('user_id', $user->id)->first();
        if ($existingContact && $existingContact->id !== $collaborator->id)
        {
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

        try
        {
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
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new portfolio item
     */
    public function storePortfolio(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'year' => 'nullable|integer|min:1900|max:'.(date('Y') + 10),
            'notes' => 'nullable|string',
            'position' => 'nullable|string',
            'languages' => 'nullable|array',
        ]);

        $collaborator = Contact::findOrFail($id);

        $data = [];
        if ($request->position)
        {
            $data['position'] = $request->position;
        }
        if ($request->languages)
        {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'year' => 'nullable|integer|min:1900|max:'.(date('Y') + 10),
            'notes' => 'nullable|string',
            'position' => 'nullable|string',
            'languages' => 'nullable|array',
        ]);

        $collaborator = Contact::findOrFail($id);
        $portfolio = $collaborator->portfolios()->findOrFail($portfolioId);

        $data = [];
        if ($request->position)
        {
            $data['position'] = $request->position;
        }
        if ($request->languages)
        {
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
        if (! auth()->user()->can('collaborator.edit'))
        {
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
     * Show collaborator activity
     */
    public function activity($id)
    {
        $collaborator = Contact::with([
            'user.roles',
            'valoration',
        ])->findOrFail($id);

        // Use the Contact Policy for authorization
        $this->authorize('view', $collaborator);

        // Get activities for this collaborator
        $activities = Activity::where(function ($query) use ($id, $collaborator)
        {
            // Activities on the contact
            $query->where('subject_type', Contact::class)
                ->where('subject_id', $id);

            // Also get activities from the linked user if exists
            if ($collaborator->user_id)
            {
                $query->orWhere(function ($subQuery) use ($collaborator)
                {
                    $subQuery->where('subject_type', \App\Models\User::class)
                        ->where('subject_id', $collaborator->user_id);
                });
            }
        })
            ->with(['causer', 'subject'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Format activities for display
        $formattedActivities = $activities->map(function ($activity) use ($collaborator)
        {
            $isOwnActivity = $activity->causer_id === $collaborator->user_id;

            return [
                'id' => $activity->id,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'user_name' => $activity->causer ? $activity->causer->name : 'Sistema',
                'user_photo' => $activity->causer ? $activity->causer->profile_photo_url : null,
                'is_own_activity' => $isOwnActivity,
                'time_ago' => $activity->created_at->diffForHumans(),
                'created_at' => $activity->created_at,
            ];
        });

        return view('collaborator.activity', compact('collaborator', 'formattedActivities'));
    }

    /**
     * Show collaborator media
     */
    public function media($id)
    {
        $collaborator = Contact::with([
            'user.roles',
            'valoration',
        ])->findOrFail($id);

        // Use the Contact Policy for authorization
        $this->authorize('view', $collaborator);

        return view('collaborator.media', compact('collaborator'));
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

        // Get count of active projects
        $activeProjects = Project::getActiveProjectsCount();

        // Get count of active languages (languages with at least 1 collaborator)
        $activeLanguages = Language::getActiveLanguagesCount();

        // Get language combinations with less than 10 collaborators using the model method
        $languageCombinations = ContactLanguageVariant::getCombinationsWithFewCollaborators();

        // Get top languages by collaborator count
        $topLanguages = Language::getTopLanguages(5);

        // Get collaborators with incomplete data
        $incompleteCollaborators = Contact::getIncompleteCollaborators(20);

        // Get recent team activities (simplified approach)
        $teamId = auth()->user()->currentTeam->id;

        // Get all team users IDs
        $teamUserIds = \App\Models\User::whereHas('teams', function ($query) use ($teamId)
        {
            $query->where('teams.id', $teamId);
        })->pluck('id');

        // Get team contact IDs
        $teamContactIds = Contact::where('team_id', $teamId)->pluck('id');

        // Get team project IDs
        $teamProjectIds = Project::where('team_id', $teamId)->pluck('id');

        // Get recent activities from team members or on team subjects (limited to last 10)
        $recentActivities = Activity::with(['causer', 'subject'])
            ->where(function ($query) use ($teamUserIds, $teamContactIds, $teamProjectIds)
            {
                // Activities by team members
                $query->whereIn('causer_id', $teamUserIds)
                      // Or activities on team contacts
                    ->orWhere(function ($subQuery) use ($teamContactIds)
                    {
                        $subQuery->where('subject_type', Contact::class)
                            ->whereIn('subject_id', $teamContactIds);
                    })
                      // Or activities on team projects
                    ->orWhere(function ($subQuery) use ($teamProjectIds)
                    {
                        $subQuery->where('subject_type', Project::class)
                            ->whereIn('subject_id', $teamProjectIds);
                    });
            })
            ->latest()
            ->limit(10)
            ->get();

        // Format activities for display
        $formattedActivities = $recentActivities->map(function ($activity)
        {
            return [
                'id' => $activity->id,
                'user_name' => $activity->causer ? $activity->causer->name : 'Sistema',
                'user_photo' => $activity->causer ? $activity->causer->profile_photo_url : null,
                'is_system_activity' => ! $activity->causer,
                'description' => $activity->description,
                'subject_type' => $activity->subject ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'subject_name' => $activity->subject && method_exists($activity->subject, 'name') ? $activity->subject->name : null,
                'time_ago' => $activity->created_at->diffForHumans(),
                'created_at' => $activity->created_at,
                'properties' => $activity->properties,
            ];
        });

        return view('collaborator.dashboard', compact('totalCollaborators', 'newCollaboratorsThisMonth', 'activeProjects', 'activeLanguages', 'languageCombinations', 'topLanguages', 'incompleteCollaborators', 'formattedActivities'));
    }

    /**
     * Show the accept form for a pending collaborator
     */
    public function showAcceptForm($id)
    {
        if (! auth()->user()->can('collaborator.edit'))
        {
            abort(403, 'No tienes permisos para esta acción.');
        }

        $collaborator = Contact::findOrFail($id);

        // Check if collaborator is already accepted (has user_id)
        if ($collaborator->user_id)
        {
            return redirect()->route('collaborator.show', $id)
                ->with('warning', 'Este colaborador ya ha sido aceptado.');
        }

        return view('collaborator.accept', compact('collaborator'));
    }

    /**
     * Process the acceptance of a collaborator
     */
    public function processAccept(Request $request, $id)
    {
        if (! auth()->user()->can('collaborator.edit'))
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);

        // Check if collaborator is already accepted
        if ($collaborator->user_id)
        {
            return redirect()->route('collaborator.show', $id)
                ->with('warning', 'Este colaborador ya ha sido aceptado.');
        }

        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        if ($validated['action'] === 'reject')
        {
            // Handle rejection
            $collaborator->update([
                'status_id' => 3, // Assuming 3 is rejected status
                'notes' => ($collaborator->notes ? $collaborator->notes."\n\n" : '').
                          'Rechazado el '.now()->format('d/m/Y H:i').' por '.auth()->user()->name.
                          "\nMotivo: ".$validated['rejection_reason'],
            ]);

            // Activity log was removed
            /*
            // Log the activity
            activity()
                ->causedBy(auth()->user())
                ->performedOn($collaborator)
                ->withProperties([
                    'action' => 'rejected',
                    'reason' => $validated['rejection_reason'],
                    'rejected_by' => auth()->user()->name,
                ])
                ->log('Colaborador rechazado');
            */

            return redirect()->route('collaborator-list')
                ->with('success', 'Colaborador rechazado correctamente.');
        }

        // Handle acceptance - create user and link
        try
        {
            // Generate a random password
            $password = \Str::random(12);

            // Create the user
            $user = \App\Models\User::create([
                'name' => $collaborator->name.' '.$collaborator->surname,
                'email' => $collaborator->email,
                'password' => \Hash::make($password),
                'email_verified_at' => now(),
            ]);

            // Assign collaborator role
            $user->assignRole('collaborator');

            // Add user to the current team
            $team = auth()->user()->currentTeam;
            $team->users()->attach($user->id, ['role' => 'collaborator']);

            // Link the user to the collaborator
            $collaborator->update([
                'user_id' => $user->id,
                'status_id' => 1, // Assuming 1 is active status
            ]);

            // Activity log was removed
            /*
            // Log the activity
            activity()
                ->causedBy(auth()->user())
                ->performedOn($collaborator)
                ->withProperties([
                    'action' => 'accepted',
                    'user_created' => $user->id,
                    'accepted_by' => auth()->user()->name,
                ])
                ->log('Colaborador aceptado y usuario creado');
            */

            // Send welcome email with credentials (optional)
            // You can implement this later if needed
            // Mail::to($user->email)->send(new WelcomeCollaboratorMail($user, $password));

            return redirect()->route('collaborator.show', $id)
                ->with('success', 'Colaborador aceptado correctamente. Usuario creado con email: '.$user->email);
        } catch (\Exception $e)
        {
            \Log::error('Error accepting collaborator: '.$e->getMessage());

            return back()
                ->with('error', 'Error al aceptar el colaborador: '.$e->getMessage());
        }
    }

    /**
     * Upload a document for a collaborator
     */
    public function uploadDocument(Request $request, $id)
    {
        $request->validate([
            'document' => 'required|file|max:10240', // 10MB max
            'document_name' => 'nullable|string|max:255',
        ]);

        $collaborator = \App\Models\Contact::findOrFail($id);

        $mediaAdder = $collaborator->addMedia($request->file('document'));
        if ($request->filled('document_name'))
        {
            $mediaAdder->usingName($request->input('document_name'));
        }
        $mediaAdder->toMediaCollection('documents');

        return back()->with('success', 'Documento subido correctamente.');
    }

    /**
     * Delete a document for a collaborator
     */
    public function destroyDocument($id, $mediaId)
    {
        $collaborator = \App\Models\Contact::findOrFail($id);
        $media = $collaborator->getMedia('documents')->where('id', $mediaId)->first();
        if ($media)
        {
            $media->delete();

            return back()->with('success', 'Documento eliminado correctamente.');
        }

        return back()->with('error', 'No se encontró el documento.');
    }

    /**
     * Upload media for a collaborator
     */
    public function uploadMedia(Request $request, $id)
    {
        try
        {
            if (! auth()->user()->can('collaborator.edit'))
            {
                return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
            }

            $request->validate([
                'media' => 'required|file|max:51200', // 50MB max
            ]);

            $collaborator = \App\Models\Contact::findOrFail($id);

            // Log the upload attempt
            \Log::info('Starting media upload', [
                'collaborator_id' => $id,
                'file_name' => $request->file('media')->getClientOriginalName(),
                'file_size' => $request->file('media')->getSize(),
                'mime_type' => $request->file('media')->getMimeType(),
                'user_id' => auth()->id(),
            ]);

            // Normalize filename for better organization
            $originalName = $request->file('media')->getClientOriginalName();
            $extension = $request->file('media')->getClientOriginalExtension();
            $name = pathinfo($originalName, PATHINFO_FILENAME);

            // Create a normalized filename
            $normalizedName = \Illuminate\Support\Str::slug($name);
            if (empty($normalizedName))
            {
                $normalizedName = 'file_'.substr(md5(time().rand()), 0, 8);
            }

            // Add timestamp to ensure uniqueness
            $finalName = $normalizedName.'_'.time().'.'.strtolower($extension);

            \Log::info('Filename processed', [
                'original_name' => $originalName,
                'normalized_name' => $normalizedName,
                'final_name' => $finalName,
            ]);

            $mediaAdder = $collaborator->addMedia($request->file('media'));
            $mediaAdder->usingName($originalName) // Keep original name for display
                ->usingFileName($finalName); // Use normalized name for storage

            $mediaAdder->toMediaCollection('media');

            $media = $collaborator->getMedia('media')->last();
            if (! $media)
            {
                throw new \Exception('Could not create media record');
            }

            // Log successful upload
            \Log::info('Media upload successful', [
                'media_id' => $media->id,
                'collaborator_id' => $id,
                'file_name' => $media->name,
                'file_size' => $media->size,
                'user_id' => auth()->id(),
            ]);

            // Activity log was removed
            /*
            // Log activity
            activity()
                ->performedOn($collaborator)
                ->causedBy(auth()->user())
                ->log('uploaded media file: '.$media->name);
            */

            return response()->json([
                'success' => true,
                'media' => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->getUrl(),
                    'size' => $media->size,
                    'mime_type' => $media->mime_type,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e)
        {
            \Log::error('Validation error in uploadMedia', [
                'collaborator_id' => $id,
                'errors' => $e->errors(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error: '.implode(', ', array_flatten($e->errors())),
            ], 422);
        } catch (\Exception $e)
        {
            \Log::error('Error in uploadMedia', [
                'collaborator_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update media name for a collaborator
     */
    public function updateMedia(Request $request, $id, $mediaId)
    {
        try
        {
            if (! auth()->user()->can('collaborator.edit'))
            {
                return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $collaborator = \App\Models\Contact::findOrFail($id);
            $media = $collaborator->getMedia('media')->where('id', $mediaId)->first();

            if (! $media)
            {
                return response()->json(['success' => false, 'message' => 'Media not found'], 404);
            }

            $oldName = $media->name;
            $media->name = $request->name;
            $media->save();

            // Activity log was removed
            /*
            // Log activity
            activity()
                ->performedOn($collaborator)
                ->causedBy(auth()->user())
                ->log('updated media file name from "'.$oldName.'" to "'.$request->name.'"');
            */

            return response()->json([
                'success' => true,
                'message' => 'Media name updated successfully',
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Error in updateMedia', [
                'collaborator_id' => $id,
                'media_id' => $mediaId,
                'message' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating media name: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete media for a collaborator
     */
    public function destroyMedia($id, $mediaId)
    {
        try
        {
            if (! auth()->user()->can('collaborator.edit'))
            {
                return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
            }

            $collaborator = \App\Models\Contact::findOrFail($id);
            $media = $collaborator->getMedia('media')->where('id', $mediaId)->first();

            if (! $media)
            {
                return response()->json(['success' => false, 'message' => 'Media not found'], 404);
            }

            $mediaName = $media->name;
            $media->delete();

            // Activity log was removed
            /*
            // Log activity
            activity()
                ->performedOn($collaborator)
                ->causedBy(auth()->user())
                ->log('deleted media file: '.$mediaName);
            */

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Error in destroyMedia', [
                'collaborator_id' => $id,
                'media_id' => $mediaId,
                'message' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting media: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for collaborator prices when a service is selected
     *
     * This endpoint calculates media (mean), moda (mode), and mediana (median)
     * statistics for collaborator prices for a specific service/fare.
     * It also provides additional statistics like min, max, range, and standard deviation.
     * The statistics are calculated only from collaborators that match the current filters.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceStatistics(Request $request)
    {
        \Log::info('Service statistics requested', ['service_id' => $request->service_id]);

        if (! $request->has('service_id') || ! $request->service_id)
        {
            \Log::warning('Service statistics: No service ID provided');

            return response()->json([
                'success' => false,
                'message' => 'Service ID is required',
            ], 400);
        }

        $serviceId = $request->service_id;

        // For public access, use a default team ID or get from request
        // If user is authenticated, use their team, otherwise use default or request parameter
        if (auth()->check())
        {
            $teamId = auth()->user()->currentTeam->id;
        } else
        {
            $teamId = $request->team_id ?? 1; // Default to team ID 1, or you can pass it as parameter
        }

        \Log::info('Service statistics: Processing', ['service_id' => $serviceId, 'team_id' => $teamId]);

        // Build the query similar to CollaboratorDataTable
        $query = Contact::where('team_id', $teamId)
            ->whereHas('fares', function ($query) use ($serviceId)
            {
                $query->where('fares.id', $serviceId)
                    ->whereNotNull('contact_fare.price')
                    ->where('contact_fare.price', '>', 0);
            })
            ->with(['fares' => function ($query) use ($serviceId)
            {
                $query->where('fares.id', $serviceId)
                    ->whereNotNull('contact_fare.price')
                    ->where('contact_fare.price', '>', 0);
            }]);

        // Apply the same filters as the DataTable
        $this->applyDataTableFilters($query, $request);

        $collaboratorsWithPrices = $query->get();

        \Log::info('Service statistics: Found collaborators', ['count' => $collaboratorsWithPrices->count()]);

        if ($collaboratorsWithPrices->isEmpty())
        {
            // Check if this is due to availability filtering
            $hasAvailabilityFilter = ($request->has('days') && $request->days) && ($request->has('delivery_date') && $request->delivery_date);

            if ($hasAvailabilityFilter)
            {
                // Calculate actual available days between tomorrow and delivery date
                $startDate = now()->addDay()->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->delivery_date)->endOfDay();
                $actualAvailableDays = $startDate->diffInDays($endDate) + 1; // +1 to include both start and end dates

                // Debug: Log the calculation details
                \Log::info('Days calculation debug', [
                    'startDate' => $startDate->format('Y-m-d H:i:s'),
                    'endDate' => $endDate->format('Y-m-d H:i:s'),
                    'diffInDays' => $startDate->diffInDays($endDate),
                    'actualAvailableDays' => $actualAvailableDays,
                    'requestedDays' => $request->days,
                ]);

                if ($actualAvailableDays < $request->days)
                {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo hay '.$actualAvailableDays.' días disponibles hasta la fecha de entrega, pero se requieren '.$request->days.' días',
                    ], 404);
                } else
                {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontraron colaboradores con precios para este servicio que cumplan con la disponibilidad de '.$request->days.' días',
                    ], 404);
                }
            } else
            {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron colaboradores con precios para este servicio',
                ], 404);
            }
        }

        // Extract all prices for this service
        $prices = [];
        foreach ($collaboratorsWithPrices as $collaborator)
        {
            foreach ($collaborator->fares as $fare)
            {
                if ($fare->pivot->price > 0)
                {
                    $prices[] = (float) $fare->pivot->price;
                }
            }
        }

        if (empty($prices))
        {
            return response()->json([
                'success' => false,
                'message' => 'No valid prices found for this service',
            ], 404);
        }

        // Calculate statistics
        $statistics = $this->calculatePriceStatistics($prices);

        // Get service name
        $service = Fare::find($serviceId);
        $serviceName = $service ? $service->name : 'Unknown Service';

        return response()->json([
            'success' => true,
            'service_name' => $serviceName,
            'total_collaborators' => count($prices),
            'statistics' => $statistics,
        ]);
    }

    /**
     * Apply the same filters as used in CollaboratorDataTable
     */
    private function applyDataTableFilters($query, $request)
    {
        // Filter by source language (base or variant)
        if ($request->has('source_language') && $request->source_language)
        {
            $source = $request->source_language;
            if (strlen($source) === 2)
            {
                // If base language (2-letter), match all variants as source
                $query->whereHas('languageVariants', function ($q) use ($source)
                {
                    $q->where('source_language_code', 'like', $source.'%')
                        ->orWhere('source_language_code', $source);
                });
            } else
            {
                // If exact variant, match only that as source
                $query->whereHas('languageVariants', function ($q) use ($source)
                {
                    $q->where('source_language_code', $source);
                });
            }
        }

        // Filter by target language (base or variant)
        if ($request->has('target_language') && $request->target_language)
        {
            $target = $request->target_language;
            if (strlen($target) === 2)
            {
                // If base language (2-letter), match all variants as target
                $query->whereHas('languageVariants', function ($q) use ($target)
                {
                    $q->where('target_language_code', 'like', $target.'%')
                        ->orWhere('target_language_code', $target);
                });
            } else
            {
                // If exact variant, match only that as target
                $query->whereHas('languageVariants', function ($q) use ($target)
                {
                    $q->where('target_language_code', $target);
                });
            }
        }

        // Filter by availability (days and delivery date)
        if (($request->has('days') && $request->days) && ($request->has('delivery_date') && $request->delivery_date))
        {
            $availableCollaboratorIds = $this->getAvailableCollaboratorIdsForStatistics($request->days, $request->delivery_date);

            if (! empty($availableCollaboratorIds))
            {
                $query->whereIn('id', $availableCollaboratorIds);
            } else
            {
                // If no collaborators are available, return empty result
                $query->whereRaw('1 = 0');
            }
        }
    }

    /**
     * Get available collaborator IDs for statistics (simplified version)
     */
    private function getAvailableCollaboratorIdsForStatistics($days, $deliveryDate)
    {
        // This is a simplified version of the availability calculation
        // For statistics, we'll use a basic availability check

        // Parse delivery date - handle both old format and new date format
        try
        {
            if (in_array($deliveryDate, ['today', '1_week', '15_days', '1_month', '3_months']))
            {
                // Old format - convert to actual date
                switch ($deliveryDate)
                {
                    case 'today':
                        $deliveryDate = now();
                        break;
                    case '1_week':
                        $deliveryDate = now()->addWeek();
                        break;
                    case '15_days':
                        $deliveryDate = now()->addDays(15);
                        break;
                    case '1_month':
                        $deliveryDate = now()->addMonth();
                        break;
                    case '3_months':
                        $deliveryDate = now()->addMonths(3);
                        break;
                }
            } else
            {
                // New format - parse as real date
                $deliveryDate = \Carbon\Carbon::parse($deliveryDate);
            }

            // Check if delivery date is in the past
            if ($deliveryDate->isPast())
            {
                \Log::warning('Delivery date is in the past: '.$deliveryDate->format('Y-m-d'));

                return [];
            }
        } catch (\Exception $e)
        {
            \Log::warning('Could not parse delivery date for statistics: '.$deliveryDate);

            return [];
        }

        $startDate = now()->format('Y-m-d');
        $endDate = $deliveryDate->format('Y-m-d');

        // Get collaborators with weekly availability data
        $collaborators = Contact::where('team_id', auth()->user()->currentTeam->id)
            ->whereHas('weeklyAvailability')
            ->with('weeklyAvailability')
            ->get();

        $availableIds = [];

        foreach ($collaborators as $collaborator)
        {
            $availableDays = $this->calculateAvailableDaysForStatistics($collaborator, $startDate, $endDate);

            if ($availableDays >= $days)
            {
                $availableIds[] = $collaborator->id;
            }
        }

        return $availableIds;
    }

    /**
     * Calculate available days for statistics (simplified version)
     */
    private function calculateAvailableDaysForStatistics($collaborator, $startDate, $endDate)
    {
        $weeklyAvailability = $collaborator->weeklyAvailability;

        // If no weekly availability is set, assume all days are available (same logic as DataTable)
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
        $currentDate = \Carbon\Carbon::parse($startDate);
        $endDateCarbon = \Carbon\Carbon::parse($endDate);

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
     * Calculate price statistics (media, moda, mediana)
     */
    private function calculatePriceStatistics(array $prices)
    {
        sort($prices);
        $count = count($prices);

        // Media (Mean)
        $media = array_sum($prices) / $count;

        // Mediana (Median)
        $mediana = 0;
        if ($count % 2 == 0)
        {
            // Even number of elements
            $mediana = ($prices[($count / 2) - 1] + $prices[$count / 2]) / 2;
        } else
        {
            // Odd number of elements
            $mediana = $prices[floor($count / 2)];
        }

        // Moda (Mode) - most frequent value
        $priceCounts = array_count_values($prices);
        $maxCount = max($priceCounts);
        $moda = array_keys($priceCounts, $maxCount);

        // If all values are unique, there's no mode
        if ($maxCount == 1)
        {
            $moda = null;
        }

        // Additional statistics
        $min = min($prices);
        $max = max($prices);
        $range = $max - $min;

        // Standard deviation
        $variance = 0;
        foreach ($prices as $price)
        {
            $variance += pow($price - $media, 2);
        }
        $variance = $variance / $count;
        $standardDeviation = sqrt($variance);

        return [
            'media' => round($media, 2),
            'mediana' => round($mediana, 2),
            'moda' => $moda ? array_map(function ($value)
            {
                return round($value, 2);
            }, $moda) : null,
            'min' => round($min, 2),
            'max' => round($max, 2),
            'range' => round($range, 2),
            'standard_deviation' => round($standardDeviation, 2),
            'count' => $count,
        ];
    }

    /**
     * Debug method to check availability filtering
     */
    public function debugAvailability(Request $request)
    {
        $days = $request->days ? (int) $request->days : null;
        $deliveryDate = $request->delivery_date;
        $serviceId = $request->service;

        $debug = [
            'input' => [
                'days' => $days,
                'delivery_date' => $deliveryDate,
                'service_id' => $serviceId,
            ],
            'team_id' => auth()->user()->currentTeam->id,
        ];

        // Parse delivery date
        $parsedDeliveryDate = null;
        try
        {
            if (in_array($deliveryDate, ['today', '1_week', '15_days', '1_month', '3_months']))
            {
                switch ($deliveryDate)
                {
                    case 'today':
                        $parsedDeliveryDate = now();
                        break;
                    case '1_week':
                        $parsedDeliveryDate = now()->addWeek();
                        break;
                    case '15_days':
                        $parsedDeliveryDate = now()->addDays(15);
                        break;
                    case '1_month':
                        $parsedDeliveryDate = now()->addMonth();
                        break;
                    case '3_months':
                        $parsedDeliveryDate = now()->addMonths(3);
                        break;
                }
            } else
            {
                $parsedDeliveryDate = \Carbon\Carbon::parse($deliveryDate);
            }
            $debug['parsed_delivery_date'] = $parsedDeliveryDate->format('Y-m-d');
        } catch (\Exception $e)
        {
            $debug['parse_error'] = $e->getMessage();

            return response()->json($debug);
        }

        // Check collaborators with the service
        $collaboratorsWithService = Contact::where('team_id', auth()->user()->currentTeam->id)
            ->whereHas('fares', function ($query) use ($serviceId)
            {
                $query->where('fares.id', $serviceId);
            })
            ->count();

        $debug['collaborators_with_service'] = $collaboratorsWithService;

        // Check collaborators with weekly availability
        $collaboratorsWithAvailability = Contact::where('team_id', auth()->user()->currentTeam->id)
            ->whereHas('weeklyAvailability')
            ->count();

        $debug['collaborators_with_availability'] = $collaboratorsWithAvailability;

        // Check availability calculation for first 5 collaborators
        $startDate = now()->format('Y-m-d');
        $endDate = $parsedDeliveryDate->format('Y-m-d');

        $sampleCollaborators = Contact::where('team_id', auth()->user()->currentTeam->id)
            ->whereHas('fares', function ($query) use ($serviceId)
            {
                $query->where('fares.id', $serviceId);
            })
            ->with(['weeklyAvailability', 'absences' => function ($q) use ($startDate, $endDate)
            {
                $q->whereBetween('absence_date', [$startDate, $endDate]);
            }])
            ->limit(5)
            ->get();

        $debug['sample_collaborators'] = [];

        foreach ($sampleCollaborators as $collaborator)
        {
            $availableDays = $this->calculateAvailableDaysForStatistics($collaborator, $startDate, $endDate);

            $debug['sample_collaborators'][] = [
                'id' => $collaborator->id,
                'name' => $collaborator->name,
                'has_weekly_availability' => $collaborator->weeklyAvailability ? 'yes' : 'no',
                'weekly_availability' => $collaborator->weeklyAvailability ? [
                    'monday' => $collaborator->weeklyAvailability->monday,
                    'tuesday' => $collaborator->weeklyAvailability->tuesday,
                    'wednesday' => $collaborator->weeklyAvailability->wednesday,
                    'thursday' => $collaborator->weeklyAvailability->thursday,
                    'friday' => $collaborator->weeklyAvailability->friday,
                    'saturday' => $collaborator->weeklyAvailability->saturday,
                    'sunday' => $collaborator->weeklyAvailability->sunday,
                ] : null,
                'absences_count' => $collaborator->absences->count(),
                'available_days' => $availableDays,
                'meets_requirement' => $availableDays >= $days ? 'yes' : 'no',
            ];
        }

        // Check total available collaborators
        $availableCollaboratorIds = $this->getAvailableCollaboratorIdsForStatistics($days, $deliveryDate);
        $debug['total_available_collaborators'] = count($availableCollaboratorIds);
        $debug['available_ids'] = $availableCollaboratorIds;

        return response()->json($debug);
    }
}
