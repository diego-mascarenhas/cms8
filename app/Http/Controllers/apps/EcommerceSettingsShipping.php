<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class EcommerceSettingsShipping extends Controller
{
    public function index()
    {
        return view('content.apps.app-ecommerce-settings-shipping');
    }
}
