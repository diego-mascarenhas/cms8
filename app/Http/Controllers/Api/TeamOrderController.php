<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class TeamOrderController extends Controller
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

        $query = Order::where('team_id', $team->id)
            ->with(['contact', 'currency']);

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

        $order = Order::where('team_id', $team->id)
            ->where('id', $id)
            ->with(['contact', 'currency'])
            ->first();

        if (!$order)
        {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }
}
