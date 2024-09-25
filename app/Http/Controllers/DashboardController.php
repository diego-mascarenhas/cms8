<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserContactAction;

class DashboardController extends Controller
{
    public function index()
    {
        $totalTeamSeconds = UserContactAction::sum('duration_seconds');
        $totalTeamMinutes = round($totalTeamSeconds / 60);

        return view('dashboard', compact('totalTeamMinutes'));
    }
}
