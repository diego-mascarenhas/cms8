<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the user's team products.
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        if (!$user)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado',
            ], 401);
        }

        // Check if user has a current team
        if (!$user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        try
        {
            // Build base query
            $query = Product::with(['category', 'currency']);

            // Filter by status if provided
            if ($request->has('status'))
            {
                $query->where('status', $request->get('status'));
            }

            // Filter by category if provided
            if ($request->has('category_id'))
            {
                $query->where('category_id', $request->get('category_id'));
            }

            // Filter by WhatsApp enabled if provided
            if ($request->has('whatsapp_enabled'))
            {
                $query->where('whatsapp_enabled', $request->get('whatsapp_enabled'));
            }

            $products = $query->orderBy('name', 'asc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $products,
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
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo productos vía API', [
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
     * Display the specified product.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();

        if (!$user || !$user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        try
        {
            // Find the product first
            $product = Product::where('id', $id)
                ->with(['category', 'currency'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $product,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado',
            ], 404);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo producto vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'product_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
