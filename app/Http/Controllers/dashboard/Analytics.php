<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Host;

class Analytics extends Controller
{
  public function index()
  {
    $hosts = Host::orderBy('name', 'asc')->get();

    return view('content.dashboard.dashboards-analytics', compact('hosts'));
  }
}
