<?php

namespace App\Http\Controllers\dashboard;

use App\DataTables\ProjectDataTable;
use App\Http\Controllers\Controller;
use App\Models\Host;

class Analytics extends Controller
{
    public function index(ProjectDataTable $dataTable)
    {
        $hosts = Host::orderBy('name', 'asc')->get();

        return $dataTable->forDashboard()->render('content.dashboard.dashboards-analytics', compact('hosts'));
    }

}
