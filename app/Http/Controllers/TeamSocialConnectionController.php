<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\View\View;

class TeamSocialConnectionController extends Controller
{
    public function index(Team $team): View
    {
        $this->authorize('update', $team);

        $connections = $team->teamSocialConnections()
            ->with('source')
            ->orderBy('provider')
            ->get();

        return view('team-settings.social', compact('team', 'connections'));
    }
}
