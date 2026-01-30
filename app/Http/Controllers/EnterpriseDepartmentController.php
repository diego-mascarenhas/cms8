<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseDepartment;
use Illuminate\Http\Request;

class EnterpriseDepartmentController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', EnterpriseDepartment::class);

        $departments = EnterpriseDepartment::orderBy('name')->get();

        return view('department.index', compact('departments'));
    }

    public function create()
    {
        $this->authorize('create', EnterpriseDepartment::class);

        $department = new EnterpriseDepartment;

        return view('department.form', compact('department'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', EnterpriseDepartment::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:255',
        ]);

        EnterpriseDepartment::create([
            'name' => $request->name,
            'color' => $request->color,
        ]);

        return redirect()->route('department.index')->with('success', __('Department created successfully.'));
    }

    public function edit(EnterpriseDepartment $department)
    {
        $this->authorize('update', $department);

        return view('department.form', compact('department'));
    }

    public function update(Request $request, EnterpriseDepartment $department)
    {
        $this->authorize('update', $department);

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:255',
        ]);

        $department->update([
            'name' => $request->name,
            'color' => $request->color,
        ]);

        return redirect()->route('department.index')->with('success', __('Department updated successfully.'));
    }

    public function destroy(EnterpriseDepartment $department)
    {
        $this->authorize('delete', $department);

        $department->delete();

        return redirect()->route('department.index')->with('success', __('Department deleted successfully.'));
    }
}
