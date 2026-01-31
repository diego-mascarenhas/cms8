<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use App\Services\GoogleCredentialsService;

class Calendar extends Controller
{
    public function index()
    {
        $team = auth()->user()->currentTeam;
        $hasGoogleCredentials = GoogleCredentialsService::hasCredentials($team);

        return view('content.apps.app-calendar', compact('hasGoogleCredentials'));
    }
}
