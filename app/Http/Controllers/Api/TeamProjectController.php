<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $team = $request->attributes->get('team');
        
        $projects = Project::where('team_id', $team->id)
            ->with(['client', 'enterprise'])
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $projects,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $team = $request->attributes->get('team');
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'enterprise_id' => 'nullable|exists:enterprises,id',
        ]);

        $project = Project::create([
            'team_id' => $team->id,
            'name' => $request->name,
            'description' => $request->description,
            'client_id' => $request->client_id,
            'enterprise_id' => $request->enterprise_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $project,
            'message' => 'Project created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $team = $request->attributes->get('team');
        
        $project = Project::where('team_id', $team->id)
            ->where('id', $id)
            ->with(['client', 'enterprise'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $team = $request->attributes->get('team');
        
        $project = Project::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'enterprise_id' => 'nullable|exists:enterprises,id',
        ]);

        $project->update($request->only(['name', 'description', 'client_id', 'enterprise_id']));

        return response()->json([
            'success' => true,
            'data' => $project,
            'message' => 'Project updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $team = $request->attributes->get('team');
        
        $project = Project::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully',
        ]);
    }
} 