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
            // Check if user can view any contacts
            if (! $user->can('viewAny', Contact::class))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver contactos',
                ], 403);
            }

            // Build base query with policy-based filtering
            $query = Contact::with(['status', 'country', 'language', 'creator', 'responsible', 'user.roles', 'user.teams']);

            // Apply role-based filtering using Policy
            $filterCallback = \App\Policies\ContactPolicy::getQueryFilter($user);
            $query = $filterCallback($query);

            // Optional search by name, surname, email
            $search = $request->get('search');
            if (is_string($search) && trim($search) !== '')
            {
                $term = '%'.trim($search).'%';
                $query->where(function ($q) use ($term)
                {
                    $q->where('name', 'like', $term)
                        ->orWhere('surname', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            }

            $query->orderBy('name')->orderBy('surname');

            $contacts = $query->paginate($request->get('per_page', 20));

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
                    'role' => $user->roles->first()->name ?? 'user',
                ],
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'own_only' : 'permission_based'),
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo contactos vía API', [
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

    /**
     * Display the specified contact.
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
            // Find the contact first
            $contact = Contact::where('id', $id)
                ->with(['status', 'creator', 'responsible', 'currentEnterprise'])
                ->firstOrFail();

            // Check if user can view this specific contact
            if (! $user->can('view', $contact))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver este contacto',
                ], 403);
            }

            // Return a plain array to avoid serialization errors (accessors, media, etc.)
            $data = [
                'id' => $contact->id,
                'name' => $contact->name,
                'surname' => $contact->surname,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'status' => $contact->status ? ['id' => $contact->status->id, 'name' => $contact->status->name] : null,
                'responsible' => $contact->responsible ? ['id' => $contact->responsible->id, 'name' => $contact->responsible->name] : null,
                'enterprise' => $contact->currentEnterprise ? ['id' => $contact->currentEnterprise->id, 'name' => $contact->currentEnterprise->name] : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'own_only' : 'permission_based'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            return response()->json([
                'success' => false,
                'error' => 'Contacto no encontrado',
            ], 404);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo contacto vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'contact_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
