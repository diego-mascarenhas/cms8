<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class TeamProductController extends Controller
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

        $query = Product::where('team_id', $team->id)
            ->with(['category', 'currency']);

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

        $product = Product::where('team_id', $team->id)
            ->where('id', $id)
            ->with(['category', 'currency'])
            ->first();

        if (!$product)
        {
            return response()->json([
                'success' => false,
                'error' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }
}
