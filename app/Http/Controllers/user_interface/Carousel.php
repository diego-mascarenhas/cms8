<?php

namespace App\Http\Controllers\user_interface;

use App\Http\Controllers\Controller;

class Carousel extends Controller
{
    public function index()
    {
        return view('content.user-interface.ui-carousel');
    }
}
