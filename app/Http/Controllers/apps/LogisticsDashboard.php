<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class LogisticsDashboard extends Controller
{
    public function index()
    {
        return view('content.apps.app-logistics-dashboard');
    }
}
