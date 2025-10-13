<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class Email extends Controller
{
    public function index()
    {
        return view('content.apps.app-email');
    }
}
