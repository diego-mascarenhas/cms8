<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fare;
use App\Models\FareType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FareController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of the fares.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Fare::class);

        $query = Fare::with(['type', 'team', 'units']);

        // Filter by type if provided
        if ($request->has('type_id'))
        {
            $query->where('type_id', $request->type_id);
        }

        // Search by name if provided
        if ($request->has('search'))
        {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $fares = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $fares,
            'message' => 'Fares retrieved successfully',
        ]);
    }

    /**
     * Store a newly created fare in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Fare::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type_id' => ['required', 'exists:fare_types,id'],
            'glosary_id' => ['nullable', 'integer'],
        ]);

        $validated['team_id'] = auth()->user()->currentTeam->id;

        $fare = Fare::create($validated);
        $fare->load(['type', 'team', 'units']);

        return response()->json([
            'success' => true,
            'data' => $fare,
            'message' => 'Fare created successfully',
        ], 201);
    }

    /**
     * Display the specified fare.
     */
    public function show(Fare $fare): JsonResponse
    {
        $this->authorize('view', $fare);

        $fare->load(['type', 'team', 'units', 'userFares']);

        return response()->json([
            'success' => true,
            'data' => $fare,
            'message' => 'Fare retrieved successfully',
        ]);
    }

    /**
     * Update the specified fare in storage.
     */
    public function update(Request $request, Fare $fare): JsonResponse
    {
        $this->authorize('update', $fare);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type_id' => ['sometimes', 'exists:fare_types,id'],
            'glosary_id' => ['nullable', 'integer'],
        ]);

        $fare->update($validated);
        $fare->load(['type', 'team', 'units']);

        return response()->json([
            'success' => true,
            'data' => $fare,
            'message' => 'Fare updated successfully',
        ]);
    }

    /**
     * Remove the specified fare from storage.
     */
    public function destroy(Fare $fare): JsonResponse
    {
        $this->authorize('delete', $fare);

        $fare->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fare deleted successfully',
        ]);
    }

    /**
     * Get all fare types.
     */
    public function types(): JsonResponse
    {
        $this->authorize('viewAny', Fare::class);

        $types = FareType::withCount('fares')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $types,
            'message' => 'Fare types retrieved successfully',
        ]);
    }

    /**
     * Get fares by type.
     */
    public function byType(FareType $type): JsonResponse
    {
        $this->authorize('viewAny', Fare::class);

        $fares = Fare::with(['type', 'team', 'units'])
            ->where('type_id', $type->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $fares,
            'message' => 'Fares filtered by type retrieved successfully',
        ]);
    }
}
