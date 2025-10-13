<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;

class HelpCenter extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'front'];

        return view('content.front-pages.help-center-landing', ['pageConfigs' => $pageConfigs]);
    }
}
