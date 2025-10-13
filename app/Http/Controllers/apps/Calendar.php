<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class Calendar extends Controller
{
    public function index()
    {
        return view('content.apps.app-calendar');
    }
}
