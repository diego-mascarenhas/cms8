<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use Illuminate\Http\Request;

class TeamEnterpriseController extends Controller
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

        $query = Enterprise::where('team_id', $team->id)
            ->with(['status', 'type', 'responsible', 'enterpriseBillingAddresses']);

        $enterprises = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $enterprises,
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

        $enterprise = Enterprise::where('team_id', $team->id)
            ->where('id', $id)
            ->with(['status', 'type', 'responsible', 'enterpriseBillingAddresses', 'contacts'])
            ->first();

        if (!$enterprise)
        {
            return response()->json([
                'success' => false,
                'error' => 'Enterprise not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $enterprise,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }
}
