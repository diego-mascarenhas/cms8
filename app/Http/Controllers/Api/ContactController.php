<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the user's team contacts.
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado'
            ], 401);
        }
        
        // Check if user has a current team
        if (!$user->currentTeam) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado'
            ], 400);
        }

        try {
            // Get contacts for the user's current team
            $contacts = Contact::where('team_id', $user->currentTeam->id)
                ->with(['status', 'country', 'language', 'creator', 'responsible'])
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $contacts,
                'team' => [
                    'id' => $user->currentTeam->id,
                    'name' => $user->currentTeam->name,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo contactos vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id ?? null,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified contact.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user || !$user->currentTeam) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo'
            ], 401);
        }

        try {
            $contact = Contact::where('team_id', $user->currentTeam->id)
                ->where('id', $id)
                ->with(['status', 'country', 'language', 'creator', 'responsible', 'enterprise'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $contact,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Contacto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error obteniendo contacto vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'contact_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }
} 