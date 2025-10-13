<?php

namespace App\Http\Controllers\layouts;

use App\Http\Controllers\Controller;

class WithoutNavbar extends Controller
{
    public function index()
    {
        return view('content.layouts-example.layouts-without-navbar');
    }
}
