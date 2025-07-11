<?php

namespace App\Http\Controllers\tables;

use App\Http\Controllers\Controller;

class Basic extends Controller
{
    public function index()
    {
        return view('content.tables.tables-basic');
    }
}
