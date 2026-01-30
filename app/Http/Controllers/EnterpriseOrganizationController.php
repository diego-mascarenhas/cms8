<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseDepartment;
use App\Models\EnterpriseOrganization;
use Illuminate\Http\Request;

class EnterpriseOrganizationController extends Controller
{
    public function index()
    {
        return view('organization.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = new \stdClass;
        $departments = EnterpriseDepartment::all()->map(function ($department)
        {
            return [
                'id' => $department->id,
                'name' => $department->name,
            ];
        });

        return view('organization.form', compact('data', 'departments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'department_id' => 'required|exists:enterprise_departments,id',
            'responsible_id' => 'required|exists:users,id',
            'time_allocation' => 'required|string|max:255',
            'availability' => 'nullable|string|max:255',
        ]);

        // Find the highest order for this department
        $maxOrder = EnterpriseOrganization::where('department_id', $request->department_id)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->max('order');

        EnterpriseOrganization::create([
            'name' => $request->name,
            'description' => $request->description,
            'department_id' => $request->department_id,
            'team_id' => auth()->user()->currentTeam->id,
            'responsible_id' => $request->responsible_id,
            'time_allocation' => $request->time_allocation,
            'availability' => $request->availability,
            'order' => ($maxOrder ?? 0) + 1,
        ]);

        return redirect()->route('organization.index')->with('success', 'Task created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $data = EnterpriseOrganization::where('id', $id)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->firstOrFail();

        $departments = EnterpriseDepartment::all()->map(function ($department)
        {
            return [
                'id' => $department->id,
                'name' => $department->name,
            ];
        });

        return view('organization.form', compact('data', 'departments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'department_id' => 'required|exists:enterprise_departments,id',
            'responsible_id' => 'required|exists:users,id',
            'time_allocation' => 'required|string|max:255',
            'availability' => 'nullable|string|max:255',
        ]);

        $organization = EnterpriseOrganization::where('id', $id)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->firstOrFail();

        $organization->update([
            'name' => $request->name,
            'description' => $request->description,
            'department_id' => $request->department_id,
            'responsible_id' => $request->responsible_id,
            'time_allocation' => $request->time_allocation,
            'availability' => $request->availability,
        ]);

        return redirect()->route('organization.index')->with('success', 'Task updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $organization = EnterpriseOrganization::where('id', $id)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->firstOrFail();

        $organization->delete();

        return redirect()->route('organization.index')->with('success', 'Task deleted successfully.');
    }
}
