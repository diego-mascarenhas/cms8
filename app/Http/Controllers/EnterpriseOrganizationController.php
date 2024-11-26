<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseDepartment;
use App\Models\EnterpriseOrganization;
use Illuminate\Http\Request;

class EnterpriseOrganizationController extends Controller
{
    public function index()
    {
        $departments = EnterpriseDepartment::all();

        $departmentPostits = [];

        foreach ($departments as $department) {
            $postits = EnterpriseOrganization::where('department_id', $department->id)
                ->with('responsible')
                ->orderBy('order')
                ->get()
                ->map(function ($organization) use ($department) {
                    return [
                        'header' => $organization->name,
                        'author' => $organization->responsible->name ?? 'N/A',
                        'content' => $organization->description,
                        'time_allocation' => $organization->time_allocation,
                        'color' => $department->color ?? 'yellow',
                        'availability' => $organization->availability
                    ];
                });

            $departmentPostits[$department->name] = $postits;
        }

        return view('organization.index', compact('departmentPostits'));
    }
}