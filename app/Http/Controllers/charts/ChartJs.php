<?php

namespace App\Http\Controllers\charts;

use App\Http\Controllers\Controller;

class ChartJs extends Controller
{
    public function index()
    {
        return view('content.charts.charts-chartjs');
    }
}
