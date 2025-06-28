<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class UserViewBilling extends Controller
{
    public function index()
    {
        return view('content.apps.app-user-view-billing');
    }
}
