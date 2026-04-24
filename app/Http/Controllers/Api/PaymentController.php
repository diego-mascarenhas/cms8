<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the user's team payments.
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
            $query = Payment::query()
                ->with(['enterprise', 'invoice', 'account', 'type']);

            // Filter by transaction type if provided
            if ($request->has('transaction_type'))
            {
                $query->where('transaction_type', $request->get('transaction_type'));
            }

            // Filter by date range if provided
            if ($request->has('date_from'))
            {
                $query->where('date', '>=', $request->get('date_from'));
            }

            if ($request->has('date_to'))
            {
                $query->where('date', '<=', $request->get('date_to'));
            }

            // Filter by enterprise if provided
            if ($request->has('enterprise_id'))
            {
                $query->where('enterprise_id', $request->get('enterprise_id'));
            }

            $payments = $query->orderBy('date', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $payments,
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
            \Log::error('Error obteniendo pagos vía API', [
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
     * Display the specified payment.
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
            $payment = Payment::query()
                ->where('id', $id)
                ->with(['enterprise', 'invoice', 'account', 'type'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $payment,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            return response()->json([
                'success' => false,
                'error' => 'Pago no encontrado',
            ], 404);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo pago vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'payment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
