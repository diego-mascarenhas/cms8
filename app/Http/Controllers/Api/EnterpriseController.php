<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use App\Services\Clients\ClientProfileBuilder;
use App\Services\Clients\ClientRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnterpriseController extends Controller
{
    /**
     * Display a listing of the user's team enterprises.
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        if (! $user)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado',
            ], 401);
        }

        // Check if user has a current team
        if (! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        try
        {
            // Check if user can view any enterprises
            if (! $user->can('viewAny', Enterprise::class))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver empresas',
                ], 403);
            }

            // Build base query with policy-based filtering
            $query = Enterprise::with(['status', 'type', 'responsible', 'enterpriseBillingAddresses']);

            // Apply role-based filtering using Policy
            $filterCallback = \App\Policies\ClientPolicy::getQueryFilter($user);
            $query = $filterCallback($query);

            $search = $request->get('search');
            if (is_string($search) && trim($search) !== '')
            {
                $term = '%'.trim($search).'%';
                $query->where('name', 'like', $term);
            }

            $query->orderBy('name');

            $enterprises = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $enterprises,
                'team' => [
                    'id' => $user->currentTeam->id,
                    'name' => $user->currentTeam->name,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()->name ?? 'user',
                ],
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'own_only' : 'permission_based'),
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo empresas vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function roster(Request $request, ClientRoster $roster): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        if (! $user->can('viewAny', Enterprise::class))
        {
            return response()->json([
                'success' => false,
                'error' => 'No tienes permisos para ver clientes',
            ], 403);
        }

        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        return response()->json([
            'success' => true,
            'data' => $roster->page($user, $request->get('search'), $page, $perPage),
        ]);
    }

    /**
     * Client dossier used by the Axis client page.
     */
    public function profile(Request $request, string $id, ClientProfileBuilder $profiles)
    {
        $user = $request->user();

        if (! $user || ! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $enterprise = Enterprise::withTrashed()->where('id', $id)->first();

        if (! $enterprise || ! $user->can('view', $enterprise))
        {
            return response()->json([
                'success' => false,
                'error' => 'Empresa no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profiles->build($enterprise),
        ]);
    }

    /**
     * Display the specified enterprise.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();

        if (! $user || ! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        try
        {
            // Find the enterprise first
            $enterprise = Enterprise::where('id', $id)
                ->with(['status', 'type', 'responsible', 'enterpriseBillingAddresses', 'contacts'])
                ->firstOrFail();

            // Check if user can view this specific enterprise
            if (! $user->can('view', $enterprise))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver esta empresa',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $enterprise,
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'own_only' : 'permission_based'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            return response()->json([
                'success' => false,
                'error' => 'Empresa no encontrada',
            ], 404);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo empresa vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'enterprise_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
