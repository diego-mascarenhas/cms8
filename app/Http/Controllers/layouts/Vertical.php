<?php

namespace App\Http\Controllers\layouts;

use App\Http\Controllers\Controller;

class Vertical extends Controller
{
    public function index()
    {

        $pageConfigs = ['myLayout' => 'vertical'];

        return view('content.dashboard.dashboards-analytics', ['pageConfigs' => $pageConfigs]);
    }
}
