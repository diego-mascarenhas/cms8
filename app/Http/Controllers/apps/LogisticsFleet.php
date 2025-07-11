<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class LogisticsFleet extends Controller
{
    public function index()
    {
        return view('content.apps.app-logistics-fleet');
    }
}
