<?php

namespace App\Http\Controllers\user_interface;

use App\Http\Controllers\Controller;

class Progress extends Controller
{
    public function index()
    {
        return view('content.user-interface.ui-progress');
    }
}
