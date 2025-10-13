<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Software;
use App\Models\SoftwareType;
use Illuminate\Http\Request;

class SoftwareApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Authorize the request
        $this->authorize('viewAny', Software::class);

        // Get all software with relationships
        $software = Software::with(['type', 'team'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $software,
            'count' => $software->count(),
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Authorize the request
        $this->authorize('create', Software::class);

        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'nullable|exists:software_types,id',
        ]);

        try
        {
            // Create the software
            $software = Software::create([
                'name' => $request->name,
                'type_id' => $request->type_id,
                'team_id' => auth()->user()->currentTeam->id,
            ]);

            // Load relationships
            $software->load(['type', 'team']);

            return response()->json([
                'success' => true,
                'message' => 'Software created successfully',
                'data' => $software,
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 201);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error creating software: '.$e->getMessage(),
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Software $software)
    {
        // Authorize the request
        $this->authorize('view', $software);

        // Load relationships
        $software->load(['type', 'team', 'contacts']);

        return response()->json([
            'success' => true,
            'data' => $software,
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Software $software)
    {
        // Authorize the request
        $this->authorize('update', $software);

        // Validate the request
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type_id' => 'nullable|exists:software_types,id',
        ]);

        try
        {
            // Update the software
            $software->update($request->only(['name', 'type_id']));

            // Load relationships
            $software->load(['type', 'team']);

            return response()->json([
                'success' => true,
                'message' => 'Software updated successfully',
                'data' => $software,
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error updating software: '.$e->getMessage(),
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Software $software)
    {
        // Authorize the request
        $this->authorize('delete', $software);

        try
        {
            // Delete the software
            $software->delete();

            return response()->json([
                'success' => true,
                'message' => 'Software deleted successfully',
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting software: '.$e->getMessage(),
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 500);
        }
    }

    /**
     * Get software types for dropdowns.
     */
    public function types()
    {
        // Authorize the request
        $this->authorize('viewAny', Software::class);

        $types = SoftwareType::all();

        return response()->json([
            'success' => true,
            'data' => $types,
            'count' => $types->count(),
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }

    /**
     * Get software by type.
     */
    public function byType($typeId)
    {
        // Authorize the request
        $this->authorize('viewAny', Software::class);

        $software = Software::with(['type', 'team'])
            ->where('type_id', $typeId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $software,
            'count' => $software->count(),
            'type_id' => $typeId,
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }
}
