<?php

namespace App\Http\Controllers;

use App\DataTables\CollaboratorDataTable;
use App\Models\Contact;
use App\Models\ContactLanguageVariant;
use App\Models\ContactValoration;
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
            'phone' => 'nullable|numeric',
            'language_pairs' => 'nullable|array',
            'is_native' => 'nullable|array',
            'fare_ids' => 'nullable|array'
        ]);

        // Add creator_id and team_id automatically
        $validated['creator_id'] = auth()->user()->id;
        $validated['team_id'] = auth()->user()->currentTeam->id;

        $contact = Contact::create([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'creator_id' => $validated['creator_id'],
            'team_id' => $validated['team_id']
        ]);

        // Process language pairs if they exist
        if ($request->has('language_pairs') && is_array($request->language_pairs) && count($request->language_pairs) > 0) {
            $processedPairs = []; // Para evitar duplicados
            
            foreach ($request->language_pairs as $index => $pair) {
                if (empty($pair)) continue;
                
                list($sourceLanguage, $targetLanguage) = explode('|', $pair);
                
                // Evitar duplicados en la misma solicitud
                $pairKey = $sourceLanguage . '-' . $targetLanguage;
                if (in_array($pairKey, $processedPairs)) {
                    continue;
                }
                
                $processedPairs[] = $pairKey;
                
                $isNative = isset($request->is_native[$index]) ? (bool)$request->is_native[$index] : false;
                
                try {
                    ContactLanguageVariant::create([
                        'contact_id' => $contact->id,
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                        'is_certified' => $isNative
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
            $fareIds = array_filter($request->fare_ids, function($value) {
                return !empty($value);
            });
            
            // Sync fares with the contact
            if (!empty($fareIds)) {
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
            'languageVariants.sourceLanguage', 
            'languageVariants.targetLanguage', 
            'user.roles', 
            'valoration',
            'fares.type'
        ])->findOrFail($id);
        return view('collaborator.show', compact('collaborator'));
    }

    public function edit($id)
    {
        $collaborator = Contact::with([
            'languageVariants.sourceLanguage', 
            'languageVariants.targetLanguage',
            'fares'
        ])->findOrFail($id);
        
        // Forzar la carga de los idiomas
        $languagePairs = [];
        
        foreach ($collaborator->languageVariants as $variant) {
            $sourceLanguage = $variant->sourceLanguage;
            $targetLanguage = $variant->targetLanguage;
            
            $languagePairs[] = [
                'source_language' => $variant->source_language_code,
                'target_language' => $variant->target_language_code,
                'source_language_text' => $sourceLanguage ? $sourceLanguage->name : $variant->source_language_code,
                'target_language_text' => $targetLanguage ? $targetLanguage->name : $variant->target_language_code,
                'is_native' => $variant->is_certified
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
            'phone' => 'nullable|numeric',
            'language_pairs' => 'nullable|array',
            'is_native' => 'nullable|array',
            'fare_ids' => 'nullable|array'
        ]);

        $collaborator->update([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null
        ]);

        // Process language pairs if they exist
        if ($request->has('language_pairs') && is_array($request->language_pairs) && count($request->language_pairs) > 0) {
            // Delete existing language pairs
            $collaborator->languageVariants()->delete();
            
            $processedPairs = []; // Para evitar duplicados
            
            // Add new language pairs
            foreach ($request->language_pairs as $index => $pair) {
                if (empty($pair)) continue;
                
                list($sourceLanguage, $targetLanguage) = explode('|', $pair);
                
                // Evitar duplicados en la misma solicitud
                $pairKey = $sourceLanguage . '-' . $targetLanguage;
                if (in_array($pairKey, $processedPairs)) {
                    continue;
                }
                
                $processedPairs[] = $pairKey;
                
                $isNative = isset($request->is_native[$index]) ? (bool)$request->is_native[$index] : false;
                
                try {
                    ContactLanguageVariant::create([
                        'contact_id' => $collaborator->id,
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                        'is_certified' => $isNative
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Si es un error de duplicado, simplemente lo ignoramos
                    if ($e->errorInfo[1] == 1062) {
                        continue;
                    }
                    throw $e; // Si es otro tipo de error, lo lanzamos
                }
            }
        } else {
            // Si no hay pares de idiomas, eliminar todos los existentes
            $collaborator->languageVariants()->delete();
        }

        // Process fares/services if they exist
        if ($request->has('fare_ids')) {
            // Get the fare IDs or use empty array if none provided
            $fareIds = $request->fare_ids ?? [];
            
            // Filter out empty values
            $fareIds = array_filter($fareIds, function($value) {
                return !empty($value);
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
        if (!auth()->user()->can('collaborator.edit')) {
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
                'message' => 'Colaborador marcado como ojo correctamente'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No se pudo encontrar la valoración de supervisión'
        ], 400);
    }

    /**
     * Send collaborator to blacklist
     */
    public function sendToBlacklist($id)
    {
        if (!auth()->user()->can('collaborator.edit')) {
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
                'message' => 'Colaborador enviado a lista negra'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No se pudo encontrar la valoración de lista negra'
        ], 400);
    }

    /**
     * Send notification to collaborator
     */
    public function sendNotification(Request $request, $id)
    {
        if (!auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        $message = $request->input('message');
        
        // TODO: Implement actual notification sending logic here
        // For now, just return success
        
        return response()->json([
            'success' => true,
            'message' => 'Notificación enviada correctamente',
            'data' => [
                'collaborator_id' => $collaborator->id,
                'message' => $message
            ]
        ]);
    }

    /**
     * Update collaborator software
     */
    public function updateSoftware(Request $request, $id)
    {
        if (!auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        
        // Obtener los IDs de software, pueden venir como array o como string o como JSON
        $softwareIds = [];
        
        // Si es una solicitud JSON
        if ($request->isJson()) {
            $data = $request->json()->all();
            $softwareIds = $data['software_ids'] ?? [];
        } else {
            // Si es una solicitud normal
            $softwareIds = $request->input('software_ids', []);
        }
        
        // Si viene como string vacío, convertir a array vacío
        if ($softwareIds === '') {
            $softwareIds = [];
        }
        
        // Si viene un solo ID como string, convertirlo a array
        if (!is_array($softwareIds) && !empty($softwareIds)) {
            $softwareIds = [$softwareIds];
        }
        
        // Filtrar valores vacíos o nulos que puedan causar errores
        $softwareIds = array_filter($softwareIds, function($value) {
            return !empty($value) && $value !== '' && $value !== null;
        });

        // Sync software - usar array vacío explícitamente si no hay IDs
        $collaborator->softwares()->sync(empty($softwareIds) ? [] : $softwareIds);

        // Load updated softwares with types
        $collaborator->load('softwares.type');
        
        // Format response data
        $softwares = $collaborator->softwares->map(function($software) {
            return [
                'id' => $software->id,
                'name' => $software->name,
                'type_name' => $software->type ? $software->type->name : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Software actualizado correctamente',
            'softwares' => $softwares
        ]);
    }
    
    /**
     * Update collaborator services
     */
    public function updateServices(Request $request, $id)
    {
        if (!auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $collaborator = Contact::findOrFail($id);
        
        // Obtener los IDs de servicios
        $fareIds = [];
        
        // Si es una solicitud JSON
        if ($request->isJson()) {
            $data = $request->json()->all();
            $fareIds = $data['fare_ids'] ?? [];
        } else {
            // Si es una solicitud normal
            $fareIds = $request->input('fare_ids', []);
        }
        
        // Si viene como string vacío, convertir a array vacío
        if ($fareIds === '') {
            $fareIds = [];
        }
        
        // Si viene un solo ID como string, convertirlo a array
        if (!is_array($fareIds) && !empty($fareIds)) {
            $fareIds = [$fareIds];
        }
        
        // Filtrar valores vacíos o nulos que puedan causar errores
        $fareIds = array_filter($fareIds, function($value) {
            return !empty($value) && $value !== '' && $value !== null;
        });

        // Sync fares
        $collaborator->fares()->sync(empty($fareIds) ? [] : $fareIds);

        // Load updated fares with types
        $collaborator->load('fares.type');
        
        // Format response data
        $services = $collaborator->fares->map(function($fare) {
            return [
                'id' => $fare->id,
                'name' => $fare->name,
                'type_name' => $fare->type ? $fare->type->name : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Servicios actualizados correctamente',
            'services' => $services
        ]);
    }

    /**
     * Update collaborator valoration
     */
    public function updateValoration(Request $request, $id)
    {
        if (!auth()->user()->can('collaborator.edit')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción'], 403);
        }

        $request->validate([
            'valoration_id' => 'required|exists:contact_valorations,id'
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
                'icon' => $valoration->icon
            ]
        ]);
    }

    /**
     * Link an existing user to a collaborator
     */
    public function linkUser(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $collaborator = Contact::findOrFail($id);
        $user = \App\Models\User::findOrFail($request->user_id);

        // Check if user belongs to the same team
        if (!$user->teams->contains(auth()->user()->currentTeam->id)) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no pertenece al equipo actual'
            ], 422);
        }

        // Check if user is already linked to another contact
        $existingContact = Contact::where('user_id', $user->id)->first();
        if ($existingContact && $existingContact->id !== $collaborator->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este usuario ya está vinculado a otro contacto'
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
                'role' => $user->roles->first()->name ?? 'user'
            ]
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
            'message' => 'Usuario desvinculado correctamente'
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
            'password' => 'required|string|min:8'
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
                    'role' => $request->role
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario: ' . $e->getMessage()
            ], 500);
        }
    }
} 