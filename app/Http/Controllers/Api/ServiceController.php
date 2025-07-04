<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Policies\ServicePolicy;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        
        // Check if user can view any services
        if (!$user->can('viewAny', Service::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view services',
                'data' => []
            ], 403);
        }

        // Apply role-based filtering
        $query = Service::query();
        $filter = ServicePolicy::getQueryFilter($user);
        $filter($query);

        $services = $query->with(['enterprise:id,name', 'responsible:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Services retrieved successfully',
            'data' => $services->items(),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
            ],
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()->name ?? 'No role'
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->can('create', Service::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create services'
            ], 403);
        }

        // Validate the request
        $validatedData = $request->validate([
            'enterprise_id' => 'required|exists:enterprises,id',
            'category_id' => 'nullable|exists:categories,id',
            'operation' => 'nullable|string|max:255',
            'description' => 'required|string',
            'data' => 'nullable|array',
            'currency_id' => 'nullable|exists:currencies,id',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'frequency' => 'nullable|integer|min:1',
            'next_billing' => 'nullable|date',
            'last_billed' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'responsible_id' => 'required|exists:users,id',
            'status' => 'required|integer|min:1|max:8',
        ]);

        try {
            $service = Service::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Service created successfully',
                'data' => $service->load(['enterprise:id,name', 'responsible:id,name']),
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()->name ?? 'No role'
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = auth()->user();
        $service = Service::with(['enterprise:id,name', 'responsible:id,name'])->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        if (!$user->can('view', $service)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this service'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service retrieved successfully',
            'data' => $service,
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()->name ?? 'No role'
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = auth()->user();
        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        if (!$user->can('update', $service)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this service'
            ], 403);
        }

        // Validate the request
        $validatedData = $request->validate([
            'enterprise_id' => 'sometimes|required|exists:enterprises,id',
            'category_id' => 'nullable|exists:categories,id',
            'operation' => 'nullable|string|max:255',
            'description' => 'sometimes|required|string',
            'data' => 'nullable|array',
            'currency_id' => 'nullable|exists:currencies,id',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'frequency' => 'nullable|integer|min:1',
            'next_billing' => 'nullable|date',
            'last_billed' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'responsible_id' => 'sometimes|required|exists:users,id',
            'status' => 'sometimes|required|integer|min:1|max:8',
        ]);

        try {
            $service->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Service updated successfully',
                'data' => $service->load(['enterprise:id,name', 'responsible:id,name']),
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()->name ?? 'No role'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = auth()->user();
        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        if (!$user->can('delete', $service)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this service'
            ], 403);
        }

        try {
            $service->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully',
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()->name ?? 'No role'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting service: ' . $e->getMessage()
            ], 500);
        }
    }
}
