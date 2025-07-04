<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Software;
use App\Models\SoftwareType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SoftwareController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * Display a listing of the software.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Software::class);

        $query = Software::with(['type', 'team']);

        // Filter by type if provided
        if ($request->has('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        // Search by name if provided
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $software = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $software,
            'message' => 'Software retrieved successfully'
        ]);
    }

    /**
     * Store a newly created software in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Software::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type_id' => ['required', 'exists:software_types,id'],
        ]);

        $validated['team_id'] = auth()->user()->currentTeam->id;

        $software = Software::create($validated);
        $software->load(['type', 'team']);

        return response()->json([
            'success' => true,
            'data' => $software,
            'message' => 'Software created successfully'
        ], 201);
    }

    /**
     * Display the specified software.
     */
    public function show(Software $software): JsonResponse
    {
        $this->authorize('view', $software);

        $software->load(['type', 'team', 'contacts']);

        return response()->json([
            'success' => true,
            'data' => $software,
            'message' => 'Software retrieved successfully'
        ]);
    }

    /**
     * Update the specified software in storage.
     */
    public function update(Request $request, Software $software): JsonResponse
    {
        $this->authorize('update', $software);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type_id' => ['sometimes', 'exists:software_types,id'],
        ]);

        $software->update($validated);
        $software->load(['type', 'team']);

        return response()->json([
            'success' => true,
            'data' => $software,
            'message' => 'Software updated successfully'
        ]);
    }

    /**
     * Remove the specified software from storage.
     */
    public function destroy(Software $software): JsonResponse
    {
        $this->authorize('delete', $software);

        $software->delete();

        return response()->json([
            'success' => true,
            'message' => 'Software deleted successfully'
        ]);
    }

    /**
     * Get all software types.
     */
    public function types(): JsonResponse
    {
        $this->authorize('viewAny', Software::class);

        $types = SoftwareType::withCount('software')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $types,
            'message' => 'Software types retrieved successfully'
        ]);
    }

    /**
     * Get software by type.
     */
    public function byType(SoftwareType $type): JsonResponse
    {
        $this->authorize('viewAny', Software::class);

        $software = Software::with(['type', 'team'])
            ->where('type_id', $type->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $software,
            'message' => 'Software filtered by type retrieved successfully'
        ]);
    }
}
