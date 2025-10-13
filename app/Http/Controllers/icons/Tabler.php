<?php

namespace App\Http\Controllers\icons;

use App\Http\Controllers\Controller;

class Tabler extends Controller
{
    public function index()
    {
        return view('content.icons.icons-tabler');
    }
}
