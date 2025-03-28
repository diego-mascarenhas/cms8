<?php

namespace App\Http\Controllers;

use App\DataTables\ProjectDataTable;
use App\Models\Project;
use App\Models\Category;
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
        $enterprise_id = request('client_id');
        $categories = Category::getOptions(6000);

        return view('project.form', compact('enterprise_id', 'categories'));
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
        ]);

        Project::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'enterprise_id' => $data['enterprise_id'],
                'category_id' => $data['category_id'],
                'description' => $data['description'],
                'responsible_id' => auth()->id()
            ]
        );

        return redirect()->route('project-list')->with('success', 'Record saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Project::findOrFail($id);
        $categories = Category::getOptions(6000);
        $enterprise_id = $data->enterprise_id;

        return view('project.form', compact('data', 'enterprise_id', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->except(['_token', '_method']);
        
        $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:3|max:255',
        ]);

        $project = Project::findOrFail($id);
        $project->update([
            'name' => $data['name'],
            'enterprise_id' => $data['enterprise_id'],
            'category_id' => $data['category_id'],
            'description' => $data['description'],
            'responsible_id' => auth()->id()
        ]);

        return redirect()->route('project-list')->with('success', 'Project updated successfully.');
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
