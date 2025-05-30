<?php

namespace App\Http\Controllers;

use App\DataTables\ProjectDataTable;
use App\Models\Project;
use App\Models\ProjectStatus;
use Illuminate\Http\Request;
use App\Models\Enterprise;
use App\Models\User;

class ProjectController extends Controller
{
    public function index(ProjectDataTable $dataTable)
    {
        return $dataTable->render('project.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $enterprise_id = request('enterprise_id');
        $statuses = ProjectStatus::getOptions();

        return view('project.form', compact('enterprise_id', 'statuses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:3|max:255',
            'enterprise_id' => 'required|exists:enterprises,id',
            'responsible_id' => 'required|exists:users,id',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|integer|min:0|max:20',
            'cost' => 'nullable|numeric|min:0',
            'status_id' => 'required|exists:project_statuses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        Project::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'enterprise_id' => $data['enterprise_id'],
                'category_id' => $data['category_id'] ?? null,
                'description' => $data['description'],
                'responsible_id' => $data['responsible_id'],
                'price' => $data['price'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'cost' => $data['cost'] ?? null,
                'status_id' => $data['status_id'] ?? 1,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
            ]
        );

        return redirect()->route('project-list')->with('success', 'Record saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::with(['client', 'responsible', 'status', 'category'])
            ->findOrFail($id);
            
        return view('project.show', compact('project'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Project::findOrFail($id);
        $enterprise_id = $data->enterprise_id;
        $statuses = ProjectStatus::getOptions();

        return view('project.form', compact('data', 'enterprise_id', 'statuses'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = Project::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
