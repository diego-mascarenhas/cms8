<?php

namespace App\Http\Controllers\layouts;

use App\Http\Controllers\Controller;

class Blank extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.layouts-example.layouts-blank', ['pageConfigs' => $pageConfigs]);
    }
}
