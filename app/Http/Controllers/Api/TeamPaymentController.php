<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class TeamPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $team = $request->attributes->get('team');

        if (!$team)
        {
            return response()->json([
                'success' => false,
                'error' => 'Team not found',
            ], 401);
        }

        $query = Payment::withoutGlobalScopes(['fromJuly2024'])
            ->where('team_id', $team->id)
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
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $team = $request->attributes->get('team');

        if (!$team)
        {
            return response()->json([
                'success' => false,
                'error' => 'Team not found',
            ], 401);
        }

        $payment = Payment::withoutGlobalScopes(['fromJuly2024'])
            ->where('team_id', $team->id)
            ->where('id', $id)
            ->with(['enterprise', 'invoice', 'account', 'type'])
            ->first();

        if (!$payment)
        {
            return response()->json([
                'success' => false,
                'error' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payment,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }
}
