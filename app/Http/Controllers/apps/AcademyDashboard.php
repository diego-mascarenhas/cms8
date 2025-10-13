<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class AcademyDashboard extends Controller
{
    public function index()
    {
        return view('content.apps.app-academy-dashboard');
    }
}
