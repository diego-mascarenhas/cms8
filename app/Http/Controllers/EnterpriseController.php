<?php

namespace App\Http\Controllers;

use App\DataTables\EnterpriseDataTable;
use App\Models\Enterprise;
use App\Models\EnterpriseStatus;

class EnterpriseController extends Controller
{
    public function index(EnterpriseDataTable $dataTable)
    {
        $this->authorize('access-billing-modules');

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $teamId = auth()->user()->current_team_id;

        $data = Enterprise::getContactStats($teamId);
        $data['enterpriseStatuses'] = EnterpriseStatus::getOptions(1);

        return $dataTable->render('enterprise.index', $data);
    }
}
