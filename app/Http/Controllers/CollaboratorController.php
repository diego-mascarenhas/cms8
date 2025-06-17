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
            'is_native' => 'nullable|array'
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
        if ($request->has('language_pairs')) {
            foreach ($request->language_pairs as $index => $pair) {
                list($sourceLanguage, $targetLanguage) = explode('|', $pair);
                
                $isNative = isset($request->is_native[$index]) ? (bool)$request->is_native[$index] : false;
                
                ContactLanguageVariant::create([
                    'contact_id' => $contact->id,
                    'source_language_code' => $sourceLanguage,
                    'target_language_code' => $targetLanguage,
                    'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                    'is_certified' => $isNative
                ]);
            }
        }

        return redirect()->route('collaborator.show', $contact->id)
            ->with('success', __('Collaborator created successfully.'));
    }

    public function show($id)
    {
        $collaborator = Contact::with(['softwares.type', 'languageVariants.sourceLanguage', 'languageVariants.targetLanguage'])->findOrFail($id);
        return view('collaborator.show', compact('collaborator'));
    }

    public function edit($id)
    {
        $collaborator = Contact::with(['languageVariants.sourceLanguage', 'languageVariants.targetLanguage'])->findOrFail($id);
        
        // Format language pairs for the view
        $languagePairs = $collaborator->languageVariants->map(function($variant) {
            return [
                'source_language' => $variant->source_language_code,
                'target_language' => $variant->target_language_code,
                'source_language_text' => $variant->sourceLanguage->name ?? $variant->source_language_code,
                'target_language_text' => $variant->targetLanguage->name ?? $variant->target_language_code,
                'is_native' => $variant->is_certified
            ];
        });
        
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
            'is_native' => 'nullable|array'
        ]);

        $collaborator->update([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null
        ]);

        // Process language pairs if they exist
        if ($request->has('language_pairs')) {
            // Delete existing language pairs
            $collaborator->languageVariants()->delete();
            
            // Add new language pairs
            foreach ($request->language_pairs as $index => $pair) {
                list($sourceLanguage, $targetLanguage) = explode('|', $pair);
                
                $isNative = isset($request->is_native[$index]) ? (bool)$request->is_native[$index] : false;
                
                ContactLanguageVariant::create([
                    'contact_id' => $collaborator->id,
                    'source_language_code' => $sourceLanguage,
                    'target_language_code' => $targetLanguage,
                    'proficiency_level' => $isNative ? 5 : 3, // Higher level for native languages
                    'is_certified' => $isNative
                ]);
            }
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
} 