<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;

class MiscNotAuthorized extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.pages.pages-misc-not-authorized', ['pageConfigs' => $pageConfigs]);
    }
}
