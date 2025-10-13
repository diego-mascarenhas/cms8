<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;

class Landing extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'front'];

        return view('content.front-pages.landing-page', ['pageConfigs' => $pageConfigs]);
    }
}
