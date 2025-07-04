<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the user's team projects.
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
            // Get projects for the user's current team
            $projects = Project::where('team_id', $user->currentTeam->id)
                ->with(['client', 'creator', 'responsible'])
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $projects,
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
            \Log::error('Error obteniendo proyectos vía API', [
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
     * Display the specified project.
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
            $project = Project::where('team_id', $user->currentTeam->id)
                ->where('id', $id)
                ->with(['client', 'creator', 'responsible', 'categories'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $project,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Proyecto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error obteniendo proyecto vía API', [
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