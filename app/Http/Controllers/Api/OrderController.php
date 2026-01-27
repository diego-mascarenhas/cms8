<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the user's team orders.
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
            $query = Order::with(['contact', 'currency']);

            // Filter by payment status if provided
            if ($request->has('payment_status'))
            {
                $query->where('payment_status', $request->get('payment_status'));
            }

            // Filter by delivery status if provided
            if ($request->has('delivery_status'))
            {
                $query->where('delivery_status', $request->get('delivery_status'));
            }

            // Filter by contact if provided
            if ($request->has('contact_id'))
            {
                $query->where('contact_id', $request->get('contact_id'));
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $orders,
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
            \Log::error('Error obteniendo órdenes vía API', [
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
     * Display the specified order.
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
            // Find the order first
            $order = Order::where('id', $id)
                ->with(['contact', 'currency'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            return response()->json([
                'success' => false,
                'error' => 'Orden no encontrada',
            ], 404);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo orden vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
